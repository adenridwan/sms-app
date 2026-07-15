<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScannerDevice extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'scanner_devices';

    protected $fillable = [
        'tenant_id',
        'device_name',
        'device_token',
        'location',
        'is_active',
        'last_scan_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scan_at' => 'datetime',
        ];
    }

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
     * Scope to filter active devices
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Find device by token
     */
    public static function findByToken(string $token): ?static
    {
        return static::where('device_token', $token)->first();
    }
}
