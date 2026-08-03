<?php

namespace App\Infrastructure\Persistence\Eloquent\Academic;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'time_slots';

    protected $fillable = [
        'tenant_id',
        'name',
        'start_time',
        'end_time',
        'order',
        'is_break',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_break' => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'time_slot_id');
    }
}
