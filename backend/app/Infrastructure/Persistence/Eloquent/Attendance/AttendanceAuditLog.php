<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAuditLog extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'attendance_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'aksi',
        'tabel',
        'record_id',
        'data_lama',
        'data_baru',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data_lama' => 'array',
            'data_baru' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    /**
     * Get the user who made the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Create a new audit log entry
     */
    public static function log(
        string $aksi,
        string $tabel,
        ?string $recordId = null,
        ?array $dataLama = null,
        ?array $dataBaru = null
    ): static {
        return static::create([
            'user_id' => auth()->id(),
            'aksi' => $aksi,
            'tabel' => $tabel,
            'record_id' => $recordId,
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
            'ip_address' => request()->ip() ?? '0.0.0.0',
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Scope to filter by action
     */
    public function scopeForAction(Builder $query, string $aksi): Builder
    {
        return $query->where('aksi', $aksi);
    }

    /**
     * Scope to filter by table
     */
    public function scopeForTable(Builder $query, string $tabel): Builder
    {
        return $query->where('tabel', $tabel);
    }

    /**
     * Scope to filter by record
     */
    public function scopeForRecord(Builder $query, string $recordId): Builder
    {
        return $query->where('record_id', $recordId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
