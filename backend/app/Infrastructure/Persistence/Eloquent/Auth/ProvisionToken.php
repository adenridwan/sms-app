<?php

namespace App\Infrastructure\Persistence\Eloquent\Auth;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token provisioning untuk login via QR code. Admin men-generate token
 * sementara (15 menit), user scan QR di app mobile, lalu dapat JWT +
 * data user tanpa perlu ketik email/password.
 */
class ProvisionToken extends Model
{
    use HasUuid;

    protected $table = 'provision_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'redeemed_at',
        'created_by',
    ];

    /** Token tidak boleh bocor ke respons API. */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * Token yang masih bisa di-redeem: belum dipakai dan belum expired.
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->whereNull('redeemed_at')->where('expires_at', '>', now());
    }

    /**
     * User yang akan di-provision (pemilik token).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin yang men-generate token ini.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tandai token sebagai sudah digunakan.
     */
    public function markRedeemed(): void
    {
        $this->update(['redeemed_at' => now()]);
    }
}
