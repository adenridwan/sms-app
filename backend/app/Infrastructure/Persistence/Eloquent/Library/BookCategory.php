<?php

namespace App\Infrastructure\Persistence\Eloquent\Library;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookCategory extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'book_categories';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'code',
        'description',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'parent_id');
    }

    /**
     * Get child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(BookCategory::class, 'parent_id');
    }

    /**
     * Get books in this category.
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'category_id');
    }

    /**
     * Get book count.
     */
    public function getBookCountAttribute(): int
    {
        return $this->books()->count();
    }
}
