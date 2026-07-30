<?php

namespace App\Infrastructure\Persistence\Eloquent\Staff;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'department_id',
        'position_id',
        'employee_id',
        'join_date',
        'employment_status',
        'education_level',
        'status',
        'additional_info',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'additional_info' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
