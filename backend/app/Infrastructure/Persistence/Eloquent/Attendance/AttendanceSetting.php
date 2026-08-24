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
     * Apakah waktu scan pulang berada dalam rentang jam pulang wajar
     * (check_out_start s/d check_out_end). Di luar rentang → perlu verifikasi.
     */
    public function isWithinCheckOutWindow(Carbon $time): bool
    {
        $start = $time->copy()->setTimeFromTimeString($this->check_out_start);
        $end = $time->copy()->setTimeFromTimeString($this->check_out_end);

        return $time->betweenIncluded($start, $end);
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

        // absolute:true wajib — Carbon 3 mengubah default diffInMinutes()
        // jadi signed, jadi tanpa ini nilainya NEGATIF untuk keterlambatan
        // (checkInTime lebih baru dari deadline). menit_keterlambatan lalu
        // gagal lolos semua pengecekan `> 0` di seluruh modul (isLate(),
        // badge Keterlambatan di UI, kategori poin pelanggaran) sehingga
        // siswa/guru yang scan telat dianggap tepat waktu.
        return (int) round($checkInTime->diffInMinutes($deadline, true));
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

    /**
     * Great-circle distance (Haversine) in meters from the school's
     * configured coordinates to the given point.
     */
    public function distanceFromSchool(float $lat, float $lng): float
    {
        $earthRadiusMeters = 6371000;

        $latFrom = deg2rad((float) $this->school_latitude);
        $lngFrom = deg2rad((float) $this->school_longitude);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    /**
     * Whether the school's coordinates have been configured, i.e. a
     * geofence check is actually possible.
     */
    public function hasSchoolLocation(): bool
    {
        return $this->school_latitude !== null
            && $this->school_longitude !== null
            && $this->location_radius !== null;
    }
}
