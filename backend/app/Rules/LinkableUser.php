<?php

namespace App\Rules;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memastikan sebuah akun pengguna boleh ditautkan ke data master (guru, staf,
 * atau siswa) yang sedang dibuat.
 *
 * Latar belakangnya: menu Pengguna dan menu data master membaca tabel berbeda
 * — Pengguna dari `users`, Daftar Guru dari `teachers`, dan seterusnya. Sebuah
 * akun bisa bertipe `teacher` tanpa pernah punya baris di `teachers`, sehingga
 * muncul di satu menu tapi hilang di menu lainnya. Form data master sebelumnya
 * SELALU membuat akun baru, jadi satu-satunya cara "memunculkan" orang itu
 * adalah membuat akun kedua yang duplikat. Aturan ini menjaga jalur penautan
 * yang baru supaya tidak menimbulkan masalah baru.
 */
class LinkableUser implements ValidationRule
{
    /**
     * @param  string|null  $tenantId  Sekolah aktif; akun dari sekolah lain ditolak.
     * @param  string  $expectedUserType  Nilai `users.user_type` yang diharapkan.
     * @param  string  $relationTable  Tabel data master ('teachers'/'staff'/'students').
     * @param  string  $label  Sebutan untuk pesan error ('guru'/'staf'/'siswa').
     */
    public function __construct(
        private ?string $tenantId,
        private string $expectedUserType,
        private string $relationTable,
        private string $label,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // withoutGlobalScopes(): super admin tidak terikat tenant, sedangkan
        // scope bawaan menyaring berdasarkan tenant aktif. Kepemilikan tenant
        // tetap diperiksa manual di bawah — tidak dilewatkan, hanya dipindah.
        $user = User::withoutGlobalScopes()->find($value);

        if (! $user) {
            $fail('Akun pengguna tidak ditemukan.');

            return;
        }

        if ($user->deleted_at !== null) {
            $fail('Akun pengguna tersebut sudah dihapus.');

            return;
        }

        if ($this->tenantId !== null && $user->tenant_id !== $this->tenantId) {
            $fail('Akun pengguna tersebut milik sekolah lain.');

            return;
        }

        if ($user->user_type !== $this->expectedUserType) {
            $fail("Akun pengguna tersebut bukan bertipe {$this->label}.");

            return;
        }

        if ($this->alreadyLinked($value)) {
            $fail("Akun pengguna tersebut sudah terdaftar sebagai {$this->label}.");
        }
    }

    /**
     * Sudah punya baris di tabel data master? Baris yang ter-soft-delete tidak
     * dihitung supaya akun yang datanya pernah dihapus bisa didaftarkan lagi.
     */
    private function alreadyLinked(string $userId): bool
    {
        $query = DB::table($this->relationTable)->where('user_id', $userId);

        if (Schema::hasColumn($this->relationTable, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }
}
