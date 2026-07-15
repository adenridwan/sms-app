<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Domain\Attendance\Enums\ScanType;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineScanQueue extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'offline_scan_queue';

    protected $fillable = [
        'tenant_id',
        'scanner_device_id',
        'unique_code',
        'scan_type',
        'scanned_at',
        'sync_status',
        'sync_error',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_type' => ScanType::class,
            'scanned_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Get the scanner device
     */
    public function scannerDevice(): BelongsTo
    {
        return $this->belongsTo(ScannerDevice::class, 'scanner_device_id');
    }

    /**
     * Mark as synced
     */
    public function markAsSynced(): void
    {
        $this->update([
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'failed',
            'sync_error' => $error,
        ]);
    }

    /**
     * Scope to filter pending scans
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * Scope to filter failed scans
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('sync_status', 'failed');
    }
}
