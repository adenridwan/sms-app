<?php

namespace App\Infrastructure\Persistence\Eloquent\Academic;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'classrooms';

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'grade_level_id',
        'major_id',
        'homeroom_teacher_id',
        'name',
        'code',
        'room',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'classroom_id');
    }
}
