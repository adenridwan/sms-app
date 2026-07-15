<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceSetting extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'attendance_settings';

    protected $fillable = [
        'tenant_id',
        'check_in_start',
        'check_in_end',
        'check_out_start',
        'check_out_end',
        'late_tolerance_minutes',
        'require_location',
        'require_photo',
        'location_radius',
        'school_latitude',
        'school_longitude',
        'working_days',
    ];

    protected function casts(): array
    {
        return [
            'late_tolerance_minutes' => 'integer',
            'require_location' => 'boolean',
            'require_photo' => 'boolean',
            'location_radius' => 'decimal:2',
            'school_latitude' => 'decimal:8',
            'school_longitude' => 'decimal:8',
            'working_days' => 'array',
        ];
    }

    /**
     * Get or create settings for a tenant
     */
    public static function getForTenant(string $tenantId): static
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'check_in_start' => '06:00:00',
                'check_in_end' => '07:30:00',
                'check_out_start' => '14:00:00',
                'check_out_end' => '17:00:00',
                'late_tolerance_minutes' => 15,
                'require_location' => false,
                'require_photo' => false,
                'working_days' => [1, 2, 3, 4, 5], // Monday to Friday
            ]
        );
    }

    /**
     * Get check-in deadline (jam_masuk_limit)
     * This is check_in_end + late_tolerance_minutes
     */
    public function getCheckInDeadline(): Carbon
    {
        return Carbon::parse($this->check_in_end)
            ->addMinutes($this->late_tolerance_minutes);
    }

    /**
     * Get standard check-out time (jam_pulang_standard)
     */
    public function getCheckOutStandard(): Carbon
    {
        return Carbon::parse($this->check_out_start);
    }

    /**
     * Check if current time is after check-out standard
     */
    public function isAfterCheckOutTime(): bool
    {
        return now()->greaterThan($this->getCheckOutStandard());
    }

    /**
     * Calculate lateness in minutes
     */
    public function calculateLateness(Carbon $checkInTime): int
    {
        $deadline = $this->getCheckInDeadline();

        if ($checkInTime->lessThanOrEqualTo($deadline)) {
            return 0;
        }

        return $checkInTime->diffInMinutes($deadline);
    }

    /**
     * Check if a day is a working day
     */
    public function isWorkingDay(int $dayOfWeek): bool
    {
        $workingDays = $this->working_days ?? [1, 2, 3, 4, 5];
        return in_array($dayOfWeek, $workingDays);
    }

    /**
     * Check if today is a working day
     */
    public function isTodayWorkingDay(): bool
    {
        return $this->isWorkingDay(now()->dayOfWeek);
    }
}
