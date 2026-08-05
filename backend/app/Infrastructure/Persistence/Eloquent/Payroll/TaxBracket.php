<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxBracket extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'tax_brackets';

    protected $fillable = [
        'tenant_id',
        'min_amount',
        'max_amount',
        'rate',
        'effective_year',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'effective_year' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('effective_year', $year);
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('min_amount');
    }

    // Helpers

    /**
     * Get formatted bracket range (e.g., "0 - 60.000.000" or "> 5.000.000.000").
     */
    public function getRangeLabel(): string
    {
        $min = number_format($this->min_amount, 0, ',', '.');

        if ($this->max_amount === null) {
            return "> {$min}";
        }

        $max = number_format($this->max_amount, 0, ',', '.');
        return "{$min} - {$max}";
    }

    /**
     * Check if an amount falls within this bracket.
     */
    public function containsAmount(float $amount): bool
    {
        $aboveMin = $amount >= $this->min_amount;
        $belowMax = $this->max_amount === null || $amount <= $this->max_amount;

        return $aboveMin && $belowMax;
    }

    /**
     * Calculate tax for an amount within this bracket.
     */
    public function calculateTax(float $amount): float
    {
        if (!$this->containsAmount($amount)) {
            // If above bracket, tax full range
            if ($amount > $this->max_amount && $this->max_amount !== null) {
                $taxableAmount = $this->max_amount - $this->min_amount;
            } else {
                return 0;
            }
        } else {
            $taxableAmount = $amount - $this->min_amount;
        }

        return $taxableAmount * ((float) $this->rate / 100);
    }

    /**
     * Get all active brackets for a given year and calculate progressive tax.
     */
    public static function calculateProgressiveTax(string $tenantId, float $pkp, int $year): float
    {
        $brackets = self::where('tenant_id', $tenantId)
            ->forYear($year)
            ->active()
            ->ordered()
            ->get();

        $totalTax = 0;
        $remainingPkp = $pkp;

        foreach ($brackets as $bracket) {
            if ($remainingPkp <= 0) {
                break;
            }

            $bracketSize = $bracket->max_amount !== null
                ? $bracket->max_amount - $bracket->min_amount
                : $remainingPkp;

            $taxableInBracket = min($remainingPkp, $bracketSize);
            $totalTax += $taxableInBracket * ((float) $bracket->rate / 100);
            $remainingPkp -= $taxableInBracket;
        }

        return $totalTax;
    }
}
