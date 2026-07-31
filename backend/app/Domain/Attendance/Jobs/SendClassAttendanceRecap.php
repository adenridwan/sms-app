<?php

namespace App\Domain\Attendance\Jobs;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Services\AttendanceStatusResolver;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceAuditLog;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Mode batch notifikasi hybrid (ATTENDANCE-PLAN.md Fase 3 / §5): guru menekan
 * "Kirim Notifikasi" di halaman Absensi Harian → satu WA per siswa ke wali
 * (template check_in/check_in_late/absent sesuai status) + satu rekap kelas
 * ke Telegram default tenant. Dijalankan lewat queue karena ±30 panggilan
 * HTTP provider terlalu lambat untuk diproses di dalam request.
 */
class SendClassAttendanceRecap implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private string $tenantId,
        private string $classroomId,
        private string $date,
        private string $triggeredByUserId,
    ) {}

    public function handle(
        NotificationDispatcher $dispatcher,
        AttendanceStatusResolver $statusResolver
    ): void {
        $enrollments = StudentEnrollment::with(['student.user', 'student.guardians'])
            ->where('classroom_id', $this->classroomId)
            ->where('status', 'active')
            ->get();

        $attendances = StudentAttendance::where('classroom_id', $this->classroomId)
            ->whereDate('attendance_date', $this->date)
            ->get()
            ->keyBy('student_id');

        $dispatcher->forTenant($this->tenantId);

        $tally = ['sent' => 0, 'no_phone' => 0, 'skipped' => 0, 'failed' => 0];
        $summaryCounts = [];
        $absentNames = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $attendance = $attendances->get($student->id);

            if ($attendance) {
                $status = AttendanceStatus::tryFrom($attendance->status) ?? AttendanceStatus::Hadir;
            } else {
                $status = $statusResolver->resolveStudentStatus($student->id, $this->date, $this->tenantId);
            }

            $summaryCounts[$status->value] = ($summaryCounts[$status->value] ?? 0) + 1;
            if ($status->isAbsent()) {
                $absentNames[] = $student->user?->full_name ?? $student->nis;
            }

            $result = $dispatcher->dispatchStudentRecap(
                $student,
                $status,
                $attendance?->check_in_time?->format('H:i'),
                $attendance?->menit_keterlambatan ?? 0,
                $this->date
            );

            $tally[$result] = ($tally[$result] ?? 0) + 1;
        }

        $summary = AttendanceStatus::summaryFromRaw($summaryCounts);
        $className = Classroom::whereKey($this->classroomId)->value('name') ?? 'Kelas';

        $dispatcher->dispatchClassSummaryToTelegram($className, $this->date, $summary, $absentNames);

        // AttendanceAuditLog::log() mengambil tenant_id dari auth() — kosong di
        // konteks queue, jadi isi eksplisit di sini.
        AttendanceAuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->triggeredByUserId,
            'aksi' => 'kirim_rekap_notifikasi',
            'tabel' => 'student_attendances',
            'record_id' => null,
            'data_lama' => null,
            'data_baru' => [
                'classroom_id' => $this->classroomId,
                'date' => $this->date,
                'tally' => $tally,
                'summary' => $summary,
            ],
            'ip_address' => '0.0.0.0',
            'user_agent' => 'queue:SendClassAttendanceRecap',
        ]);
    }
}
