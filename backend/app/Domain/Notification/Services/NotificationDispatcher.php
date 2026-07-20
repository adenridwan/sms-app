<?php

namespace App\Domain\Notification\Services;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    private WhatsAppService $whatsAppService;
    private TelegramService $telegramService;

    public function __construct(
        WhatsAppService $whatsAppService,
        TelegramService $telegramService
    ) {
        $this->whatsAppService = $whatsAppService;
        $this->telegramService = $telegramService;
    }

    /**
     * Initialize services for a tenant
     */
    public function forTenant(string $tenantId): self
    {
        $this->whatsAppService->initializeForTenant($tenantId);
        $this->telegramService->initializeForTenant($tenantId);

        return $this;
    }

    /**
     * Dispatch student check-in notification
     */
    public function dispatchStudentCheckIn(
        Student $student,
        string $time,
        int $lateMinutes = 0
    ): void {
        $settings = NotificationSetting::getForTenant($student->tenant_id);

        if (!$settings->notify_check_in && $lateMinutes === 0) {
            return;
        }

        if ($lateMinutes > 0 && !$settings->notify_late) {
            return;
        }

        $this->forTenant($student->tenant_id);

        $templateKey = $lateMinutes > 0 ? 'check_in_late' : 'check_in';
        $template = $settings->getTemplate($templateKey);

        $replacements = [
            'nama' => $student->user?->full_name ?? 'Siswa',
            'jenis' => 'Siswa',
            'waktu' => $time,
            'menit' => (string) $lateMinutes,
            'tanggal' => now()->format('d/m/Y'),
        ];

        // Try to get guardian phone
        $guardian = $student->primaryGuardian();
        if ($guardian && $guardian->phone) {
            $this->sendWhatsApp($guardian->phone, $template, $replacements);
        }

        // Also send to Telegram if configured
        $this->sendToTelegramDefault($template, $replacements);
    }

    /**
     * Dispatch student check-out notification
     */
    public function dispatchStudentCheckOut(Student $student, string $time): void
    {
        $settings = NotificationSetting::getForTenant($student->tenant_id);

        if (!$settings->notify_check_out) {
            return;
        }

        $this->forTenant($student->tenant_id);

        $template = $settings->getTemplate('check_out');
        $replacements = [
            'nama' => $student->user?->full_name ?? 'Siswa',
            'jenis' => 'Siswa',
            'waktu' => $time,
            'tanggal' => now()->format('d/m/Y'),
        ];

        $guardian = $student->primaryGuardian();
        if ($guardian && $guardian->phone) {
            $this->sendWhatsApp($guardian->phone, $template, $replacements);
        }

        $this->sendToTelegramDefault($template, $replacements);
    }

    /**
     * Dispatch teacher check-in notification
     */
    public function dispatchTeacherCheckIn(
        Teacher $teacher,
        string $time,
        int $lateMinutes = 0
    ): void {
        $settings = NotificationSetting::getForTenant($teacher->tenant_id);

        if (!$settings->notify_check_in && $lateMinutes === 0) {
            return;
        }

        $this->forTenant($teacher->tenant_id);

        $templateKey = $lateMinutes > 0 ? 'check_in_late' : 'check_in';
        $template = $settings->getTemplate($templateKey);

        $replacements = [
            'nama' => $teacher->user?->full_name ?? 'Guru',
            'jenis' => 'Guru',
            'waktu' => $time,
            'menit' => (string) $lateMinutes,
            'tanggal' => now()->format('d/m/Y'),
        ];

        // Send to teacher's phone if available
        if ($teacher->no_hp) {
            $this->sendWhatsApp($teacher->no_hp, $template, $replacements);
        }

        $this->sendToTelegramDefault($template, $replacements);
    }

    /**
     * Dispatch teacher check-out notification
     */
    public function dispatchTeacherCheckOut(Teacher $teacher, string $time): void
    {
        $settings = NotificationSetting::getForTenant($teacher->tenant_id);

        if (!$settings->notify_check_out) {
            return;
        }

        $this->forTenant($teacher->tenant_id);

        $template = $settings->getTemplate('check_out');
        $replacements = [
            'nama' => $teacher->user?->full_name ?? 'Guru',
            'jenis' => 'Guru',
            'waktu' => $time,
            'tanggal' => now()->format('d/m/Y'),
        ];

        if ($teacher->no_hp) {
            $this->sendWhatsApp($teacher->no_hp, $template, $replacements);
        }

        $this->sendToTelegramDefault($template, $replacements);
    }

    /**
     * Dispatch leave approved notification
     */
    public function dispatchLeaveApproved(
        string $tenantId,
        string $name,
        string $type,
        string $leaveType,
        string $startDate,
        string $endDate,
        ?string $phone = null
    ): void {
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->notify_leave_approved) {
            return;
        }

        $this->forTenant($tenantId);

        $template = $settings->getTemplate('leave_approved');
        $replacements = [
            'nama' => $name,
            'jenis' => $type,
            'tipe_izin' => $leaveType,
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => $endDate,
        ];

        if ($phone) {
            $this->sendWhatsApp($phone, $template, $replacements);
        }

        $this->sendToTelegramDefault($template, $replacements);
    }

    /**
     * Dispatch leave rejected notification
     */
    public function dispatchLeaveRejected(
        string $tenantId,
        string $name,
        string $type,
        string $leaveType,
        string $startDate,
        string $endDate,
        string $reason,
        ?string $phone = null
    ): void {
        $this->forTenant($tenantId);

        $settings = NotificationSetting::getForTenant($tenantId);
        $template = $settings->getTemplate('leave_rejected');
        $replacements = [
            'nama' => $name,
            'jenis' => $type,
            'tipe_izin' => $leaveType,
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => $endDate,
            'alasan' => $reason,
        ];

        if ($phone) {
            $this->sendWhatsApp($phone, $template, $replacements);
        }

        $this->sendToTelegramDefault($template, $replacements);
    }

    /**
     * Kirim satu pesan rekap harian ke wali murid (mode batch, Fase 3
     * ATTENDANCE-PLAN.md §5) — WhatsApp saja, TANPA Telegram per siswa
     * (rekap kelas ke Telegram dikirim sekali lewat
     * dispatchClassSummaryToTelegram, bukan 30x per siswa).
     *
     * Pemanggil wajib sudah memanggil forTenant(). Mengembalikan hasil per
     * siswa supaya job batch bisa merekap: 'sent'|'no_phone'|'skipped'|'failed'.
     */
    public function dispatchStudentRecap(
        Student $student,
        AttendanceStatus $status,
        ?string $checkInTime,
        int $lateMinutes,
        string $date
    ): string {
        $settings = NotificationSetting::getForTenant($student->tenant_id);

        // Sakit/izin sudah dinotifikasi saat approval izin; BelumScan berarti
        // hari belum selesai — dua-duanya bukan urusan rekap.
        if ($status->isExcused() || $status === AttendanceStatus::BelumScan) {
            return 'skipped';
        }

        if ($status->isAbsent()) {
            if (!$settings->notify_absent) {
                return 'skipped';
            }
            $templateKey = 'absent';
        } elseif ($lateMinutes > 0) {
            if (!$settings->notify_late) {
                return 'skipped';
            }
            $templateKey = 'check_in_late';
        } else {
            if (!$settings->notify_check_in) {
                return 'skipped';
            }
            $templateKey = 'check_in';
        }

        $guardian = $student->primaryGuardian();
        if (!$guardian || !$guardian->phone) {
            return 'no_phone';
        }

        if (!$this->whatsAppService->isAvailable()) {
            return 'failed';
        }

        $replacements = [
            'nama' => $student->user?->full_name ?? 'Siswa',
            'jenis' => 'Siswa',
            'waktu' => $checkInTime ?? '-',
            'menit' => (string) $lateMinutes,
            'tanggal' => \Carbon\Carbon::parse($date)->format('d/m/Y'),
        ];

        try {
            $sent = $this->whatsAppService->sendAttendanceNotification(
                $guardian->phone,
                $settings->getTemplate($templateKey),
                $replacements
            );

            return $sent ? 'sent' : 'failed';
        } catch (\Exception $e) {
            Log::warning('NotificationDispatcher: recap WhatsApp send failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * Kirim satu pesan rekap kelas ke chat Telegram default tenant.
     * Pemanggil wajib sudah memanggil forTenant().
     */
    public function dispatchClassSummaryToTelegram(
        string $className,
        string $date,
        array $summary,
        array $absentNames
    ): void {
        if (!$this->telegramService->isAvailable()) {
            return;
        }

        $lines = [
            "Rekap Absensi {$className} — " . \Carbon\Carbon::parse($date)->format('d/m/Y'),
            'Hadir: ' . ($summary['hadir'] ?? 0),
            'Sakit: ' . ($summary['sakit'] ?? 0),
            'Izin: ' . ($summary['izin'] ?? 0),
            'Alfa: ' . ($summary['alfa'] ?? 0),
        ];

        if (!empty($absentNames)) {
            $lines[] = 'Siswa alfa: ' . implode(', ', $absentNames);
        }

        try {
            $this->telegramService->sendToDefault(implode("\n", $lines));
        } catch (\Exception $e) {
            Log::warning('NotificationDispatcher: class summary Telegram send failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send WhatsApp message (best-effort)
     */
    private function sendWhatsApp(string $phone, string $template, array $replacements): void
    {
        if (!$this->whatsAppService->isAvailable()) {
            return;
        }

        try {
            $this->whatsAppService->sendAttendanceNotification($phone, $template, $replacements);
        } catch (\Exception $e) {
            Log::warning('NotificationDispatcher: WhatsApp send failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send to Telegram default chat (best-effort)
     */
    private function sendToTelegramDefault(string $template, array $replacements): void
    {
        if (!$this->telegramService->isAvailable()) {
            return;
        }

        try {
            $message = $this->parseTemplate($template, $replacements);
            $this->telegramService->sendToDefault($message);
        } catch (\Exception $e) {
            Log::warning('NotificationDispatcher: Telegram send failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse template with replacements
     */
    private function parseTemplate(string $template, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }
}
