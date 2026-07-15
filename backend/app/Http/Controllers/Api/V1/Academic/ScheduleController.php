<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Models\Academic\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Schedule::with(['classRoom', 'subject', 'teacher.user'])
            ->when($request->class_room_id, fn($q, $classId) => $q->where('class_room_id', $classId))
            ->when($request->teacher_id, fn($q, $teacherId) => $q->where('teacher_id', $teacherId))
            ->when($request->subject_id, fn($q, $subjectId) => $q->where('subject_id', $subjectId))
            ->when($request->day, fn($q, $day) => $q->where('day', $day))
            ->when($request->academic_year_id, fn($q, $yearId) => $q->where('academic_year_id', $yearId))
            ->when($request->semester_id, fn($q, $semesterId) => $q->where('semester_id', $semesterId));

        $sortField = $request->get('sort', 'day');
        $sortDirection = $request->get('direction', 'asc');

        if ($sortField === 'day') {
            $query->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
                ->orderBy('start_time', 'asc');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->get('per_page', 50);
        $schedules = $query->paginate($perPage);

        return $this->success($schedules);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_room_id' => ['required', 'uuid', 'exists:class_rooms,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'day' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        // Check for schedule conflicts
        $conflict = $this->checkConflict($data);
        if ($conflict) {
            return $this->error($conflict, 422);
        }

        $schedule = Schedule::create($data);
        $schedule->load(['classRoom', 'subject', 'teacher.user']);

        return $this->success($schedule, 'Jadwal berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule): JsonResponse
    {
        $schedule->load(['classRoom', 'subject', 'teacher.user', 'academicYear', 'semester']);

        return $this->success($schedule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'class_room_id' => ['sometimes', 'uuid', 'exists:class_rooms,id'],
            'subject_id' => ['sometimes', 'uuid', 'exists:subjects,id'],
            'teacher_id' => ['sometimes', 'uuid', 'exists:teachers,id'],
            'day' => ['sometimes', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        // Check for schedule conflicts
        $checkData = array_merge($schedule->toArray(), $data);
        $conflict = $this->checkConflict($checkData, $schedule->id);
        if ($conflict) {
            return $this->error($conflict, 422);
        }

        $schedule->update($data);
        $schedule->load(['classRoom', 'subject', 'teacher.user']);

        return $this->success($schedule, 'Jadwal berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();

        return $this->success(null, 'Jadwal berhasil dihapus');
    }

    /**
     * Get schedule by class.
     */
    public function byClass(Request $request): JsonResponse
    {
        $request->validate([
            'class_room_id' => ['required', 'uuid', 'exists:class_rooms,id'],
        ]);

        $schedules = Schedule::with(['subject', 'teacher.user'])
            ->where('class_room_id', $request->class_room_id)
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->orderBy('start_time')
            ->get()
            ->groupBy('day');

        return $this->success($schedules);
    }

    /**
     * Get schedule by teacher.
     */
    public function byTeacher(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
        ]);

        $schedules = Schedule::with(['classRoom', 'subject'])
            ->where('teacher_id', $request->teacher_id)
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->orderBy('start_time')
            ->get()
            ->groupBy('day');

        return $this->success($schedules);
    }

    /**
     * Bulk create schedules.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.class_room_id' => ['required', 'uuid', 'exists:class_rooms,id'],
            'schedules.*.subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'schedules.*.teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'schedules.*.academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'schedules.*.semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'schedules.*.day' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.room' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            DB::beginTransaction();

            $created = [];
            $errors = [];

            foreach ($data['schedules'] as $index => $scheduleData) {
                $conflict = $this->checkConflict($scheduleData);
                if ($conflict) {
                    $errors[] = "Jadwal #{$index}: {$conflict}";
                    continue;
                }

                $created[] = Schedule::create($scheduleData);
            }

            if (!empty($errors) && empty($created)) {
                DB::rollBack();
                return $this->error('Gagal membuat jadwal: ' . implode('; ', $errors), 422);
            }

            DB::commit();

            $message = count($created) . ' jadwal berhasil ditambahkan';
            if (!empty($errors)) {
                $message .= '. ' . count($errors) . ' jadwal gagal: ' . implode('; ', $errors);
            }

            return $this->success(['count' => count($created)], $message, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal membuat jadwal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check for schedule conflicts.
     */
    private function checkConflict(array $data, ?string $excludeId = null): ?string
    {
        // Check teacher conflict
        $teacherConflict = Schedule::where('teacher_id', $data['teacher_id'])
            ->where('day', $data['day'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('start_time', '<=', $data['start_time'])
                            ->where('end_time', '>=', $data['end_time']);
                    });
            })
            ->exists();

        if ($teacherConflict) {
            return 'Guru sudah memiliki jadwal lain pada waktu yang sama';
        }

        // Check class conflict
        $classConflict = Schedule::where('class_room_id', $data['class_room_id'])
            ->where('day', $data['day'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('start_time', '<=', $data['start_time'])
                            ->where('end_time', '>=', $data['end_time']);
                    });
            })
            ->exists();

        if ($classConflict) {
            return 'Kelas sudah memiliki jadwal lain pada waktu yang sama';
        }

        return null;
    }
}
