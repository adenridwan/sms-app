<?php

namespace App\Infrastructure\Persistence\Eloquent\Exam;

use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use App\Infrastructure\Persistence\Eloquent\Academic\Subject;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeComponent extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'semester_id',
        'name',
        'code',
        'description',
        'weight',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function studentGrades(): HasMany
    {
        return $this->hasMany(StudentGrade::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSubject($query, string $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForSemester($query, string $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }
}
