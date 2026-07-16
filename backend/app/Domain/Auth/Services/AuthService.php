<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthService
{
    /**
     * Register a new user account.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'tenant_id' => tenant_id(),
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'active',
                'user_type' => $data['user_type'] ?? 'student',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            return $user->load('profile');
        });
    }
}
