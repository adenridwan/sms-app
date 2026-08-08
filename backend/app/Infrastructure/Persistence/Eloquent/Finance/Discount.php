<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'discounts';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'type',
        'value',
        'fee_type_id',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Constants for type
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public static function getTypes(): array
    {
        return [
            self::TYPE_PERCENTAGE => 'Persentase (%)',
            self::TYPE_FIXED => 'Nominal Tetap (Rp)',
        ];
    }

    // Relationships

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    public function studentDiscounts(): HasMany
    {
        return $this->hasMany(StudentDiscount::class, 'discount_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where(function ($q) use ($today) {
            $q->whereNull('valid_from')
                ->orWhere('valid_from', '<=', $today);
        })->where(function ($q) use ($today) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>=', $today);
        });
    }

    public function scopeForFeeType(Builder $query, ?string $feeTypeId): Builder
    {
        return $query->where(function ($q) use ($feeTypeId) {
            $q->whereNull('fee_type_id')
                ->orWhere('fee_type_id', $feeTypeId);
        });
    }

    // Helpers

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Calculate the discount amount for a given base amount.
     */
    public function calculateDiscount(float $baseAmount): float
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            return $baseAmount * ((float) $this->value / 100);
        }

        return min((float) $this->value, $baseAmount);
    }

    /**
     * Check if the discount is currently valid.
     */
    public function isValid(): bool
    {
        $today = now()->toDateString();

        $validFrom = $this->valid_from === null || $this->valid_from <= $today;
        $validUntil = $this->valid_until === null || $this->valid_until >= $today;

        return $this->is_active && $validFrom && $validUntil;
    }
}
