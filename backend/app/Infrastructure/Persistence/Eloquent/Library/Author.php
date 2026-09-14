<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'authors';

    protected $fillable = [
        'tenant_id',
        'name',
        'biography',
        'nationality',
    ];

    /**
     * Get books by this author.
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_authors')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get book count.
     */
    public function getBookCountAttribute(): int
    {
        return $this->books()->count();
    }
}
