<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Domain\Attendance\Enums\LeaveStatus;
use App\Domain\Attendance\Enums\LeaveType;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeavePermission extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'leave_permissions';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'teacher_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'tipe_izin',
        'alasan',
        'bukti',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tipe_izin' => LeaveType::class,
            'status' => LeaveStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the student that owns the leave permission
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Student\Student::class,
            'student_id'
        );
    }

    /**
     * Get the teacher that owns the leave permission
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Teacher\Teacher::class,
            'teacher_id'
        );
    }

    /**
     * Get the user who approved the leave
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all dates covered by this leave permission
     */
    public function getCoveredDates(): array
    {
        $dates = [];
        $current = $this->tanggal_mulai->copy();

        while ($current <= $this->tanggal_selesai) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Get total days of leave
     */
    public function getTotalDaysAttribute(): int
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    /**
     * Check if this is a student leave
     */
    public function isStudentLeave(): bool
    {
        return !is_null($this->student_id);
    }

    /**
     * Check if this is a teacher leave
     */
    public function isTeacherLeave(): bool
    {
        return !is_null($this->teacher_id);
    }

    /**
     * Scope to filter pending permissions
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    /**
     * Scope to filter approved permissions
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    /**
     * Scope to filter by student
     */
    public function scopeForStudent(Builder $query, string $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope to filter by teacher
     */
    public function scopeForTeacher(Builder $query, string $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Scope to get permissions covering a specific date
     */
    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query->where('tanggal_mulai', '<=', $date)
            ->where('tanggal_selesai', '>=', $date);
    }
}
