<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LoginWithOtpRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\LoginLogService;
use App\Domain\Auth\Services\OtpService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends ApiController
{
    public function __construct(
        protected AuthService $authService,
        protected LoginLogService $loginLog,
        protected OtpService $otp,
    ) {}

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $email = (string) $request->input('email');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->loginLog->record($request, null, $email, 'password', false, 'invalid_credentials');
            return $this->unauthorized('Email atau password salah.');
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            $this->loginLog->record($request, $user, $email, 'password', false, 'inactive_account');
            return $this->forbidden('Akun Anda tidak aktif. Silakan hubungi administrator.');
        }

        return $this->issueToken($request, $user, 'password');
    }

    /**
     * Login memakai kode akses sekali-pakai yang digenerate administrator.
     * Dipakai saat user lupa password / masuk di perangkat baru.
     */
    public function loginWithOtp(LoginWithOtpRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $user = User::where('email', $email)->first();

        // Pesan gagal sengaja sama untuk user tak ada / kode salah, agar tidak
        // membocorkan email mana yang terdaftar.
        if (!$user || !$this->otp->verify($user, (string) $request->input('code'))) {
            $this->loginLog->record($request, $user, $email, 'otp', false, 'invalid_otp');
            return $this->unauthorized('Kode akses tidak valid atau sudah kedaluwarsa.');
        }

        if ($user->status !== 'active') {
            $this->loginLog->record($request, $user, $email, 'otp', false, 'inactive_account');
            return $this->forbidden('Akun Anda tidak aktif. Silakan hubungi administrator.');
        }

        return $this->issueToken($request, $user, 'otp');
    }

    /** Catat login sukses, perbarui jejak terakhir, lalu terbitkan token. */
    protected function issueToken(Request $request, $user, string $method): JsonResponse
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->loginLog->record($request, $user, (string) $user->email, $method, true);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load('profile')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login berhasil.');
    }

    /**
     * Register new user.
     *
     * Sengaja TIDAK menerbitkan token: akun lahir berstatus `pending` dan baru
     * bisa dipakai setelah diaktifkan lewat `activate()` memakai kode dari
     * admin (menu Keamanan Login).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return $this->created([
            'user' => new UserResource($user->load('profile')),
            'status' => $user->status,
            'requires_activation' => true,
        ], 'Pendaftaran berhasil. Akun masih menunggu aktivasi — hubungi administrator sekolah untuk mendapatkan kode OTP, lalu masukkan kode tersebut untuk mengaktifkan akun.');
    }

    /**
     * Aktivasi akun `pending` memakai kode sekali-pakai dari admin.
     *
     * Urutan cek disengaja: kode diverifikasi lebih dulu, baru status akun
     * diperiksa. Kalau dibalik, endpoint ini jadi alat untuk menebak email mana
     * yang terdaftar — sama seperti alasan di `loginWithOtp()`.
     */
    public function activate(ActivateAccountRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user || !$this->otp->verify($user, (string) $request->input('code'))) {
            $this->loginLog->record($request, $user, $email, 'activation', false, 'invalid_otp');
            return $this->unauthorized('Kode aktivasi tidak valid atau sudah kedaluwarsa.');
        }

        if ($user->status === 'active') {
            return $this->success(null, 'Akun Anda sudah aktif. Silakan masuk seperti biasa.');
        }

        // Hanya `pending` yang boleh menghidupkan dirinya sendiri. Akun
        // `inactive`/`suspended` dimatikan oleh keputusan admin, jadi tidak
        // boleh hidup lagi sebagai efek samping kode akses.
        if ($user->status !== 'pending') {
            $this->loginLog->record($request, $user, $email, 'activation', false, 'inactive_account');
            return $this->forbidden('Akun Anda tidak dapat diaktifkan sendiri. Silakan hubungi administrator.');
        }

        // `is_active` sengaja tidak ikut di-update: ia atribut turunan dari
        // kolom `status` yang sama (User::getIsActiveAttribute).
        $user->update(['status' => 'active']);

        $this->loginLog->record($request, $user, $email, 'activation', true);

        return $this->success([
            'status' => $user->status,
        ], 'Akun berhasil diaktifkan. Silakan masuk memakai email dan password Anda.');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout berhasil.');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'roles', 'permissions']);

        return $this->success([
            'user' => new UserResource($user),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ]);
    }
}
