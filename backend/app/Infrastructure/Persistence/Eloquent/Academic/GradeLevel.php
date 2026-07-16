<?php

namespace App\Infrastructure\Persistence\Eloquent\Academic;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GradeLevel extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'grade_levels';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'grade_level_id');
    }
}
