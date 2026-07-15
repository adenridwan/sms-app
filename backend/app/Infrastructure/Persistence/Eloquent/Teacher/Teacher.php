<?php

namespace App\Infrastructure\Persistence\Eloquent\Teacher;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Teacher extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'teachers';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'nip',
        'nuptk',
        'unique_code',
        'rfid_code',
        'no_hp',
        'join_date',
        'employment_status',
        'certification_status',
        'certification_number',
        'education_level',
        'education_major',
        'university',
        'teaching_experience_years',
        'status',
        'additional_info',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'teaching_experience_years' => 'integer',
            'additional_info' => 'array',
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->unique_code)) {
                $model->unique_code = 'TCH-' . strtoupper(Str::random(12));
            }
        });
    }

    /**
     * Get the user account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get employee attendance records
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(
            \App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance::class,
            'user_id',
            'user_id'
        );
    }

    /**
     * Get leave permissions
     */
    public function leavePermissions(): HasMany
    {
        return $this->hasMany(LeavePermission::class, 'teacher_id');
    }

    /**
     * Get subjects taught
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'teacher_id');
    }

    /**
     * Generate new unique code
     */
    public function regenerateUniqueCode(): string
    {
        $this->unique_code = 'TCH-' . strtoupper(Str::random(12));
        $this->save();

        return $this->unique_code;
    }

    /**
     * Scope for active teachers
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search by unique code or RFID
     */
    public function scopeFindByCode(Builder $query, string $code): Builder
    {
        return $query->where('unique_code', $code)
            ->orWhere('rfid_code', $code);
    }

    /**
     * Find teacher by unique code or RFID code
     */
    public static function findByCode(string $code): ?static
    {
        return static::where('unique_code', $code)
            ->orWhere('rfid_code', $code)
            ->first();
    }
}
