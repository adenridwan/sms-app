<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'employee_attendances';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'attendance_date',
        'status',
        'perlu_verifikasi',
        'check_in_time',
        'check_out_time',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_photo',
        'check_out_photo',
        'notes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'perlu_verifikasi' => 'boolean',
            'check_in_latitude' => 'decimal:8',
            'check_in_longitude' => 'decimal:8',
            'check_out_latitude' => 'decimal:8',
            'check_out_longitude' => 'decimal:8',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if employee was late
     */
    public function isLate(): bool
    {
        return $this->late_minutes > 0;
    }

    /**
     * Check if employee left early
     */
    public function leftEarly(): bool
    {
        return $this->early_leave_minutes > 0;
    }

    /**
     * Scope for a specific date
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('attendance_date', $date);
    }

    /**
     * Scope for a specific user
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for date range
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    /**
     * Get attendance record for a user on a specific date
     */
    public static function getForUserOnDate(string $userId, string $date): ?static
    {
        return static::where('user_id', $userId)
            ->whereDate('attendance_date', $date)
            ->first();
    }
}
