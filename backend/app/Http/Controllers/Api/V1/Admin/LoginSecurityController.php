<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Auth\Services\LoginLogService;
use App\Domain\Auth\Services\OtpService;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menu admin "Keamanan Login": riwayat login, generate kode akses sekali-pakai,
 * dan reset sesi (cabut token) milik seorang user.
 */
class LoginSecurityController extends ApiController
{
    public function __construct(
        protected LoginLogService $loginLog,
        protected OtpService $otp,
    ) {}

    /**
     * GET /admin/login-security/users?search=
     *
     * Pencarian user seadanya untuk halaman ini saja. Sengaja tidak memakai
     * /admin/users: endpoint itu khusus super admin dan mengembalikan seluruh
     * profil, sedangkan admin di sini hanya perlu memilih siapa yang diberi
     * kode akses atau dicabut sesinya.
     */
    public function users(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        if (mb_strlen($search) < 2) {
            return $this->success(['data' => []]);
        }

        $term = '%' . $search . '%';

        // Query lewat kelas dasar, BUKAN App\Models\User: relasi role bersifat
        // polymorphic dan model_has_roles.model_type menyimpan nama kelas dasar,
        // sehingga subclass selalu mengembalikan roles kosong.
        $users = \App\Infrastructure\Persistence\Eloquent\Auth\User::query()
            ->with(['roles:id,name', 'profile'])
            ->leftJoin('user_profiles as up', 'users.id', '=', 'up.user_id')
            ->where(function ($q) use ($term) {
                $q->where('users.username', 'ilike', $term)
                    ->orWhere('users.email', 'ilike', $term)
                    ->orWhere('up.first_name', 'ilike', $term)
                    ->orWhere('up.last_name', 'ilike', $term)
                    ->orWhereRaw("concat(up.first_name, ' ', up.last_name) ilike ?", [$term]);
            })
            ->select('users.*')
            ->orderBy('users.username')
            ->limit((int) min($request->input('per_page', 10), 25))
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        return $this->success(['data' => $users]);
    }

    /** GET /admin/login-security/logs */
    public function logs(Request $request): JsonResponse
    {
        $filters = $request->only(['user_id', 'email', 'method', 'from_date', 'to_date']);

        if ($request->filled('successful')) {
            $filters['successful'] = $request->boolean('successful');
        }

        return $this->success($this->loginLog->paginate($filters, (int) $request->input('per_page', 25)));
    }

    /**
     * POST /admin/login-security/users/{user}/otp
     *
     * Kode polos hanya dikembalikan sekali di sini — admin membacakannya ke
     * user lewat kanal luar (telepon/WA). Tidak disimpan dalam bentuk polos.
     */
    public function generateOtp(Request $request, User $user): JsonResponse
    {
        $code = $this->otp->generateFor($user, $request->user());

        return $this->success([
            'code' => $code,
            'expires_in_minutes' => OtpService::TTL_MINUTES,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
        ], 'Kode akses dibuat. Berlaku ' . OtpService::TTL_MINUTES . ' menit dan hanya bisa dipakai sekali.');
    }

    /** DELETE /admin/login-security/users/{user}/otp — cabut kode aktif. */
    public function revokeOtp(User $user): JsonResponse
    {
        $revoked = $this->otp->revokeAllFor($user);

        return $this->success(['revoked' => $revoked], 'Kode akses aktif dicabut.');
    }

    /**
     * POST /admin/login-security/users/{user}/revoke-sessions
     *
     * Cabut semua token Sanctum user (semua perangkat ter-logout). Dipakai saat
     * perangkat hilang atau akses disalahgunakan.
     */
    public function revokeSessions(User $user): JsonResponse
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        return $this->success(['revoked' => $count], 'Semua sesi perangkat user dicabut.');
    }
}
