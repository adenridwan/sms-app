<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\GradeLevel;
use App\Infrastructure\Persistence\Eloquent\Academic\Major;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'fee_structures';

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'fee_type_id',
        'grade_level_id',
        'major_id',
        'amount',
        'discount_amount',
        'due_date',
        'due_day',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'due_date' => 'date',
            'due_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function studentFees(): HasMany
    {
        return $this->hasMany(StudentFee::class, 'fee_structure_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForAcademicYear(Builder $query, string $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForGradeLevel(Builder $query, ?string $gradeLevelId): Builder
    {
        return $query->where('grade_level_id', $gradeLevelId);
    }

    public function scopeForMajor(Builder $query, ?string $majorId): Builder
    {
        return $query->where('major_id', $majorId);
    }

    // Helpers

    /**
     * Get the effective amount after discount.
     */
    public function getEffectiveAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->discount_amount;
    }
}
