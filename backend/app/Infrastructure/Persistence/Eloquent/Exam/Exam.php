<?php

namespace App\Infrastructure\Persistence\Eloquent\Exam;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use App\Infrastructure\Persistence\Eloquent\Academic\Subject;
use App\Infrastructure\Persistence\Eloquent\Staff\Teacher;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'semester_id',
        'subject_id',
        'exam_type_id',
        'classroom_id',
        'teacher_id',
        'name',
        'description',
        'exam_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'max_score',
        'passing_score',
        'weight',
        'status',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'duration_minutes' => 'integer',
        'max_score' => 'decimal:2',
        'passing_score' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    protected $appends = ['status_label', 'score_count', 'average_score'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_SCHEDULED => 'Dijadwalkan',
        self::STATUS_ONGOING => 'Berlangsung',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ExamScore::class);
    }

    public function remedials(): HasMany
    {
        return $this->hasMany(Remedial::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getScoreCountAttribute(): int
    {
        return $this->scores()->count();
    }

    public function getAverageScoreAttribute(): ?float
    {
        $avg = $this->scores()->avg('score');
        return $avg !== null ? round($avg, 2) : null;
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeForAcademicYear($query, string $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForSemester($query, string $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForSubject($query, string $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForClassroom($query, string $classroomId)
    {
        return $query->where('classroom_id', $classroomId);
    }

    public function scopeForTeacher($query, string $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhereHas('subject', fn($sq) => $sq->where('name', 'ilike', "%{$search}%"))
              ->orWhereHas('classroom', fn($sq) => $sq->where('name', 'ilike', "%{$search}%"));
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED]);
    }

    public function canBeDeleted(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->scores()->count() === 0;
    }

    public function canAcceptScores(): bool
    {
        return in_array($this->status, [self::STATUS_ONGOING, self::STATUS_COMPLETED]);
    }
}
