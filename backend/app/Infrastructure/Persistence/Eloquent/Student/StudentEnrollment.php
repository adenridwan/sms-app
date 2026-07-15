<?php

namespace App\Infrastructure\Persistence\Eloquent\Student;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentEnrollment extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'student_enrollments';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academic_year_id',
        'classroom_id',
        'student_number_in_class',
        'status',
        'enrollment_date',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
        ];
    }

    /**
     * Get the student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the academic year
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear::class,
            'academic_year_id'
        );
    }

    /**
     * Get the classroom
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Academic\Classroom::class,
            'classroom_id'
        );
    }

    /**
     * Scope for active enrollments
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for a specific academic year
     */
    public function scopeForAcademicYear(Builder $query, string $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Scope for a specific classroom
     */
    public function scopeForClassroom(Builder $query, string $classroomId): Builder
    {
        return $query->where('classroom_id', $classroomId);
    }
}
