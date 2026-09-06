<?php

namespace App\Infrastructure\Persistence\Eloquent\Staff;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'positions';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'position_id');
    }
}
