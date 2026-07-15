<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Domain\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends ApiController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Login user and create token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return $this->unauthorized('Email atau password salah.');
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            return $this->forbidden('Akun Anda tidak aktif. Silakan hubungi administrator.');
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load('profile')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login berhasil.');
    }

    /**
     * Register new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->created([
            'user' => new UserResource($user->load('profile')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registrasi berhasil.');
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
