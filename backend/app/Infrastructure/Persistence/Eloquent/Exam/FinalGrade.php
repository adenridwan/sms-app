<?php

namespace App\Infrastructure\Persistence\Eloquent\Exam;

use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use App\Infrastructure\Persistence\Eloquent\Academic\Subject;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use App\Infrastructure\Persistence\Eloquent\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalGrade extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'subject_id',
        'semester_id',
        'knowledge_score',
        'skill_score',
        'attitude_score',
        'final_score',
        'grade_letter',
        'predicate',
        'description',
        'is_passed',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'knowledge_score' => 'decimal:2',
        'skill_score' => 'decimal:2',
        'attitude_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'is_passed' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $appends = ['is_approved', 'status'];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getIsApprovedAttribute(): bool
    {
        return $this->approved_by !== null && $this->approved_at !== null;
    }

    public function getStatusAttribute(): string
    {
        return $this->is_approved ? 'approved' : 'pending';
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeForStudent($query, string $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForSubject($query, string $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForSemester($query, string $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by')->whereNotNull('approved_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('is_passed', false);
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    public function calculateFinalScore(): float
    {
        // Default weights: Knowledge 60%, Skill 30%, Attitude 10%
        $knowledgeWeight = 0.6;
        $skillWeight = 0.3;
        $attitudeWeight = 0.1;

        return round(
            ($this->knowledge_score * $knowledgeWeight) +
            ($this->skill_score * $skillWeight) +
            ($this->attitude_score * $attitudeWeight),
            2
        );
    }

    public function approve(User $user): void
    {
        $this->approved_by = $user->id;
        $this->approved_at = now();
        $this->save();
    }
}
