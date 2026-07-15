<?php

namespace App\Domain\Attendance\Services;

use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceSetting;
use Carbon\Carbon;

class LateCalculationService
{
    /**
     * Calculate lateness in minutes based on check-in time
     */
    public function calculate(Carbon $checkInTime, ?string $tenantId = null): int
    {
        $settings = $this->getSettings($tenantId);

        if (!$settings) {
            return 0;
        }

        return $settings->calculateLateness($checkInTime);
    }

    /**
     * Check if a check-in time is considered late
     */
    public function isLate(Carbon $checkInTime, ?string $tenantId = null): bool
    {
        return $this->calculate($checkInTime, $tenantId) > 0;
    }

    /**
     * Get the check-in deadline time
     */
    public function getCheckInDeadline(?string $tenantId = null): Carbon
    {
        $settings = $this->getSettings($tenantId);

        if (!$settings) {
            // Default deadline: 07:30
            return Carbon::today()->setTime(7, 30);
        }

        return $settings->getCheckInDeadline();
    }

    /**
     * Get the standard check-in end time (before tolerance)
     */
    public function getCheckInEnd(?string $tenantId = null): Carbon
    {
        $settings = $this->getSettings($tenantId);

        if (!$settings) {
            return Carbon::today()->setTime(7, 15);
        }

        return Carbon::parse($settings->check_in_end);
    }

    /**
     * Get lateness category based on minutes
     */
    public function getLateCategoryInfo(int $minutes): array
    {
        if ($minutes === 0) {
            return [
                'category' => 'on_time',
                'label' => 'Tepat Waktu',
                'color' => 'green',
                'points' => 0,
            ];
        }

        if ($minutes <= 15) {
            return [
                'category' => 'slightly_late',
                'label' => 'Terlambat Ringan',
                'color' => 'yellow',
                'points' => $minutes, // 1 point per minute
            ];
        }

        if ($minutes <= 30) {
            return [
                'category' => 'moderately_late',
                'label' => 'Terlambat Sedang',
                'color' => 'orange',
                'points' => $minutes, // 1 point per minute
            ];
        }

        return [
            'category' => 'very_late',
            'label' => 'Terlambat Berat',
            'color' => 'red',
            'points' => $minutes, // 1 point per minute
        ];
    }

    /**
     * Format lateness for display
     */
    public function formatLateness(int $minutes): string
    {
        if ($minutes === 0) {
            return 'Tepat waktu';
        }

        if ($minutes < 60) {
            return "{$minutes} menit";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$remainingMinutes} menit";
    }

    /**
     * Get attendance settings
     */
    private function getSettings(?string $tenantId = null): ?AttendanceSetting
    {
        if ($tenantId) {
            return AttendanceSetting::where('tenant_id', $tenantId)->first();
        }

        // Try to get from current tenant context
        $currentTenantId = auth()->user()?->tenant_id;

        if ($currentTenantId) {
            return AttendanceSetting::where('tenant_id', $currentTenantId)->first();
        }

        return AttendanceSetting::first();
    }
}
