<?php

namespace App\Infrastructure\Persistence\Eloquent\Report;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use App\Infrastructure\Persistence\Eloquent\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'generated_by',
        'name',
        'type',
        'parameters',
        'format',
        'file_path',
        'file_size',
        'status',
        'error_message',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'file_size' => 'integer',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'is_downloadable'];

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED => 'Dalam Antrean',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_FAILED => 'Gagal',
    ];

    public const TYPES = [
        'attendance' => 'Laporan Absensi',
        'finance' => 'Laporan Keuangan',
        'academic' => 'Laporan Akademik',
        'student' => 'Laporan Siswa',
        'teacher' => 'Laporan Guru',
        'report_card' => 'Rapor',
    ];

    public const FORMATS = [
        'pdf' => 'PDF',
        'excel' => 'Excel',
        'csv' => 'CSV',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getIsDownloadableAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDownloadable($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->whereNotNull('file_path')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    public function markAsProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->save();
    }

    public function markAsCompleted(string $filePath, int $fileSize): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->file_path = $filePath;
        $this->file_size = $fileSize;
        $this->completed_at = now();
        $this->expires_at = now()->addDays(7); // Reports expire after 7 days
        $this->save();
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->completed_at = now();
        $this->save();
    }
}
