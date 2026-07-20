<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Services\AttendanceStatusResolver;
use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAttendanceController extends ApiController
{
    public function __construct(
        private AttendanceStatusResolver $statusResolver
    ) {}

    /**
     * List teacher/employee attendances
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeAttendance::with(['user.profile', 'user.teacher'])
            ->when($request->date, fn($q, $date) => $q->forDate($date))
            ->when($request->user_id, fn($q, $id) => $q->forUser($id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->from_date, fn($q, $date) => $q->where('attendance_date', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->where('attendance_date', '<=', $date))
            // R4: guru non-admin hanya lihat barisnya sendiri, apa pun
            // parameter user_id yang dikirim — mencegah override.
            ->when(!$this->isFullAccess($request->user()), fn($q) => $q->where('user_id', $request->user()->id));

        $sortField = $request->get('sort', 'attendance_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);

        return $this->success($query->paginate($perPage));
    }

    /**
     * Get daily attendance for all teachers
     */
    public function daily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $data['date'];

        // Get all active teachers — guru non-admin hanya lihat barisnya
        // sendiri (R4), role admin-tier (Student::ALL_ACCESS_ROLES) lihat semua.
        $teachers = Teacher::with('user.profile')
            ->active()
            ->when(
                !$this->isFullAccess($request->user()),
                fn ($q) => $q->where('user_id', $request->user()->id)
            )
            ->get();

        // Get existing attendances
        $attendances = EmployeeAttendance::forDate($date)
            ->get()
            ->keyBy('user_id');

        $results = [];
        foreach ($teachers as $teacher) {
            $attendance = $attendances->get($teacher->user_id);

            $status = $attendance?->status ?? 'belum_scan';
            if (!$attendance) {
                // Resolve actual status
                $resolvedStatus = $this->statusResolver->resolveEmployeeStatus($teacher->user_id, $date);
                $status = $resolvedStatus->value;
            }

            $results[] = [
                'teacher_id' => $teacher->id,
                'user_id' => $teacher->user_id,
                'nip' => $teacher->nip,
                'name' => $teacher->user?->full_name,
                'attendance_id' => $attendance?->id,
                'status' => $status,
                'check_in_time' => $attendance?->check_in_time?->format('H:i'),
                'check_out_time' => $attendance?->check_out_time?->format('H:i'),
                'late_minutes' => $attendance?->late_minutes ?? 0,
                'notes' => $attendance?->notes,
            ];
        }

        // Calculate summary
        $summary = $this->calculateSummary($results);

        return $this->success([
            'date' => $date,
            'teachers' => $results,
            'summary' => $summary,
        ]);
    }

    /**
     * Update a teacher attendance
     */
    public function update(Request $request, EmployeeAttendance $attendance): JsonResponse
    {
        if (!$this->isFullAccess($request->user()) && $attendance->user_id !== $request->user()->id) {
            return $this->forbidden('Anda tidak memiliki akses ke absensi guru ini.');
        }

        $data = $request->validate([
            'status' => ['sometimes', 'in:present,absent,late,sick,permitted,on_duty,work_from_home'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $attendance->update($data);
        $attendance->load(['user.profile']);

        return $this->success($attendance, 'Absensi berhasil diperbarui');
    }

    /**
     * Get attendance summary for teachers
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $query = EmployeeAttendance::query()
            ->betweenDates($data['from_date'], $data['to_date'])
            ->when($data['user_id'] ?? null, fn($q, $id) => $q->forUser($id))
            ->when(!$this->isFullAccess($request->user()), fn($q) => $q->where('user_id', $request->user()->id));

        $stats = $query->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $lateStats = $query->where('late_minutes', '>', 0)
            ->select(
                DB::raw('COUNT(*) as total_late'),
                DB::raw('SUM(late_minutes) as total_minutes'),
                DB::raw('AVG(late_minutes) as avg_minutes')
            )
            ->first();

        $total = array_sum($stats);

        return $this->success([
            'period' => [
                'from' => $data['from_date'],
                'to' => $data['to_date'],
            ],
            'total_records' => $total,
            'by_status' => [
                'present' => $stats['present'] ?? 0,
                'absent' => $stats['absent'] ?? 0,
                'late' => $stats['late'] ?? 0,
                'sick' => $stats['sick'] ?? 0,
                'permitted' => $stats['permitted'] ?? 0,
                'on_duty' => $stats['on_duty'] ?? 0,
                'work_from_home' => $stats['work_from_home'] ?? 0,
            ],
            'percentages' => [
                'present' => $total > 0 ? round((($stats['present'] ?? 0) / $total) * 100, 2) : 0,
                'absent' => $total > 0 ? round((($stats['absent'] ?? 0) / $total) * 100, 2) : 0,
            ],
            'lateness' => [
                'total_late' => $lateStats->total_late ?? 0,
                'total_minutes' => $lateStats->total_minutes ?? 0,
                'avg_minutes' => round($lateStats->avg_minutes ?? 0, 1),
            ],
        ]);
    }

    /**
     * Role admin-tier (Student::ALL_ACCESS_ROLES — dipakai ulang di sini
     * karena ini daftar kanonik "boleh lihat/edit semua orang" R4 di seluruh
     * modul absensi, bukan konsep khusus siswa) melihat & mengedit absensi
     * semua guru; selain itu (guru/wali_kelas biasa) hanya barisnya sendiri.
     */
    private function isFullAccess(User $user): bool
    {
        return $user->getRoleNames()->intersect(Student::ALL_ACCESS_ROLES)->isNotEmpty();
    }

    /**
     * Calculate summary from results
     */
    private function calculateSummary(array $results): array
    {
        $summary = [
            'total' => count($results),
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'sick' => 0,
            'permitted' => 0,
            'belum_scan' => 0,
        ];

        foreach ($results as $result) {
            $status = $result['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        return $summary;
    }
}
