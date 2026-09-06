<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScannerDevice extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'scanner_devices';

    /** Status berdasarkan heartbeat */
    public const STATUS_ONLINE = 'online';   // < 2 menit
    public const STATUS_IDLE = 'idle';       // 2-15 menit
    public const STATUS_OFFLINE = 'offline'; // > 15 menit

    protected $fillable = [
        'tenant_id',
        'user_id',
        'device_name',
        'device_token',
        'location',
        'is_active',
        'last_scan_at',
        'last_heartbeat_at',
        'app_version',
        'os_version',
        'device_model',
        'battery_level',
        'battery_charging',
        'network_type',
        'network_name',
        'latency_ms',
        'pending_sync_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scan_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'battery_level' => 'integer',
            'battery_charging' => 'boolean',
            'latency_ms' => 'integer',
            'pending_sync_count' => 'integer',
        ];
    }

    protected $appends = ['connection_status', 'last_seen_text'];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->device_token)) {
                $model->device_token = Str::random(64);
            }
        });
    }

    /**
     * User yang sedang login di device ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get offline scan queue for this device
     */
    public function offlineScans(): HasMany
    {
        return $this->hasMany(OfflineScanQueue::class, 'scanner_device_id');
    }

    /**
     * Update last scan timestamp
     */
    public function recordScan(): void
    {
        $this->update(['last_scan_at' => now()]);
    }

    /**
     * Update heartbeat dari device.
     */
    public function recordHeartbeat(array $data = []): void
    {
        $this->update(array_merge($data, [
            'last_heartbeat_at' => now(),
        ]));
    }

    /**
     * Status koneksi berdasarkan last_heartbeat_at.
     */
    public function getConnectionStatusAttribute(): string
    {
        if (!$this->last_heartbeat_at) {
            return self::STATUS_OFFLINE;
        }

        $minutes = $this->last_heartbeat_at->diffInMinutes(now());

        if ($minutes < 2) {
            return self::STATUS_ONLINE;
        }

        if ($minutes < 15) {
            return self::STATUS_IDLE;
        }

        return self::STATUS_OFFLINE;
    }

    /**
     * Text "terakhir terlihat" yang human-readable.
     */
    public function getLastSeenTextAttribute(): ?string
    {
        if (!$this->last_heartbeat_at) {
            return null;
        }

        return $this->last_heartbeat_at->diffForHumans();
    }

    /**
     * Scope to filter active devices
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk device yang online (heartbeat < 2 menit).
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('last_heartbeat_at', '>=', now()->subMinutes(2));
    }

    /**
     * Scope untuk device yang idle (heartbeat 2-15 menit).
     */
    public function scopeIdle(Builder $query): Builder
    {
        return $query->whereBetween('last_heartbeat_at', [
            now()->subMinutes(15),
            now()->subMinutes(2),
        ]);
    }

    /**
     * Scope untuk device yang offline (heartbeat > 15 menit atau null).
     */
    public function scopeOffline(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('last_heartbeat_at')
              ->orWhere('last_heartbeat_at', '<', now()->subMinutes(15));
        });
    }

    /**
     * Scope untuk device dengan antrean pending.
     */
    public function scopeHasPending(Builder $query): Builder
    {
        return $query->where('pending_sync_count', '>', 0);
    }

    /**
     * Find device by token
     */
    public static function findByToken(string $token): ?static
    {
        return static::where('device_token', $token)->first();
    }
}
