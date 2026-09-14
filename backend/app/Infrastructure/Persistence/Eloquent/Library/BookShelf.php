<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookShelf extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'book_shelves';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'location',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * Get books on this shelf.
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'shelf_id');
    }

    /**
     * Get book count.
     */
    public function getBookCountAttribute(): int
    {
        return $this->books()->count();
    }
}
