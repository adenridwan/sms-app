<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'student_attendances';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'classroom_id',
        'academic_year_id',
        'semester_id',
        'attendance_date',
        'status',
        'perlu_verifikasi',
        'menit_keterlambatan',
        'check_in_time',
        'check_out_time',
        'notes',
        'excuse_document',
        'recorded_by',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'perlu_verifikasi' => 'boolean',
            'menit_keterlambatan' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Get the student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Student\Student::class,
            'student_id'
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
     * Get the semester
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Academic\Semester::class,
            'semester_id'
        );
    }

    /**
     * Get the user who recorded this attendance
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get status as enum
     */
    public function getStatusEnumAttribute(): AttendanceStatus
    {
        return AttendanceStatus::tryFrom($this->status) ?? AttendanceStatus::Hadir;
    }

    /**
     * Check if student was late
     */
    public function isLate(): bool
    {
        return $this->menit_keterlambatan > 0;
    }

    /**
     * Scope for a specific date
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('attendance_date', $date);
    }

    /**
     * Scope for a specific student
     */
    public function scopeForStudent(Builder $query, string $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope for a specific classroom
     */
    public function scopeForClassroom(Builder $query, string $classroomId): Builder
    {
        return $query->where('classroom_id', $classroomId);
    }

    /**
     * Scope for a specific status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for late attendances
     */
    public function scopeLate(Builder $query): Builder
    {
        return $query->where('menit_keterlambatan', '>', 0);
    }

    /**
     * Scope for date range
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    /**
     * Get attendance record for a student on a specific date
     */
    public static function getForStudentOnDate(string $studentId, string $date): ?static
    {
        return static::where('student_id', $studentId)
            ->whereDate('attendance_date', $date)
            ->first();
    }
}
