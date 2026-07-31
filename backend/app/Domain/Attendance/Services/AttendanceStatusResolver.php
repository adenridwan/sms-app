<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceSetting;
use App\Infrastructure\Persistence\Eloquent\Attendance\Holiday;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use Carbon\Carbon;

class AttendanceStatusResolver
{
    /**
     * Resolve attendance status for a student on a given date
     * Returns "BelumScan" if before checkout time and no record
     * Returns "Alfa" if after checkout time and no record
     */
    public function resolveStudentStatus(
        string $studentId,
        string $date,
        ?string $tenantId = null
    ): AttendanceStatus {
        // Check if there's an existing attendance record
        $attendance = StudentAttendance::getForStudentOnDate($studentId, $date);

        if ($attendance) {
            return AttendanceStatus::tryFrom($attendance->status) ?? AttendanceStatus::Hadir;
        }

        // No record exists - determine if "Belum Scan" or "Alfa"
        return $this->determineNoRecordStatus($date, $tenantId);
    }

    /**
     * Resolve attendance status for a teacher/employee on a given date
     */
    public function resolveEmployeeStatus(
        string $userId,
        string $date,
        ?string $tenantId = null
    ): AttendanceStatus {
        $attendance = EmployeeAttendance::getForUserOnDate($userId, $date);

        if ($attendance) {
            return AttendanceStatus::tryFrom($attendance->status) ?? AttendanceStatus::Hadir;
        }

        return $this->determineNoRecordStatus($date, $tenantId);
    }

    /**
     * Determine status when no attendance record exists
     */
    private function determineNoRecordStatus(string $date, ?string $tenantId = null): AttendanceStatus
    {
        $targetDate = Carbon::parse($date);
        $today = Carbon::today();

        // If the date is in the future, it's "Belum Scan"
        if ($targetDate->isAfter($today)) {
            return AttendanceStatus::BelumScan;
        }

        // If it's a past date, it's "Alfa" (absent without notice)
        if ($targetDate->isBefore($today)) {
            return AttendanceStatus::Alfa;
        }

        // It's today - check against checkout time
        $settings = $tenantId
            ? AttendanceSetting::where('tenant_id', $tenantId)->first()
            : AttendanceSetting::first();

        if (!$settings) {
            // Default: after 14:00 is considered Alfa
            return now()->hour >= 14 ? AttendanceStatus::Alfa : AttendanceStatus::BelumScan;
        }

        // Check if we're past the standard checkout time
        $checkoutTime = $settings->getCheckOutStandard();

        if (now()->greaterThanOrEqualTo($checkoutTime)) {
            return AttendanceStatus::Alfa;
        }

        return AttendanceStatus::BelumScan;
    }

    /**
     * Check if a date is a holiday
     */
    public function isHoliday(string $date): bool
    {
        return Holiday::isHoliday($date);
    }

    /**
     * Check if a date is a working day (not weekend, not holiday)
     */
    public function isWorkingDay(string $date, ?string $tenantId = null): bool
    {
        $targetDate = Carbon::parse($date);

        // Check if it's a holiday
        if ($this->isHoliday($date)) {
            return false;
        }

        // Check attendance settings for working days
        $settings = $tenantId
            ? AttendanceSetting::where('tenant_id', $tenantId)->first()
            : AttendanceSetting::first();

        if ($settings) {
            return $settings->isWorkingDay($targetDate->dayOfWeek);
        }

        // Default: Monday to Friday are working days
        return !$targetDate->isWeekend();
    }

    /**
     * Get attendance statistics for a classroom on a date
     */
    public function getClassroomStats(string $classroomId, string $date): array
    {
        $attendances = StudentAttendance::where('classroom_id', $classroomId)
            ->whereDate('attendance_date', $date)
            ->get();

        $rawCounts = [];
        $totalLateMinutes = 0;

        foreach ($attendances as $attendance) {
            $rawCounts[$attendance->status] = ($rawCounts[$attendance->status] ?? 0) + 1;
            $totalLateMinutes += $attendance->menit_keterlambatan ?? 0;
        }

        $stats = AttendanceStatus::summaryFromRaw($rawCounts);
        $stats['belum_scan'] = 0;
        $stats['total_late_minutes'] = $totalLateMinutes;

        return $stats;
    }
}
