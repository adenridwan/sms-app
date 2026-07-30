<?php

namespace App\Infrastructure\Persistence\Eloquent\Academic;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'academic_years';

    protected $fillable = [
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class, 'academic_year_id');
    }

    /**
     * The currently active semester within this academic year. Referenced
     * as `$academicYear?->activeSemester?->id` by attendance write paths
     * (StudentAttendanceController::storeBulk, AttendanceScanService,
     * LeaveApprovalService) to stamp new attendance rows with semester_id.
     */
    public function activeSemester(): HasOne
    {
        return $this->hasOne(Semester::class, 'academic_year_id')->where('is_active', true);
    }

    public function classRooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'academic_year_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
