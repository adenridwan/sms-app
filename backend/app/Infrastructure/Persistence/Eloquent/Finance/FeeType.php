<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeType extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'fee_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'frequency',
        'is_mandatory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'fee_type_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'fee_type_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeByFrequency(Builder $query, string $frequency): Builder
    {
        return $query->where('frequency', $frequency);
    }
}
