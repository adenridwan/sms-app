<?php

namespace App\Infrastructure\Persistence\Eloquent\Finance;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'payment_methods';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'provider',
        'configuration',
        'admin_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'admin_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Constants for type
    public const TYPE_CASH = 'cash';
    public const TYPE_BANK_TRANSFER = 'bank_transfer';
    public const TYPE_VIRTUAL_ACCOUNT = 'virtual_account';
    public const TYPE_E_WALLET = 'e_wallet';
    public const TYPE_CREDIT_CARD = 'credit_card';
    public const TYPE_OTHER = 'other';

    public static function getTypes(): array
    {
        return [
            self::TYPE_CASH => 'Tunai',
            self::TYPE_BANK_TRANSFER => 'Transfer Bank',
            self::TYPE_VIRTUAL_ACCOUNT => 'Virtual Account',
            self::TYPE_E_WALLET => 'E-Wallet',
            self::TYPE_CREDIT_CARD => 'Kartu Kredit',
            self::TYPE_OTHER => 'Lainnya',
        ];
    }

    // Relationships

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
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

    // Helpers

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }
}
