<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'publishers';

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'phone',
        'email',
        'website',
    ];

    /**
     * Get books from this publisher.
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'publisher_id');
    }

    /**
     * Get book count.
     */
    public function getBookCountAttribute(): int
    {
        return $this->books()->count();
    }
}
