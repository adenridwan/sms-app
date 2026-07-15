<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Enums\ScanType;
use App\Domain\Attendance\Events\StudentCheckedIn;
use App\Domain\Attendance\Events\StudentCheckedOut;
use App\Domain\Attendance\Events\TeacherCheckedIn;
use App\Domain\Attendance\Events\TeacherCheckedOut;
use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceAuditLog;
use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceSetting;
use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\Holiday;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceScanService
{
    public function __construct(
        private LateCalculationService $lateCalculationService,
        private AttendanceStatusResolver $statusResolver
    ) {}

    /**
     * Process a scan (check-in or check-out)
     */
    public function processScan(
        string $code,
        ScanType $scanType,
        ?array $location = null
    ): array {
        // Check if today is a holiday
        $today = now()->toDateString();
        if (Holiday::isHoliday($today)) {
            return $this->errorResponse('Hari ini adalah hari libur');
        }

        // Try to identify as student first, then teacher
        $student = Student::findByCode($code);
        if ($student) {
            return $this->processStudentScan($student, $scanType, $location);
        }

        $teacher = Teacher::findByCode($code);
        if ($teacher) {
            return $this->processTeacherScan($teacher, $scanType, $location);
        }

        return $this->errorResponse('Kode tidak ditemukan');
    }

    /**
     * Process student scan
     */
    private function processStudentScan(
        Student $student,
        ScanType $scanType,
        ?array $location
    ): array {
        $today = now()->toDateString();
        $now = now();

        // Get current enrollment for classroom info
        $enrollment = $student->currentEnrollment();
        if (!$enrollment) {
            return $this->errorResponse('Siswa tidak terdaftar di kelas manapun');
        }

        // Get or check existing attendance
        $attendance = StudentAttendance::getForStudentOnDate($student->id, $today);

        if ($scanType->isCheckIn()) {
            return $this->processStudentCheckIn($student, $enrollment, $attendance, $location, $now);
        }

        return $this->processStudentCheckOut($student, $attendance, $now);
    }

    /**
     * Process student check-in
     */
    private function processStudentCheckIn(
        Student $student,
        $enrollment,
        ?StudentAttendance $existingAttendance,
        ?array $location,
        Carbon $now
    ): array {
        // Check if already checked in
        if ($existingAttendance && $existingAttendance->check_in_time) {
            return $this->errorResponse(
                'Siswa sudah melakukan absen masuk pada ' .
                $existingAttendance->check_in_time->format('H:i')
            );
        }

        // Calculate lateness
        $lateMinutes = $this->lateCalculationService->calculate($now, $student->tenant_id);
        $lateInfo = $this->lateCalculationService->getLateCategoryInfo($lateMinutes);

        DB::beginTransaction();
        try {
            // Create or update attendance record
            $attendance = StudentAttendance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'attendance_date' => $now->toDateString(),
                ],
                [
                    'tenant_id' => $student->tenant_id,
                    'classroom_id' => $enrollment->classroom_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'semester_id' => $enrollment->academicYear?->activeSemester?->id,
                    'status' => AttendanceStatus::Hadir->value,
                    'check_in_time' => $now->toTimeString(),
                    'menit_keterlambatan' => $lateMinutes,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                ]
            );

            // Add violation points if late
            if ($lateMinutes > 0) {
                $student->addViolationPoints($lateMinutes);
            }

            // Log the action
            AttendanceAuditLog::log(
                'scan_masuk_siswa',
                'student_attendances',
                $attendance->id,
                null,
                $attendance->toArray()
            );

            DB::commit();

            // Dispatch event for notification (best-effort)
            try {
                event(new StudentCheckedIn($student, $attendance, $lateMinutes));
            } catch (\Exception $e) {
                // Don't fail the scan if notification fails
                \Log::warning('Failed to dispatch StudentCheckedIn event', [
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->successResponse([
                'type' => 'student',
                'action' => 'check_in',
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'name' => $student->user?->full_name,
                    'classroom' => $enrollment->classroom?->name,
                ],
                'time' => $now->format('H:i:s'),
                'late' => $lateMinutes > 0,
                'late_minutes' => $lateMinutes,
                'late_info' => $lateInfo,
                'total_violation_points' => $student->fresh()->poin_pelanggaran,
            ], $lateMinutes > 0
                ? "Terlambat {$lateMinutes} menit"
                : 'Absen masuk berhasil'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menyimpan absensi: ' . $e->getMessage());
        }
    }

    /**
     * Process student check-out
     */
    private function processStudentCheckOut(
        Student $student,
        ?StudentAttendance $attendance,
        Carbon $now
    ): array {
        if (!$attendance) {
            return $this->errorResponse('Siswa belum melakukan absen masuk');
        }

        if ($attendance->check_out_time) {
            return $this->errorResponse(
                'Siswa sudah melakukan absen pulang pada ' .
                $attendance->check_out_time->format('H:i')
            );
        }

        $oldData = $attendance->toArray();

        $attendance->update([
            'check_out_time' => $now->toTimeString(),
        ]);

        // Log the action
        AttendanceAuditLog::log(
            'scan_pulang_siswa',
            'student_attendances',
            $attendance->id,
            $oldData,
            $attendance->fresh()->toArray()
        );

        // Dispatch event for notification
        try {
            event(new StudentCheckedOut($student, $attendance->fresh()));
        } catch (\Exception $e) {
            \Log::warning('Failed to dispatch StudentCheckedOut event', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successResponse([
            'type' => 'student',
            'action' => 'check_out',
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
            ],
            'time' => $now->format('H:i:s'),
            'check_in_time' => $attendance->check_in_time?->format('H:i:s'),
        ], 'Absen pulang berhasil');
    }

    /**
     * Process teacher scan
     */
    private function processTeacherScan(
        Teacher $teacher,
        ScanType $scanType,
        ?array $location
    ): array {
        $today = now()->toDateString();
        $now = now();

        $attendance = EmployeeAttendance::getForUserOnDate($teacher->user_id, $today);

        if ($scanType->isCheckIn()) {
            return $this->processTeacherCheckIn($teacher, $attendance, $location, $now);
        }

        return $this->processTeacherCheckOut($teacher, $attendance, $location, $now);
    }

    /**
     * Process teacher check-in
     */
    private function processTeacherCheckIn(
        Teacher $teacher,
        ?EmployeeAttendance $existingAttendance,
        ?array $location,
        Carbon $now
    ): array {
        if ($existingAttendance && $existingAttendance->check_in_time) {
            return $this->errorResponse(
                'Guru sudah melakukan absen masuk pada ' .
                $existingAttendance->check_in_time->format('H:i')
            );
        }

        $lateMinutes = $this->lateCalculationService->calculate($now, $teacher->tenant_id);

        $attendance = EmployeeAttendance::updateOrCreate(
            [
                'user_id' => $teacher->user_id,
                'attendance_date' => $now->toDateString(),
            ],
            [
                'tenant_id' => $teacher->tenant_id,
                'status' => 'present',
                'check_in_time' => $now->toTimeString(),
                'late_minutes' => $lateMinutes,
                'check_in_latitude' => $location['latitude'] ?? null,
                'check_in_longitude' => $location['longitude'] ?? null,
            ]
        );

        AttendanceAuditLog::log(
            'scan_masuk_guru',
            'employee_attendances',
            $attendance->id,
            null,
            $attendance->toArray()
        );

        try {
            event(new TeacherCheckedIn($teacher, $attendance, $lateMinutes));
        } catch (\Exception $e) {
            \Log::warning('Failed to dispatch TeacherCheckedIn event', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successResponse([
            'type' => 'teacher',
            'action' => 'check_in',
            'teacher' => [
                'id' => $teacher->id,
                'nip' => $teacher->nip,
                'name' => $teacher->user?->full_name,
            ],
            'time' => $now->format('H:i:s'),
            'late' => $lateMinutes > 0,
            'late_minutes' => $lateMinutes,
        ], $lateMinutes > 0
            ? "Terlambat {$lateMinutes} menit"
            : 'Absen masuk berhasil'
        );
    }

    /**
     * Process teacher check-out
     */
    private function processTeacherCheckOut(
        Teacher $teacher,
        ?EmployeeAttendance $attendance,
        ?array $location,
        Carbon $now
    ): array {
        if (!$attendance) {
            return $this->errorResponse('Guru belum melakukan absen masuk');
        }

        if ($attendance->check_out_time) {
            return $this->errorResponse(
                'Guru sudah melakukan absen pulang pada ' .
                $attendance->check_out_time->format('H:i')
            );
        }

        $oldData = $attendance->toArray();

        $attendance->update([
            'check_out_time' => $now->toTimeString(),
            'check_out_latitude' => $location['latitude'] ?? null,
            'check_out_longitude' => $location['longitude'] ?? null,
        ]);

        AttendanceAuditLog::log(
            'scan_pulang_guru',
            'employee_attendances',
            $attendance->id,
            $oldData,
            $attendance->fresh()->toArray()
        );

        try {
            event(new TeacherCheckedOut($teacher, $attendance->fresh()));
        } catch (\Exception $e) {
            \Log::warning('Failed to dispatch TeacherCheckedOut event', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successResponse([
            'type' => 'teacher',
            'action' => 'check_out',
            'teacher' => [
                'id' => $teacher->id,
                'nip' => $teacher->nip,
                'name' => $teacher->user?->full_name,
            ],
            'time' => $now->format('H:i:s'),
            'check_in_time' => $attendance->check_in_time?->format('H:i:s'),
        ], 'Absen pulang berhasil');
    }

    /**
     * Get bootstrap data for scanner app
     */
    public function getBootstrapData(string $tenantId): array
    {
        $settings = AttendanceSetting::getForTenant($tenantId);

        // Get today's date info
        $today = now()->toDateString();
        $isHoliday = Holiday::isHoliday($today);
        $holidayInfo = $isHoliday
            ? Holiday::where('tanggal', $today)->first()
            : null;

        return [
            'today' => $today,
            'current_time' => now()->format('H:i:s'),
            'is_holiday' => $isHoliday,
            'holiday_info' => $holidayInfo ? [
                'keterangan' => $holidayInfo->keterangan,
            ] : null,
            'settings' => [
                'check_in_start' => $settings->check_in_start,
                'check_in_end' => $settings->check_in_end,
                'check_out_start' => $settings->check_out_start,
                'check_out_end' => $settings->check_out_end,
                'late_tolerance_minutes' => $settings->late_tolerance_minutes,
                'require_location' => $settings->require_location,
            ],
            'check_in_deadline' => $settings->getCheckInDeadline()->format('H:i:s'),
        ];
    }

    private function successResponse(array $data, string $message = 'Sukses'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];
    }
}
