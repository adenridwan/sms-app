<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\ScheduleResource;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Academic\Schedule;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use App\Infrastructure\Persistence\Eloquent\Academic\TimeSlot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ScheduleController extends ApiController
{
    // 'teacher.profile' (bukan cuma 'teacher') wajib: ScheduleResource baca
    // $teacher->full_name, yang accessor-nya butuh $teacher->profile untuk
    // first_name/last_name. Tanpa ini, lazy load ->profile kena
    // Model::preventLazyLoading() (aktif di luar production) dan 500 setiap
    // GET /schedules — lihat storage/logs (LazyLoadingViolationException).
    private const RELATIONS = ['subject', 'teacher.profile', 'timeSlot'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'classroom_id' => ['required', 'uuid'],
            'semester_id' => ['required', 'uuid'],
        ]);

        $query = Schedule::with(self::RELATIONS)
            ->where('classroom_id', $request->classroom_id)
            ->where('semester_id', $request->semester_id)
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->get('teacher_id')))
            ->orderBy('day_of_week');

        $perPage = min((int) $request->get('per_page', 100), 200);
        $schedules = $query->paginate($perPage);

        return $this->success(ScheduleResource::collection($schedules)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'semester_id' => ['required', 'uuid', Rule::exists('semesters', 'id')],
            'classroom_id' => ['required', 'uuid', Rule::exists('classrooms', 'id')],
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')],
            'teacher_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'time_slot_id' => ['required', 'uuid', Rule::exists('time_slots', 'id')],
            'day_of_week' => ['required', 'integer', 'between:1,6'],
            'room' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $conflict = $this->findConflict($data);
        if ($conflict) {
            return $this->error($conflict, 422);
        }

        $schedule = Schedule::create($data);
        $schedule->load(self::RELATIONS);

        return $this->success(new ScheduleResource($schedule), 'Jadwal berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule): JsonResponse
    {
        $schedule->load(self::RELATIONS);

        return $this->success(new ScheduleResource($schedule));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        $data = $request->validate([
            'subject_id' => ['sometimes', 'uuid', Rule::exists('subjects', 'id')],
            'teacher_id' => ['sometimes', 'uuid', Rule::exists('users', 'id')],
            'time_slot_id' => ['sometimes', 'uuid', Rule::exists('time_slots', 'id')],
            'day_of_week' => ['sometimes', 'integer', 'between:1,6'],
            'room' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $conflict = $this->findConflict(array_merge($schedule->only([
            'classroom_id', 'semester_id', 'teacher_id', 'time_slot_id', 'day_of_week',
        ]), $data), $schedule->id);
        if ($conflict) {
            return $this->error($conflict, 422);
        }

        $schedule->update($data);
        $schedule->load(self::RELATIONS);

        return $this->success(new ScheduleResource($schedule), 'Jadwal berhasil diperbarui');
    }

    /**
     * Export the weekly schedule grid for a classroom+semester as PDF —
     * same grid shape as the frontend (time_slots as rows, day_of_week 1-6
     * as columns, is_break rows spanning the full width).
     */
    public function exportPdf(Request $request)
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid', Rule::exists('classrooms', 'id')],
            'semester_id' => ['required', 'uuid', Rule::exists('semesters', 'id')],
        ]);

        $classroom = Classroom::with(['gradeLevel', 'major', 'academicYear'])->findOrFail($data['classroom_id']);
        $semester = Semester::findOrFail($data['semester_id']);

        $schedules = Schedule::with(self::RELATIONS)
            ->where('classroom_id', $data['classroom_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('is_active', true)
            ->get();

        $timeSlots = TimeSlot::orderBy('order')->orderBy('start_time')->get();

        $grid = $timeSlots->map(function (TimeSlot $slot) use ($schedules) {
            $days = [];
            if (! $slot->is_break) {
                foreach (range(1, 6) as $day) {
                    $days[$day] = $schedules->first(
                        fn (Schedule $s) => (int) $s->day_of_week === $day && $s->time_slot_id === $slot->id
                    );
                }
            }

            return ['slot' => $slot, 'days' => $days];
        });

        $pdf = Pdf::loadView('reports.academic.schedule', [
            'classroom' => $classroom,
            'semester' => $semester,
            'grid' => $grid,
            'dayNames' => array_slice(Schedule::DAY_NAMES, 1, 6),
        ])->setPaper('a4', 'landscape');

        $filename = 'jadwal-' . Str::slug($classroom->name) . '-' . Str::slug($semester->name) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Salin seluruh jadwal dari kelas+semester lain ke kelas+semester yang
     * sedang dibuka — supaya kelas paralel atau semester baru tidak perlu
     * input ulang manual. Default melewati sel yang di tujuan sudah terisi
     * (aman); centang overwrite untuk menimpa.
     */
    public function copyFromClassroom(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        $data = $request->validate([
            'source_classroom_id' => ['required', 'uuid', Rule::exists('classrooms', 'id')],
            'source_semester_id' => ['required', 'uuid', Rule::exists('semesters', 'id')],
            'target_classroom_id' => ['required', 'uuid', Rule::exists('classrooms', 'id')],
            'target_semester_id' => ['required', 'uuid', Rule::exists('semesters', 'id')],
            'target_academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'overwrite' => ['boolean'],
        ]);

        if ($data['source_classroom_id'] === $data['target_classroom_id']
            && $data['source_semester_id'] === $data['target_semester_id']) {
            return $this->error('Kelas & semester sumber tidak boleh sama dengan tujuan.', 422);
        }

        $sourceSchedules = Schedule::with('timeSlot')
            ->where('classroom_id', $data['source_classroom_id'])
            ->where('semester_id', $data['source_semester_id'])
            ->get();

        if ($sourceSchedules->isEmpty()) {
            return $this->error('Kelas sumber belum punya jadwal pada semester tersebut.', 422);
        }

        [$copied, $skipped] = $this->copySchedules($sourceSchedules, [
            'academic_year_id' => $data['target_academic_year_id'],
            'semester_id' => $data['target_semester_id'],
            'classroom_id' => $data['target_classroom_id'],
        ], $data['overwrite'] ?? false);

        return $this->success(
            ['copied' => $copied, 'skipped' => $skipped],
            $this->copyResultMessage($copied, $skipped),
        );
    }

    /**
     * Salin jadwal satu hari ke satu/lebih hari lain dalam kelas+semester
     * yang sama — untuk pola jadwal yang berulang di beberapa hari.
     */
    public function copyFromDay(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'classroom_id' => ['required', 'uuid', Rule::exists('classrooms', 'id')],
            'semester_id' => ['required', 'uuid', Rule::exists('semesters', 'id')],
            'source_day_of_week' => ['required', 'integer', 'between:1,6'],
            'target_days' => ['required', 'array', 'min:1'],
            'target_days.*' => ['integer', 'between:1,6', 'different:source_day_of_week'],
            'overwrite' => ['boolean'],
        ]);

        $sourceSchedules = Schedule::with('timeSlot')
            ->where('classroom_id', $data['classroom_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('day_of_week', $data['source_day_of_week'])
            ->get();

        if ($sourceSchedules->isEmpty()) {
            return $this->error('Hari sumber belum punya jadwal.', 422);
        }

        $copied = 0;
        $skipped = [];

        foreach (array_unique($data['target_days']) as $targetDay) {
            [$c, $s] = $this->copySchedules($sourceSchedules, [
                'academic_year_id' => $data['academic_year_id'],
                'semester_id' => $data['semester_id'],
                'classroom_id' => $data['classroom_id'],
            ], $data['overwrite'] ?? false, overrideDay: (int) $targetDay);
            $copied += $c;
            $skipped = array_merge($skipped, $s);
        }

        return $this->success(
            ['copied' => $copied, 'skipped' => $skipped],
            $this->copyResultMessage($copied, $skipped),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Schedule>  $sourceSchedules
     * @return array{0: int, 1: string[]}
     */
    private function copySchedules($sourceSchedules, array $targetContext, bool $overwrite, ?int $overrideDay = null): array
    {
        $copied = 0;
        $skipped = [];

        foreach ($sourceSchedules as $source) {
            $dayOfWeek = $overrideDay ?? (int) $source->day_of_week;
            $label = ($source->timeSlot->name ?? 'Jam') . ' · ' . (Schedule::DAY_NAMES[$dayOfWeek] ?? '');

            $candidate = array_merge($targetContext, [
                'subject_id' => $source->subject_id,
                'teacher_id' => $source->teacher_id,
                'time_slot_id' => $source->time_slot_id,
                'day_of_week' => $dayOfWeek,
                'room' => $source->room,
                'is_active' => $source->is_active,
            ]);

            $existing = Schedule::where('classroom_id', $candidate['classroom_id'])
                ->where('semester_id', $candidate['semester_id'])
                ->where('day_of_week', $dayOfWeek)
                ->where('time_slot_id', $candidate['time_slot_id'])
                ->first();

            if ($existing) {
                if (! $overwrite) {
                    $skipped[] = "{$label}: kelas tujuan sudah ada jadwal";
                    continue;
                }
                // Soft delete biasa — `schedule_unique` sekarang partial index
                // (`WHERE deleted_at IS NULL`, lihat migrasi
                // 2026_08_09_000001), jadi baris yang di-soft-delete tidak
                // lagi dihitung menempati slotnya dan insert di bawah aman.
                $existing->delete();
            }

            // $source->id dikecualikan: kalau tidak, baris sumber sendiri
            // (guru yang sama, hari+jam yang sama) selalu terdeteksi
            // "bentrok" pada pengecekan guru lintas-kelas, padahal itu
            // memang baris yang sedang kita salin.
            $conflict = $this->findConflict($candidate, $source->id);
            if ($conflict) {
                $skipped[] = "{$label}: {$conflict}";
                continue;
            }

            Schedule::create($candidate);
            $copied++;
        }

        return [$copied, $skipped];
    }

    /**
     * @param  string[]  $skipped
     */
    private function copyResultMessage(int $copied, array $skipped): string
    {
        $message = "{$copied} jadwal berhasil disalin.";
        if (count($skipped) > 0) {
            $message .= ' ' . count($skipped) . ' dilewati karena bentrok.';
        }

        return $message;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        abort_unless(request()->user()->can('schedules.manage'), 403);

        $schedule->delete();

        return $this->success(null, 'Jadwal berhasil dihapus');
    }

    /**
     * Cari konflik kelas (dijamin unique constraint DB juga) atau guru
     * (tidak ada constraint DB — guru secara desain boleh dobel dalam kasus
     * tertentu, tapi kita tetap tolak dengan pesan jelas by default).
     */
    private function findConflict(array $data, ?string $excludeId = null): ?string
    {
        $classConflict = Schedule::where('classroom_id', $data['classroom_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('time_slot_id', $data['time_slot_id'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($classConflict) {
            return 'Kelas sudah memiliki jadwal lain pada hari dan jam yang sama';
        }

        $teacherConflict = Schedule::where('teacher_id', $data['teacher_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('time_slot_id', $data['time_slot_id'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($teacherConflict) {
            return 'Guru sudah memiliki jadwal mengajar lain pada hari dan jam yang sama';
        }

        return null;
    }
}
