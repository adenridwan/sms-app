<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReport extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'financial_reports';

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'month',
        'year',
        'total_billed',
        'total_collected',
        'total_outstanding',
        'total_discount',
        'total_fine',
        'student_count',
        'paid_student_count',
        'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'total_billed' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'total_outstanding' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_fine' => 'decimal:2',
            'student_count' => 'integer',
            'paid_student_count' => 'integer',
            'breakdown' => 'array',
        ];
    }

    // Relationships

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    // Scopes

    public function scopeForAcademicYear(Builder $query, string $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForPeriod(Builder $query, int $month, int $year): Builder
    {
        return $query->where('month', $month)->where('year', $year);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    // Helpers

    /**
     * Get the collection rate as percentage.
     */
    public function getCollectionRateAttribute(): float
    {
        if ($this->total_billed == 0) {
            return 0;
        }

        return round(($this->total_collected / $this->total_billed) * 100, 2);
    }

    /**
     * Get the payment rate (students who paid / total students) as percentage.
     */
    public function getPaymentRateAttribute(): float
    {
        if ($this->student_count == 0) {
            return 0;
        }

        return round(($this->paid_student_count / $this->student_count) * 100, 2);
    }

    /**
     * Get period label (e.g., "Januari 2024").
     */
    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($months[$this->month] ?? '') . ' ' . $this->year;
    }
}
