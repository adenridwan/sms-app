<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'notification_settings';

    protected $fillable = [
        'tenant_id',
        'wa_enabled',
        'wa_provider',
        'wa_api_key',
        'wa_sender_number',
        'telegram_enabled',
        'telegram_bot_token',
        'telegram_default_chat_id',
        'templates',
        'notify_check_in',
        'notify_check_out',
        'notify_late',
        'notify_absent',
        'notify_leave_approved',
    ];

    protected $hidden = [
        'wa_api_key',
        'telegram_bot_token',
    ];

    protected function casts(): array
    {
        return [
            'wa_enabled' => 'boolean',
            'telegram_enabled' => 'boolean',
            'templates' => 'array',
            'notify_check_in' => 'boolean',
            'notify_check_out' => 'boolean',
            'notify_late' => 'boolean',
            'notify_absent' => 'boolean',
            'notify_leave_approved' => 'boolean',
        ];
    }

    /**
     * Get default templates
     */
    public static function getDefaultTemplates(): array
    {
        return [
            'check_in' => 'Assalamu\'alaikum, {nama} ({jenis}) telah hadir di sekolah pada pukul {waktu}. Terima kasih.',
            'check_in_late' => 'Assalamu\'alaikum, {nama} ({jenis}) terlambat {menit} menit. Hadir pada pukul {waktu}.',
            'check_out' => 'Assalamu\'alaikum, {nama} ({jenis}) telah pulang dari sekolah pada pukul {waktu}. Terima kasih.',
            'absent' => 'Assalamu\'alaikum, {nama} ({jenis}) tidak hadir pada tanggal {tanggal}. Mohon konfirmasi.',
            'leave_approved' => 'Assalamu\'alaikum, izin {tipe_izin} untuk {nama} tanggal {tanggal_mulai} s/d {tanggal_selesai} telah disetujui.',
            'leave_rejected' => 'Assalamu\'alaikum, izin {tipe_izin} untuk {nama} tanggal {tanggal_mulai} s/d {tanggal_selesai} ditolak. Alasan: {alasan}',
        ];
    }

    /**
     * Get a specific template
     */
    public function getTemplate(string $key): string
    {
        $templates = $this->templates ?? [];
        $defaults = static::getDefaultTemplates();

        return $templates[$key] ?? $defaults[$key] ?? '';
    }

    /**
     * Check if WhatsApp is configured
     */
    public function isWhatsAppConfigured(): bool
    {
        return $this->wa_enabled && !empty($this->wa_api_key);
    }

    /**
     * Check if Telegram is configured
     */
    public function isTelegramConfigured(): bool
    {
        return $this->telegram_enabled && !empty($this->telegram_bot_token);
    }

    /**
     * Get or create settings for a tenant
     */
    public static function getForTenant(string $tenantId): static
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'wa_enabled' => false,
                'wa_provider' => 'fonnte',
                'telegram_enabled' => false,
                'templates' => static::getDefaultTemplates(),
            ]
        );
    }
}
