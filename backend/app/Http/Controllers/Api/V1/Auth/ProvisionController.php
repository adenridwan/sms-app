<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Domain\Auth\Services\LoginLogService;
use App\Infrastructure\Persistence\Eloquent\Auth\ProvisionToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Provisioning via QR Code.
 *
 * Admin men-generate token sementara (15 menit) untuk user tertentu. User
 * scan QR berisi token tersebut di app mobile, lalu dapat JWT + data user
 * tanpa perlu ketik email/password — cocok untuk onboarding device baru
 * tanpa koneksi stabil atau saat lupa password.
 */
class ProvisionController extends ApiController
{
    /** Token expires in 15 minutes. */
    private const TOKEN_LIFETIME_MINUTES = 15;

    public function __construct(
        protected LoginLogService $loginLog,
    ) {}

    /**
     * Generate provision token untuk user tertentu.
     *
     * POST /api/v1/auth/provision
     * Body: { user_id: uuid }
     *
     * Hanya admin/TU yang bisa generate. Token berlaku 15 menit dan sekali
     * pakai. Kalau user sudah punya token aktif, yang lama di-revoke.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $userId = $request->input('user_id');
        $user = User::findOrFail($userId);

        // Validasi user yang di-provision harus aktif
        if ($user->status !== 'active') {
            return $this->error(
                'Tidak dapat membuat token untuk akun yang tidak aktif.',
                422
            );
        }

        // Revoke token aktif sebelumnya (kalau ada)
        ProvisionToken::where('user_id', $userId)
            ->redeemable()
            ->update(['redeemed_at' => now()]);

        // Generate token baru
        $plainToken = Str::random(64);
        $expiresAt = now()->addMinutes(self::TOKEN_LIFETIME_MINUTES);

        $provisionToken = ProvisionToken::create([
            'user_id' => $userId,
            'token' => $plainToken,
            'expires_at' => $expiresAt,
            'created_by' => $request->user()->id,
        ]);

        return $this->success([
            'provision_token' => $plainToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_minutes' => self::TOKEN_LIFETIME_MINUTES,
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ],
            // Deep link untuk QR code
            'qr_content' => 'smsapp://provision?token=' . $plainToken,
        ], 'Token provisioning berhasil dibuat.');
    }

    /**
     * Redeem provision token dan dapatkan JWT.
     *
     * POST /api/v1/auth/redeem-provision
     * Body: { provision_token: string }
     *
     * Endpoint ini PUBLIC (tanpa auth) karena user belum login. Setelah
     * redeem berhasil, user dapat JWT yang bisa dipakai untuk request
     * selanjutnya.
     */
    public function redeem(Request $request): JsonResponse
    {
        $request->validate([
            'provision_token' => 'required|string|size:64',
        ]);

        $plainToken = $request->input('provision_token');

        // Cari token yang masih valid
        $provisionToken = ProvisionToken::where('token', $plainToken)
            ->redeemable()
            ->first();

        if (!$provisionToken) {
            $this->loginLog->record($request, null, null, 'provision', false, 'invalid_token');
            return $this->unauthorized('Token tidak valid atau sudah kedaluwarsa.');
        }

        $user = $provisionToken->user;

        // Cek status user
        if ($user->status !== 'active') {
            $this->loginLog->record($request, $user, $user->email, 'provision', false, 'inactive_account');
            $provisionToken->markRedeemed();
            return $this->forbidden('Akun tidak aktif. Silakan hubungi administrator.');
        }

        // Tandai token sebagai sudah dipakai
        $provisionToken->markRedeemed();

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Catat login sukses
        $this->loginLog->record($request, $user, $user->email, 'provision', true);

        // Buat JWT token
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load(['profile', 'roles', 'permissions'])),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login berhasil.');
    }

    /**
     * Lihat daftar token provisioning yang pernah dibuat untuk user tertentu.
     *
     * GET /api/v1/auth/provision/{user}
     *
     * Untuk audit trail oleh admin.
     */
    public function history(User $user): JsonResponse
    {
        $tokens = ProvisionToken::where('user_id', $user->id)
            ->with('createdBy:id,username,email')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'created_at' => $t->created_at->toIso8601String(),
                'expires_at' => $t->expires_at->toIso8601String(),
                'redeemed_at' => $t->redeemed_at?->toIso8601String(),
                'is_active' => $t->redeemed_at === null && $t->expires_at->isFuture(),
                'created_by' => $t->createdBy ? [
                    'id' => $t->createdBy->id,
                    'name' => $t->createdBy->username,
                ] : null,
            ]);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
            ],
            'tokens' => $tokens,
        ]);
    }
}
