<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlipItem extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'payroll_slip_items';

    protected $fillable = [
        'payroll_slip_id',
        'salary_component_id',
        'component_code',
        'component_name',
        'type',
        'category',
        'amount',
        'quantity',
        'rate',
        'is_taxable',
        'is_auto_calculated',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_auto_calculated' => 'boolean',
        ];
    }

    // Type constants
    public const TYPE_EARNING = 'earning';
    public const TYPE_DEDUCTION = 'deduction';

    // Category constants
    public const CATEGORY_FIXED = 'fixed';
    public const CATEGORY_VARIABLE = 'variable';
    public const CATEGORY_ATTENDANCE = 'attendance';
    public const CATEGORY_TAX = 'tax';
    public const CATEGORY_BPJS = 'bpjs';
    public const CATEGORY_OTHER = 'other';

    public static function getTypes(): array
    {
        return [
            self::TYPE_EARNING => 'Pendapatan',
            self::TYPE_DEDUCTION => 'Potongan',
        ];
    }

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_FIXED => 'Tetap',
            self::CATEGORY_VARIABLE => 'Variabel',
            self::CATEGORY_ATTENDANCE => 'Kehadiran',
            self::CATEGORY_TAX => 'Pajak',
            self::CATEGORY_BPJS => 'BPJS',
            self::CATEGORY_OTHER => 'Lainnya',
        ];
    }

    // Relationships
    public function slip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class, 'payroll_slip_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    // Scopes
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EARNING);
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEDUCTION);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeTaxable(Builder $query): Builder
    {
        return $query->where('is_taxable', true);
    }

    public function scopeAutoCalculated(Builder $query): Builder
    {
        return $query->where('is_auto_calculated', true);
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('is_auto_calculated', false);
    }

    // Helpers
    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    public function getCategoryLabel(): string
    {
        return self::getCategories()[$this->category] ?? $this->category;
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
     * Calculate amount based on quantity and rate.
     */
    public function calculateAmount(): float
    {
        if ($this->rate && $this->quantity) {
            return (float) $this->rate * (float) $this->quantity;
        }
        return (float) $this->amount;
    }
}
