<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LibrarySetting extends Model
{
    use HasUuid;

    protected $table = 'library_settings';

    protected $fillable = [
        'tenant_id',
        'default_loan_days',
        'max_loan_days',
        'max_extensions',
        'extension_days',
        'daily_fine',
        'max_fine',
        'lost_book_multiplier',
        'allow_reservations',
        'reservation_expiry_days',
        'operating_hours',
    ];

    protected function casts(): array
    {
        return [
            'default_loan_days' => 'integer',
            'max_loan_days' => 'integer',
            'max_extensions' => 'integer',
            'extension_days' => 'integer',
            'daily_fine' => 'decimal:2',
            'max_fine' => 'decimal:2',
            'lost_book_multiplier' => 'decimal:2',
            'allow_reservations' => 'boolean',
            'reservation_expiry_days' => 'integer',
            'operating_hours' => 'array',
        ];
    }

    /**
     * Get or create settings for a tenant.
     */
    public static function getOrCreate(string $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'default_loan_days' => 7,
                'max_loan_days' => 14,
                'max_extensions' => 2,
                'extension_days' => 7,
                'daily_fine' => 500.00,
                'max_fine' => 50000.00,
                'lost_book_multiplier' => 2.00,
                'allow_reservations' => true,
                'reservation_expiry_days' => 3,
            ]
        );
    }
}
