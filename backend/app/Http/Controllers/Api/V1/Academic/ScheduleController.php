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
