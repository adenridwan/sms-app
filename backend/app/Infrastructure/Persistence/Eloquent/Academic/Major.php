<?php

namespace App\Infrastructure\Persistence\Eloquent\Academic;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Major extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'majors';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'major_id');
    }
}
