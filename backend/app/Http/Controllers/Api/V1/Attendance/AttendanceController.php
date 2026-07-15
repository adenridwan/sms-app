<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Api\ApiController;
use App\Models\Attendance\Attendance;
use App\Models\MasterData\ClassRoom;
use App\Models\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['student.user', 'classRoom'])
            ->when($request->date, fn($q, $date) => $q->whereDate('date', $date))
            ->when($request->class_id, fn($q, $classId) => $q->where('class_room_id', $classId))
            ->when($request->student_id, fn($q, $studentId) => $q->where('student_id', $studentId))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->from_date, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->whereDate('date', '<=', $date));

        $sortField = $request->get('sort', 'date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $attendances = $query->paginate($perPage);

        return $this->success($attendances);
    }

    /**
     * Store attendance (single or bulk).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'class_room_id' => ['required', 'uuid', 'exists:class_rooms,id'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'attendances.*.status' => ['required', 'in:present,absent,late,sick,permission'],
            'attendances.*.check_in' => ['nullable', 'date_format:H:i'],
            'attendances.*.check_out' => ['nullable', 'date_format:H:i'],
            'attendances.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $created = [];
            foreach ($data['attendances'] as $attendance) {
                $created[] = Attendance::updateOrCreate(
                    [
                        'student_id' => $attendance['student_id'],
                        'class_room_id' => $data['class_room_id'],
                        'date' => $data['date'],
                    ],
                    [
                        'status' => $attendance['status'],
                        'check_in' => $attendance['check_in'] ?? null,
                        'check_out' => $attendance['check_out'] ?? null,
                        'notes' => $attendance['notes'] ?? null,
                    ]
                );
            }

            DB::commit();

            return $this->success(
                ['count' => count($created)],
                'Absensi berhasil disimpan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menyimpan absensi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load(['student.user', 'classRoom']);

        return $this->success($attendance);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:present,absent,late,sick,permission'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance->update($data);
        $attendance->load(['student.user', 'classRoom']);

        return $this->success($attendance, 'Absensi berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return $this->success(null, 'Absensi berhasil dihapus');
    }

    /**
     * Get attendance by class and date.
     */
    public function byClass(Request $request): JsonResponse
    {
        $request->validate([
            'class_room_id' => ['required', 'uuid', 'exists:class_rooms,id'],
            'date' => ['required', 'date'],
        ]);

        $classRoom = ClassRoom::with(['students.user'])->findOrFail($request->class_room_id);

        $attendances = Attendance::where('class_room_id', $request->class_room_id)
            ->whereDate('date', $request->date)
            ->get()
            ->keyBy('student_id');

        $result = $classRoom->students->map(function ($student) use ($attendances, $request) {
            $attendance = $attendances->get($student->id);

            return [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user->full_name,
                'attendance_id' => $attendance?->id,
                'status' => $attendance?->status ?? null,
                'check_in' => $attendance?->check_in,
                'check_out' => $attendance?->check_out,
                'notes' => $attendance?->notes,
            ];
        });

        return $this->success([
            'class_room' => [
                'id' => $classRoom->id,
                'name' => $classRoom->name,
            ],
            'date' => $request->date,
            'students' => $result,
        ]);
    }

    /**
     * Get attendance summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'class_room_id' => ['nullable', 'uuid', 'exists:class_rooms,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
        ]);

        $query = Attendance::query()
            ->whereBetween('date', [$request->from_date, $request->to_date])
            ->when($request->class_room_id, fn($q) => $q->where('class_room_id', $request->class_room_id))
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id));

        $summary = $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $total = array_sum($summary);

        return $this->success([
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'total' => $total,
            'present' => $summary['present'] ?? 0,
            'absent' => $summary['absent'] ?? 0,
            'late' => $summary['late'] ?? 0,
            'sick' => $summary['sick'] ?? 0,
            'permission' => $summary['permission'] ?? 0,
            'percentage' => [
                'present' => $total > 0 ? round((($summary['present'] ?? 0) / $total) * 100, 2) : 0,
                'absent' => $total > 0 ? round((($summary['absent'] ?? 0) / $total) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * Get student attendance history.
     */
    public function studentHistory(Student $student, Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $query = Attendance::where('student_id', $student->id)
            ->with('classRoom')
            ->when($request->from_date, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderBy('date', 'desc');

        $perPage = $request->get('per_page', 30);
        $attendances = $query->paginate($perPage);

        return $this->success($attendances);
    }
}
