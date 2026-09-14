<?php

namespace App\Infrastructure\Persistence\Eloquent\Exam;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use App\Infrastructure\Persistence\Eloquent\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamScore extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'student_id',
        'score',
        'notes',
        'is_absent',
        'is_remedial',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'is_absent' => 'boolean',
        'is_remedial' => 'boolean',
        'graded_at' => 'datetime',
    ];

    protected $appends = ['is_passed', 'grade_letter'];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function remedial(): HasOne
    {
        return $this->hasOne(Remedial::class, 'exam_id', 'exam_id')
            ->where('student_id', $this->student_id);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getIsPassedAttribute(): bool
    {
        if ($this->is_absent) {
            return false;
        }

        $passingScore = $this->exam?->passing_score ?? 0;
        return $this->score >= $passingScore;
    }

    public function getGradeLetterAttribute(): string
    {
        if ($this->is_absent) {
            return '-';
        }

        $maxScore = $this->exam?->max_score ?? 100;
        $percentage = ($this->score / $maxScore) * 100;

        return match (true) {
            $percentage >= 90 => 'A',
            $percentage >= 80 => 'B',
            $percentage >= 70 => 'C',
            $percentage >= 60 => 'D',
            default => 'E',
        };
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeForExam($query, string $examId)
    {
        return $query->where('exam_id', $examId);
    }

    public function scopeForStudent($query, string $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopePassed($query)
    {
        return $query->whereHas('exam', function ($q) {
            $q->whereColumn('exam_scores.score', '>=', 'exams.passing_score');
        });
    }

    public function scopeFailed($query)
    {
        return $query->whereHas('exam', function ($q) {
            $q->whereColumn('exam_scores.score', '<', 'exams.passing_score');
        })->where('is_absent', false);
    }

    public function scopeNeedsRemedial($query)
    {
        return $query->where('is_remedial', false)
            ->where('is_absent', false)
            ->whereHas('exam', function ($q) {
                $q->whereColumn('exam_scores.score', '<', 'exams.passing_score');
            });
    }
}
