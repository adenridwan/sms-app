<?php

namespace App\Domain\Notification\Services;

use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim notifikasi via Email dengan SMTP per-tenant (tiap sekolah punya
 * kredensial sendiri). Pola sejajar dengan WhatsAppService/TelegramService.
 * Best-effort: kegagalan tidak boleh menggagalkan proses absensi.
 */
class EmailService
{
    private bool $configured = false;
    private ?string $fromAddress = null;
    private ?string $fromName = null;

    /** Nama mailer runtime khusus tenant (dikonfigurasi on the fly). */
    private const MAILER = 'tenant_smtp';

    /**
     * Bangun konfigurasi mailer dari setting tenant.
     */
    public function initializeForTenant(string $tenantId): self
    {
        $settings = NotificationSetting::getForTenant($tenantId);

        if (! $settings->isEmailConfigured()) {
            $this->configured = false;
            return $this;
        }

        // Set konfigurasi mailer runtime — tidak menyentuh mailer 'smtp' default.
        config(['mail.mailers.' . self::MAILER => [
            'transport' => 'smtp',
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port,
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'encryption' => $settings->smtp_encryption ?: null,
            'timeout' => 10,
        ]]);

        $this->fromAddress = $settings->email_from_address;
        $this->fromName = $settings->email_from_name ?: config('app.name');
        $this->configured = true;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->configured;
    }

    /**
     * Kirim email (body dianggap plaintext, dibungkus sederhana).
     */
    public function send(string $to, string $subject, string $body): bool
    {
        if (! $this->configured) {
            Log::debug('Email: mailer tenant belum dikonfigurasi');
            return false;
        }

        try {
            Mail::mailer(self::MAILER)->html(nl2br(e($body)), function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject)
                    ->from($this->fromAddress, $this->fromName);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Email: gagal mengirim', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Kirim notifikasi absensi (template diproses jadi isi email).
     */
    public function sendAttendanceNotification(
        string $to,
        string $subject,
        string $template,
        array $replacements
    ): bool {
        return $this->send($to, $subject, $this->parseTemplate($template, $replacements));
    }

    private function parseTemplate(string $template, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }
}
