<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'salary_components';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'calculation_type',
        'default_value',
        'percentage_of',
        'percentage_component_id',
        'formula',
        'is_taxable',
        'is_mandatory',
        'is_active',
        'order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
            'formula' => 'array', // JSON formula untuk GUI builder
        ];
    }

    // Constants for percentage references
    public const PERCENTAGE_BASE_SALARY = 'base_salary';
    public const PERCENTAGE_GROSS_SALARY = 'gross_salary';

    public static function getPercentageReferences(): array
    {
        return [
            self::PERCENTAGE_BASE_SALARY => 'Gaji Pokok',
            self::PERCENTAGE_GROSS_SALARY => 'Gaji Kotor (Total Pendapatan)',
        ];
    }

    // Constants for type
    public const TYPE_EARNING = 'earning';
    public const TYPE_DEDUCTION = 'deduction';

    public static function getTypes(): array
    {
        return [
            self::TYPE_EARNING => 'Pendapatan',
            self::TYPE_DEDUCTION => 'Potongan',
        ];
    }

    // Constants for calculation_type
    public const CALC_FIXED = 'fixed';
    public const CALC_PERCENTAGE = 'percentage';
    public const CALC_PER_DAY = 'per_day';
    public const CALC_PER_HOUR = 'per_hour';
    public const CALC_FORMULA = 'formula';

    public static function getCalculationTypes(): array
    {
        return [
            self::CALC_FIXED => 'Nominal Tetap',
            self::CALC_PERCENTAGE => 'Persentase',
            self::CALC_PER_DAY => 'Per Hari',
            self::CALC_PER_HOUR => 'Per Jam',
            self::CALC_FORMULA => 'Rumus Khusus',
        ];
    }

    // Relationships

    public function employeeSalaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'salary_component_id');
    }

    /**
     * Komponen yang direferensikan untuk perhitungan persentase.
     */
    public function percentageComponent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'percentage_component_id');
    }

    /**
     * Komponen yang mereferensikan komponen ini untuk persentase.
     */
    public function referencedByComponents(): HasMany
    {
        return $this->hasMany(self::class, 'percentage_component_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EARNING);
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEDUCTION);
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('type')->orderBy('order')->orderBy('name');
    }

    // Helpers

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    public function getCalculationTypeLabel(): string
    {
        return self::getCalculationTypes()[$this->calculation_type] ?? $this->calculation_type;
    }

    public function isEarning(): bool
    {
        return $this->type === self::TYPE_EARNING;
    }

    public function isDeduction(): bool
    {
        return $this->type === self::TYPE_DEDUCTION;
    }

    /**
     * Calculate value based on type and parameters.
     */
    public function calculateValue(float $baseValue, array $params = []): float
    {
        return match ($this->calculation_type) {
            self::CALC_FIXED => (float) $this->default_value,
            self::CALC_PERCENTAGE => $baseValue * ((float) $this->default_value / 100),
            self::CALC_PER_DAY => (float) $this->default_value * ($params['days'] ?? 0),
            self::CALC_PER_HOUR => (float) $this->default_value * ($params['hours'] ?? 0),
            default => (float) $this->default_value,
        };
    }
}
