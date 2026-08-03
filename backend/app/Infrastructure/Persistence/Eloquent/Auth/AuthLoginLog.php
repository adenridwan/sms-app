<?php

namespace App\Infrastructure\Persistence\Eloquent\Auth;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat percobaan login (sukses & gagal).
 *
 * Sengaja tidak ber-tenant scope: percobaan login yang gagal bisa datang dari
 * email yang tak cocok user mana pun, jadi tenant belum tentu diketahui.
 */
class AuthLoginLog extends Model
{
    use HasUuid;

    protected $table = 'auth_login_logs';

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'method',
        'successful',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
