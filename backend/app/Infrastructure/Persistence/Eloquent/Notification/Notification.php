<?php

namespace App\Infrastructure\Persistence\Eloquent\Notification;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi in-app milik seorang user (tabel `notifications`).
 *
 * Tenant di-scope otomatis lewat [BelongsToTenant]; kepemilikan per-user
 * TIDAK otomatis — controller wajib memfilter `user_id` sendiri agar user
 * tak bisa membaca/menghapus notifikasi milik orang lain di tenant yang sama.
 */
class Notification extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'notifications';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'title',
        'body',
        'icon',
        'action_url',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
