<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BpjsRate extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'bpjs_rates';

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'employee_rate',
        'employer_rate',
        'min_salary',
        'max_salary',
        'effective_from',
        'effective_until',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:2',
            'employer_rate' => 'decimal:2',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Constants for type
    public const TYPE_KESEHATAN = 'kesehatan';
    public const TYPE_JHT = 'jht';
    public const TYPE_JKK = 'jkk';
    public const TYPE_JKM = 'jkm';
    public const TYPE_JP = 'jp';

    public static function getTypes(): array
    {
        return [
            self::TYPE_KESEHATAN => 'BPJS Kesehatan',
            self::TYPE_JHT => 'Jaminan Hari Tua (JHT)',
            self::TYPE_JKK => 'Jaminan Kecelakaan Kerja (JKK)',
            self::TYPE_JKM => 'Jaminan Kematian (JKM)',
            self::TYPE_JP => 'Jaminan Pensiun (JP)',
        ];
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            });
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $this->scopeActive($query)->scopeEffectiveOn($query, now()->toDateString());
    }

    // Helpers

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Calculate employee contribution based on salary.
     */
    public function calculateEmployeeContribution(float $salary): float
    {
        $applicableSalary = $salary;

        // Apply min/max limits
        if ($this->min_salary && $salary < $this->min_salary) {
            $applicableSalary = (float) $this->min_salary;
        }
        if ($this->max_salary && $salary > $this->max_salary) {
            $applicableSalary = (float) $this->max_salary;
        }

        return $applicableSalary * ((float) $this->employee_rate / 100);
    }

    /**
     * Calculate employer contribution based on salary.
     */
    public function calculateEmployerContribution(float $salary): float
    {
        $applicableSalary = $salary;

        // Apply min/max limits
        if ($this->min_salary && $salary < $this->min_salary) {
            $applicableSalary = (float) $this->min_salary;
        }
        if ($this->max_salary && $salary > $this->max_salary) {
            $applicableSalary = (float) $this->max_salary;
        }

        return $applicableSalary * ((float) $this->employer_rate / 100);
    }

    /**
     * Get total rate (employee + employer).
     */
    public function getTotalRateAttribute(): float
    {
        return (float) $this->employee_rate + (float) $this->employer_rate;
    }

    /**
     * Check if rate is currently effective.
     */
    public function isEffective(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && $this->effective_from <= $today
            && ($this->effective_until === null || $this->effective_until >= $today);
    }
}
