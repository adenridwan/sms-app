<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookReservation extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'book_reservations';

    protected $fillable = [
        'tenant_id',
        'library_member_id',
        'book_id',
        'reservation_date',
        'expiry_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Get the member.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(LibraryMember::class, 'library_member_id');
    }

    /**
     * Get the book.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu',
            'ready' => 'Siap Diambil',
            'fulfilled' => 'Terpenuhi',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kadaluarsa',
            default => ucfirst($this->status ?? ''),
        };
    }

    /**
     * Check if reservation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date->isPast() && $this->status === 'pending';
    }
}
