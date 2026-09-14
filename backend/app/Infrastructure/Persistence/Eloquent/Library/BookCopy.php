<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCopy extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'book_copies';

    protected $fillable = [
        'tenant_id',
        'book_id',
        'copy_number',
        'barcode',
        'condition',
        'status',
        'acquisition_date',
        'acquisition_source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
        ];
    }

    /**
     * Get the book.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    /**
     * Get loans for this copy.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class, 'book_copy_id');
    }

    /**
     * Get current active loan.
     */
    public function activeLoan()
    {
        return $this->loans()->whereIn('status', ['borrowed', 'overdue'])->first();
    }

    /**
     * Get condition label.
     */
    public function getConditionLabelAttribute(): string
    {
        return match ($this->condition) {
            'good' => 'Baik',
            'fair' => 'Cukup',
            'poor' => 'Kurang',
            'damaged' => 'Rusak',
            'lost' => 'Hilang',
            default => ucfirst($this->condition ?? ''),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Tersedia',
            'borrowed' => 'Dipinjam',
            'reserved' => 'Dipesan',
            'maintenance' => 'Perawatan',
            'lost' => 'Hilang',
            default => ucfirst($this->status ?? ''),
        };
    }

    /**
     * Check if copy is available.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
