<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Enums\LeaveStatus;
use App\Domain\Attendance\Enums\LeaveType;
use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceAuditLog;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    /**
     * Approve a leave permission and upsert attendance records
     */
    public function approve(LeavePermission $permission, string $approvedById): array
    {
        if (!$permission->status->isPending()) {
            return [
                'success' => false,
                'message' => 'Izin sudah diproses sebelumnya',
            ];
        }

        DB::beginTransaction();
        try {
            $oldData = $permission->toArray();

            // Update permission status
            $permission->update([
                'status' => LeaveStatus::Approved,
                'approved_by' => $approvedById,
                'approved_at' => now(),
            ]);

            // Upsert attendance records for each date in the range
            $dates = $permission->getCoveredDates();
            $upsertedCount = 0;

            if ($permission->isStudentLeave()) {
                $upsertedCount = $this->upsertStudentAttendances($permission, $dates);
            } else {
                $upsertedCount = $this->upsertEmployeeAttendances($permission, $dates);
            }

            // Log the action
            AttendanceAuditLog::log(
                'approve_izin',
                'leave_permissions',
                $permission->id,
                $oldData,
                $permission->fresh()->toArray(),
                $permission->tenant_id
            );

            DB::commit();

            return [
                'success' => true,
                'message' => "Izin disetujui. {$upsertedCount} record presensi dibuat/diperbarui.",
                'data' => [
                    'permission_id' => $permission->id,
                    'dates_affected' => $dates,
                    'records_upserted' => $upsertedCount,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Gagal menyetujui izin: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reject a leave permission
     */
    public function reject(LeavePermission $permission, string $rejectedById, string $reason): array
    {
        if (!$permission->status->isPending()) {
            return [
                'success' => false,
                'message' => 'Izin sudah diproses sebelumnya',
            ];
        }

        $oldData = $permission->toArray();

        $permission->update([
            'status' => LeaveStatus::Rejected,
            'approved_by' => $rejectedById,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AttendanceAuditLog::log(
            'reject_izin',
            'leave_permissions',
            $permission->id,
            $oldData,
            $permission->fresh()->toArray(),
            $permission->tenant_id
        );

        return [
            'success' => true,
            'message' => 'Izin ditolak.',
            'data' => $permission->fresh(),
        ];
    }

    /**
     * Create a new leave permission
     */
    public function createLeavePermission(array $data): LeavePermission
    {
        return LeavePermission::create([
            'tenant_id' => $data['tenant_id'],
            'student_id' => $data['student_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'tipe_izin' => $data['tipe_izin'],
            'alasan' => $data['alasan'] ?? null,
            'bukti' => $data['bukti'] ?? null,
            'status' => LeaveStatus::Pending,
        ]);
    }

    /**
     * Upsert student attendance records for leave dates
     */
    private function upsertStudentAttendances(LeavePermission $permission, array $dates): int
    {
        $student = $permission->student;
        if (!$student) {
            return 0;
        }

        $enrollment = $student->currentEnrollment();
        $attendanceStatus = $permission->tipe_izin->toAttendanceStatus();
        $count = 0;

        foreach ($dates as $date) {
            StudentAttendance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'attendance_date' => $date,
                ],
                [
                    'tenant_id' => $permission->tenant_id,
                    'classroom_id' => $enrollment?->classroom_id,
                    'academic_year_id' => $enrollment?->academic_year_id,
                    'semester_id' => $enrollment?->academicYear?->activeSemester?->id,
                    'status' => $attendanceStatus->value,
                    'notes' => "Izin: {$permission->alasan}",
                    'menit_keterlambatan' => 0,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Upsert employee attendance records for leave dates
     */
    private function upsertEmployeeAttendances(LeavePermission $permission, array $dates): int
    {
        $teacher = $permission->teacher;
        if (!$teacher) {
            return 0;
        }

        $status = $permission->tipe_izin === LeaveType::Sakit ? 'sick' : 'permitted';
        $count = 0;

        foreach ($dates as $date) {
            EmployeeAttendance::updateOrCreate(
                [
                    'user_id' => $teacher->user_id,
                    'attendance_date' => $date,
                ],
                [
                    'tenant_id' => $permission->tenant_id,
                    'status' => $status,
                    'notes' => "Izin: {$permission->alasan}",
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Check if a date range overlaps with existing leave
     */
    public function hasOverlappingLeave(
        ?string $studentId,
        ?string $teacherId,
        string $startDate,
        string $endDate
    ): bool {
        $query = LeavePermission::where('status', LeaveStatus::Pending)
            ->orWhere('status', LeaveStatus::Approved);

        if ($studentId) {
            $query->where('student_id', $studentId);
        } else if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        } else {
            return false;
        }

        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('tanggal_mulai', [$startDate, $endDate])
                ->orWhereBetween('tanggal_selesai', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('tanggal_mulai', '<=', $startDate)
                        ->where('tanggal_selesai', '>=', $endDate);
                });
        })->exists();
    }
}
