<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'books';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'shelf_id',
        'publisher_id',
        'title',
        'isbn',
        'edition',
        'publish_year',
        'language',
        'pages',
        'description',
        'cover_image',
        'total_copies',
        'available_copies',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'publish_year' => 'integer',
            'pages' => 'integer',
            'total_copies' => 'integer',
            'available_copies' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    /**
     * Get the category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'category_id');
    }

    /**
     * Get the shelf.
     */
    public function shelf(): BelongsTo
    {
        return $this->belongsTo(BookShelf::class, 'shelf_id');
    }

    /**
     * Get the publisher.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class, 'publisher_id');
    }

    /**
     * Get book copies.
     */
    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'book_id');
    }

    /**
     * Get authors.
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_authors')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get reservations.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class, 'book_id');
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Tersedia',
            'unavailable' => 'Tidak Tersedia',
            'damaged' => 'Rusak',
            'lost' => 'Hilang',
            default => ucfirst($this->status ?? ''),
        };
    }

    /**
     * Get cover image URL.
     */
    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }

    /**
     * Update available copies count.
     */
    public function updateAvailableCopies(): void
    {
        $this->available_copies = $this->copies()
            ->where('status', 'available')
            ->count();
        $this->save();
    }
}
