<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDiscount extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'student_discounts';

    protected $fillable = [
        'student_id',
        'discount_id',
        'academic_year_id',
        'reason',
        'approved_by',
        'approved_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopeForStudent(Builder $query, string $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForAcademicYear(Builder $query, string $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    // Helpers

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function approve(string $userId): void
    {
        $this->update([
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }
}
