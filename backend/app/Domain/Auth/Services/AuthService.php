<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthService
{
    /**
     * Register a new user account.
     *
     * Akun dibuat berstatus `pending` dan TIDAK bisa login: pendaftaran mandiri
     * lewat /register terbuka untuk publik, jadi aktivasinya harus lewat
     * persetujuan manusia. Admin membuat kode aktivasi di menu Keamanan Login,
     * membacakannya ke pendaftar, lalu pendaftar memasukkannya di
     * `AuthController::activate()`. Sengaja tidak assign role apa pun —
     * penentuan role tetap wewenang admin lewat menu Pengguna.
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
                // Jangan tambahkan 'is_active' di sini: itu atribut virtual yang
                // mutator-nya menimpa 'status' jadi active/inactive, sehingga
                // status 'pending' akan hilang (lihat User::setIsActiveAttribute).
                'status' => 'pending',
                'user_type' => $data['user_type'] ?? 'student',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            return $user->load('profile');
        });
    }
}
