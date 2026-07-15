<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceSetting;
use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSettingController extends ApiController
{
    /**
     * Get attendance settings
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $attendanceSettings = AttendanceSetting::getForTenant($tenantId);
        $notificationSettings = NotificationSetting::getForTenant($tenantId);

        return $this->success([
            'attendance' => [
                'check_in_start' => $attendanceSettings->check_in_start,
                'check_in_end' => $attendanceSettings->check_in_end,
                'check_out_start' => $attendanceSettings->check_out_start,
                'check_out_end' => $attendanceSettings->check_out_end,
                'late_tolerance_minutes' => $attendanceSettings->late_tolerance_minutes,
                'require_location' => $attendanceSettings->require_location,
                'require_photo' => $attendanceSettings->require_photo,
                'location_radius' => $attendanceSettings->location_radius,
                'school_latitude' => $attendanceSettings->school_latitude,
                'school_longitude' => $attendanceSettings->school_longitude,
                'working_days' => $attendanceSettings->working_days,
            ],
            'notification' => [
                'wa_enabled' => $notificationSettings->wa_enabled,
                'wa_provider' => $notificationSettings->wa_provider,
                'wa_configured' => $notificationSettings->isWhatsAppConfigured(),
                'telegram_enabled' => $notificationSettings->telegram_enabled,
                'telegram_configured' => $notificationSettings->isTelegramConfigured(),
                'notify_check_in' => $notificationSettings->notify_check_in,
                'notify_check_out' => $notificationSettings->notify_check_out,
                'notify_late' => $notificationSettings->notify_late,
                'notify_absent' => $notificationSettings->notify_absent,
                'notify_leave_approved' => $notificationSettings->notify_leave_approved,
                'templates' => $notificationSettings->templates ?? NotificationSetting::getDefaultTemplates(),
            ],
        ]);
    }

    /**
     * Update attendance settings
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Attendance settings
            'check_in_start' => ['sometimes', 'date_format:H:i:s'],
            'check_in_end' => ['sometimes', 'date_format:H:i:s'],
            'check_out_start' => ['sometimes', 'date_format:H:i:s'],
            'check_out_end' => ['sometimes', 'date_format:H:i:s'],
            'late_tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'require_location' => ['sometimes', 'boolean'],
            'require_photo' => ['sometimes', 'boolean'],
            'location_radius' => ['nullable', 'numeric', 'min:10', 'max:10000'],
            'school_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'school_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'working_days' => ['sometimes', 'array'],
            'working_days.*' => ['integer', 'between:0,6'],

            // Notification settings
            'wa_enabled' => ['sometimes', 'boolean'],
            'wa_provider' => ['sometimes', 'in:fonnte,wablas'],
            'wa_api_key' => ['nullable', 'string', 'max:255'],
            'wa_sender_number' => ['nullable', 'string', 'max:32'],
            'telegram_enabled' => ['sometimes', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_default_chat_id' => ['nullable', 'string', 'max:100'],
            'notify_check_in' => ['sometimes', 'boolean'],
            'notify_check_out' => ['sometimes', 'boolean'],
            'notify_late' => ['sometimes', 'boolean'],
            'notify_absent' => ['sometimes', 'boolean'],
            'notify_leave_approved' => ['sometimes', 'boolean'],
            'templates' => ['sometimes', 'array'],
        ]);

        $tenantId = $request->user()->tenant_id;

        // Update attendance settings
        $attendanceFields = [
            'check_in_start', 'check_in_end', 'check_out_start', 'check_out_end',
            'late_tolerance_minutes', 'require_location', 'require_photo',
            'location_radius', 'school_latitude', 'school_longitude', 'working_days',
        ];

        $attendanceData = array_intersect_key($data, array_flip($attendanceFields));
        if (!empty($attendanceData)) {
            $attendanceSettings = AttendanceSetting::getForTenant($tenantId);
            $attendanceSettings->update($attendanceData);
        }

        // Update notification settings
        $notificationFields = [
            'wa_enabled', 'wa_provider', 'wa_api_key', 'wa_sender_number',
            'telegram_enabled', 'telegram_bot_token', 'telegram_default_chat_id',
            'notify_check_in', 'notify_check_out', 'notify_late',
            'notify_absent', 'notify_leave_approved', 'templates',
        ];

        $notificationData = array_intersect_key($data, array_flip($notificationFields));
        if (!empty($notificationData)) {
            $notificationSettings = NotificationSetting::getForTenant($tenantId);
            $notificationSettings->update($notificationData);
        }

        return $this->success(null, 'Pengaturan berhasil diperbarui');
    }

    /**
     * Test WhatsApp notification
     */
    public function testWhatsApp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->isWhatsAppConfigured()) {
            return $this->error('WhatsApp belum dikonfigurasi', 422);
        }

        $whatsAppService = app(\App\Domain\Notification\Services\WhatsAppService::class);
        $whatsAppService->initializeForTenant($tenantId);

        $success = $whatsAppService->send(
            $data['phone'],
            'Ini adalah pesan uji coba dari sistem absensi sekolah.'
        );

        if ($success) {
            return $this->success(null, 'Pesan uji coba berhasil dikirim');
        }

        return $this->error('Gagal mengirim pesan uji coba', 500);
    }

    /**
     * Test Telegram notification
     */
    public function testTelegram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => ['required', 'string', 'max:100'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->isTelegramConfigured()) {
            return $this->error('Telegram belum dikonfigurasi', 422);
        }

        $telegramService = app(\App\Domain\Notification\Services\TelegramService::class);
        $telegramService->initializeForTenant($tenantId);

        $success = $telegramService->send(
            $data['chat_id'],
            'Ini adalah pesan uji coba dari sistem absensi sekolah.'
        );

        if ($success) {
            return $this->success(null, 'Pesan uji coba berhasil dikirim');
        }

        return $this->error('Gagal mengirim pesan uji coba', 500);
    }

    /**
     * Get Telegram bot info
     */
    public function telegramBotInfo(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->isTelegramConfigured()) {
            return $this->error('Telegram belum dikonfigurasi', 422);
        }

        $telegramService = app(\App\Domain\Notification\Services\TelegramService::class);
        $telegramService->initializeForTenant($tenantId);

        $botInfo = $telegramService->getBotInfo();

        if ($botInfo) {
            return $this->success($botInfo, 'Bot info berhasil diambil');
        }

        return $this->error('Gagal mengambil info bot', 500);
    }
}
