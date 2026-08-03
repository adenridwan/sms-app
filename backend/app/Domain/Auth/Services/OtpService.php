<?php

namespace App\Domain\Auth\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\AuthOtpCode;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public const CODE_LENGTH = 6;
    public const TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    /**
     * Generate kode baru untuk $user dan batalkan kode aktif sebelumnya.
     * Mengembalikan kode polos — hanya di sini nilai ini pernah ada; setelahnya
     * cuma hash yang tersimpan.
     */
    public function generateFor(User $user, User $admin): string
    {
        AuthOtpCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        AuthOtpCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'generated_by' => $admin->id,
        ]);

        return $code;
    }

    /**
     * Verifikasi kode untuk $user. Mengembalikan true bila cocok & belum
     * kedaluwarsa; kode langsung ditandai terpakai (sekali-pakai).
     */
    public function verify(User $user, string $code): bool
    {
        $record = AuthOtpCode::where('user_id', $user->id)
            ->usable()
            ->latest('created_at')
            ->first();

        if (!$record) {
            return false;
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            // Bakar kode agar tidak bisa di-brute force lebih lanjut.
            $record->update(['used_at' => now()]);
            return false;
        }

        if (!Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            return false;
        }

        $record->update(['used_at' => now()]);

        return true;
    }

    /** Cabut semua kode aktif milik user (dipakai tombol "reset" admin). */
    public function revokeAllFor(User $user): int
    {
        return AuthOtpCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }
}
