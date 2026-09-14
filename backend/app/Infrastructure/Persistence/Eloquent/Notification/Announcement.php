<?php

namespace App\Infrastructure\Persistence\Eloquent\Notification;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'announcements';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'title',
        'content',
        'image',
        'attachments',
        'priority',
        'target_audience',
        'publish_at',
        'expires_at',
        'is_pinned',
        'is_published',
        'send_notification',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'target_audience' => 'array',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'send_notification' => 'boolean',
        ];
    }

    /**
     * Get the user who created the announcement.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get users who have read the announcement.
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Scope for published announcements.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for pinned announcements.
     */
    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for a specific priority.
     */
    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Rendah',
            'normal' => 'Normal',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => ucfirst($this->priority ?? ''),
        };
    }

    /**
     * Check if announcement is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        if (! $this->is_published) {
            return false;
        }

        $now = now();

        if ($this->publish_at && $this->publish_at > $now) {
            return false;
        }

        if ($this->expires_at && $this->expires_at <= $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if a user has read this announcement.
     */
    public function isReadBy(User $user): bool
    {
        return $this->readers()->where('user_id', $user->id)->exists();
    }

    /**
     * Mark announcement as read by a user.
     */
    public function markAsReadBy(User $user): void
    {
        if (! $this->isReadBy($user)) {
            $this->readers()->attach($user->id, ['read_at' => now()]);
        }
    }

    /**
     * Get read count.
     */
    public function getReadCountAttribute(): int
    {
        return $this->readers()->count();
    }
}
