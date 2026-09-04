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

    // Employment status constants
    public const EMPLOYMENT_PERMANENT = 'permanent';
    public const EMPLOYMENT_CONTRACT = 'contract';
    public const EMPLOYMENT_HONORARY = 'honorary';
    public const EMPLOYMENT_PART_TIME = 'part_time';

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_TERMINATED = 'terminated';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function employmentStatusLabel(): string
    {
        return match ($this->employment_status) {
            self::EMPLOYMENT_PERMANENT => 'Tetap',
            self::EMPLOYMENT_CONTRACT => 'Kontrak',
            self::EMPLOYMENT_HONORARY => 'Honorer',
            self::EMPLOYMENT_PART_TIME => 'Paruh Waktu',
            default => '-',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_INACTIVE => 'Tidak Aktif',
            self::STATUS_ON_LEAVE => 'Cuti',
            self::STATUS_RETIRED => 'Pensiun',
            self::STATUS_TERMINATED => 'Berhenti',
            default => '-',
        };
    }

    public static function employmentStatuses(): array
    {
        return [
            self::EMPLOYMENT_PERMANENT => 'Tetap',
            self::EMPLOYMENT_CONTRACT => 'Kontrak',
            self::EMPLOYMENT_HONORARY => 'Honorer',
            self::EMPLOYMENT_PART_TIME => 'Paruh Waktu',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_INACTIVE => 'Tidak Aktif',
            self::STATUS_ON_LEAVE => 'Cuti',
            self::STATUS_RETIRED => 'Pensiun',
            self::STATUS_TERMINATED => 'Berhenti',
        ];
    }
}
