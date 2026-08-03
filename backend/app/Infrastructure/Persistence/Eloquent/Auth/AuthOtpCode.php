<?php

namespace App\Infrastructure\Persistence\Eloquent\Auth;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kode akses sekali-pakai yang digenerate admin untuk membantu user masuk
 * tanpa password (mis. lupa password / device baru). Kode disimpan sebagai
 * hash — nilai polosnya hanya ditampilkan sekali ke admin saat digenerate.
 */
class AuthOtpCode extends Model
{
    use HasUuid;

    protected $table = 'auth_otp_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'used_at',
        'generated_by',
        'attempts',
    ];

    /** Jangan pernah ikut terserialisasi ke respons API/Inertia. */
    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
