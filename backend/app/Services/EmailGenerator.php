<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\System\Setting;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pembuat email login otomatis untuk akun siswa & guru.
 * Rancangan lengkap: docs/EMAIL-OTOMATIS-AKUN.md.
 *
 * Email di aplikasi ini adalah IDENTITAS LOGIN (Auth::attempt memakai
 * users.email), bukan alamat surat — surat sungguhan dikirim ke
 * users.contact_email. Karena itu alamat sintetis di sini tidak masalah, asalkan
 * unik dan bisa dihitung ulang admin tanpa membuka aplikasi:
 *
 *   siswa : {kata-pertama-nama-depan}.{nis}@{domain}
 *   guru  : {username}@{domain}
 *
 * Siswa memakai NIS karena `students` unik pada (tenant_id, nis) — jadi email
 * pasti unik tanpa perlu angka urut yang tak bisa ditebak siapa pun. Guru tidak
 * bisa memakai pola itu karena `teachers.nip` nullable, jadi ia menumpang
 * `username` yang sudah dijamin unik global oleh registrar.
 */
class EmailGenerator
{
    public const SETTING_GROUP = 'account';

    public const SETTING_KEY = 'email_domain';

    /**
     * Batas panjang bagian nama. Bagian nama murni pengingat (keunikan dipegang
     * NIS/username), jadi dipendekkan agar mudah diketik orang tua di layar
     * login. 20 + 1 + 20 (nis maks 20) = 41, aman di bawah batas 64 karakter
     * local part (RFC 5321).
     */
    private const MAX_NAME_LENGTH = 20;

    /** Batas percobaan sufiks angka bila alamat sudah dipakai akun lain. */
    private const MAX_SUFFIX_ATTEMPTS = 50;

    /**
     * Domain email otomatis milik satu sekolah, atau null bila belum diatur.
     */
    public function domainFor(string $tenantId): ?string
    {
        $domain = Setting::getForTenant($tenantId, self::SETTING_GROUP, self::SETTING_KEY);

        return $domain === null || trim($domain) === '' ? null : strtolower(trim($domain));
    }

    public function hasDomain(string $tenantId): bool
    {
        return $this->domainFor($tenantId) !== null;
    }

    /**
     * Bagian nama pada local part: kata sebelum spasi pertama, ASCII, huruf
     * kecil, tanpa karakter selain a-z0-9, maksimum 20 karakter.
     *
     * "Ahmad Ridwan" → "ahmad"; "Al-Fatih" → "alfatih"; "王" → $fallback.
     */
    public function namePart(?string $firstName, string $fallback = 'siswa'): string
    {
        // Pisah pada spasi/tab/baris baru — hanya kata pertama yang dipakai.
        $firstWord = preg_split('/\s+/', trim((string) $firstName), 2)[0] ?? '';

        $slug = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($firstWord))) ?? '';

        if ($slug === '') {
            return $fallback;
        }

        return substr($slug, 0, self::MAX_NAME_LENGTH);
    }

    /**
     * Email login siswa: {kata-pertama-nama-depan}.{nis}@{domain}.
     *
     * @throws RuntimeException bila domain sekolah belum diatur
     */
    public function forStudent(string $tenantId, ?string $firstName, string $nis): string
    {
        return $this->build($tenantId, $this->namePart($firstName, 'siswa') . '.' . $this->digits($nis, 'siswa'));
    }

    /**
     * Email login guru: {username}@{domain}. Username sudah unik global, jadi
     * tidak perlu pembeda tambahan.
     *
     * @throws RuntimeException bila domain sekolah belum diatur
     */
    public function forTeacher(string $tenantId, string $username): string
    {
        $local = preg_replace('/[^a-z0-9.]/', '', strtolower(Str::ascii($username))) ?? '';

        return $this->build($tenantId, trim($local, '.') ?: 'guru');
    }

    /**
     * Pesan seragam saat domain belum diatur — dipakai form maupun import
     * supaya admin diarahkan ke tempat yang sama.
     */
    public static function domainMissingMessage(): string
    {
        return 'Domain email otomatis belum diatur. Isi dulu di Pengaturan → Umum (Profil Sekolah) sebelum menambahkan akun tanpa email.';
    }

    /**
     * Rakit alamat lengkap dan pastikan belum dipakai akun lain.
     *
     * Pengecekan lintas tenant karena `users.email` unik global. Sufiks angka
     * hanya jaring pengaman — dengan NIS/username sebagai pembeda, bentrok baru
     * mungkin terjadi bila dua sekolah mengetik domain yang sama persis.
     */
    private function build(string $tenantId, string $localPart): string
    {
        $domain = $this->domainFor($tenantId);

        if ($domain === null) {
            throw new RuntimeException(self::domainMissingMessage());
        }

        $email = "{$localPart}@{$domain}";

        for ($suffix = 2; $this->isTaken($email); $suffix++) {
            if ($suffix > self::MAX_SUFFIX_ATTEMPTS) {
                throw new RuntimeException("Gagal membuat email otomatis yang unik untuk '{$localPart}@{$domain}'.");
            }

            $email = "{$localPart}{$suffix}@{$domain}";
        }

        return $email;
    }

    /**
     * withTrashed() WAJIB: index UNIQUE Postgres pada users.email ikut
     * menghitung baris yang ter-soft-delete. Siswa yang pernah dihapus lalu
     * diimpor ulang dengan NIS yang sama akan menghasilkan alamat yang persis
     * sama — tanpa ini alamat itu dikira bebas dan insert-nya ditolak.
     */
    private function isTaken(string $email): bool
    {
        return User::withoutTenant()->withTrashed()->where('email', $email)->exists();
    }

    /**
     * NIS/NIP kadang terbaca sebagai "2024001.0" atau memuat spasi dari file
     * Excel — sisakan angka & hurufnya saja supaya alamatnya tetap sah.
     */
    private function digits(string $identifier, string $fallback): string
    {
        $clean = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($identifier))) ?? '';

        return $clean !== '' ? $clean : $fallback;
    }
}
