<?php

namespace App\Infrastructure\Persistence\Eloquent\Exam;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remedial extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'student_id',
        'original_score',
        'remedial_score',
        'remedial_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'original_score' => 'decimal:2',
        'remedial_score' => 'decimal:2',
        'remedial_date' => 'date',
    ];

    protected $appends = ['status_label', 'final_score'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_WAIVED = 'waived';

    public const STATUSES = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_SCHEDULED => 'Dijadwalkan',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_WAIVED => 'Dibebaskan',
    ];

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

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getFinalScoreAttribute(): ?float
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return null;
        }

        // Remedial score capped at passing score or take higher of original/remedial
        $passingScore = $this->exam?->passing_score ?? 0;

        if ($this->remedial_score !== null) {
            // Take maximum of remedial score and passing score
            return min($this->remedial_score, $passingScore);
        }

        return $this->original_score;
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

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SCHEDULED]);
    }
}
