<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\System\Setting;
use App\Support\EnvFileWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PDO;
use PDOException;

/**
 * Lihat/ubah ke mana koneksi database aplikasi (.env DB_*) dari UI —
 * digabung ke menu Backup Database (super_admin saja). Sengaja di-gate
 * password TERPISAH dari password login (lihat CLAUDE.md), disimpan di
 * tabel `settings` yang sudah ada (group=security), bukan tabel baru.
 *
 * Baik lihat (reveal/test) maupun ubah (update) WAJIB mengirim access_password
 * yang benar di tiap request — tidak ada sesi "unlock" tersendiri di server,
 * supaya tidak ada state tambahan yang bisa lupa di-lock lagi.
 */
class DatabaseConnectionController extends ApiController
{
    private const SETTING_GROUP = 'security';

    private const SETTING_KEY = 'db_config_access_password';

    public function accessStatus(): JsonResponse
    {
        return $this->success([
            'configured' => Setting::getGlobal(self::SETTING_GROUP, self::SETTING_KEY) !== null,
        ]);
    }

    /**
     * Versi aplikasi yang ditampilkan di footer (HandleInertiaRequests
     * membaca Setting::getGlobal('app','version') di setiap request). Bukan
     * kredensial DB — sengaja TIDAK digerbangi access_password seperti
     * reveal/test/update di atas, cukup role:super_admin (middleware route).
     */
    public function updateAppVersion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:50'],
        ]);

        Setting::setGlobal('app', 'version', $data['version'], 'Versi aplikasi yang ditampilkan di footer.');

        return $this->success(['version' => $data['version']], 'Versi aplikasi berhasil disimpan');
    }

    public function setAccessPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['nullable', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
            'new_password_confirmation' => ['required', 'string', 'same:new_password'],
        ]);

        $existingHash = Setting::getGlobal(self::SETTING_GROUP, self::SETTING_KEY);

        if ($existingHash !== null) {
            if (! Hash::check($data['current_password'] ?? '', $existingHash)) {
                return $this->error('Password akses saat ini salah.', 422);
            }
        }

        Setting::setGlobal(
            self::SETTING_GROUP,
            self::SETTING_KEY,
            Hash::make($data['new_password']),
            'Password akses untuk melihat/mengubah koneksi database aplikasi (menu Backup Database).',
        );

        return $this->success(null, $existingHash === null ? 'Password akses berhasil dibuat.' : 'Password akses berhasil diubah.');
    }

    public function reveal(Request $request): JsonResponse
    {
        $error = $this->verifyAccessPassword($request);
        if ($error) {
            return $error;
        }

        $config = config('database.connections.pgsql');

        return $this->success([
            'connection' => config('database.default'),
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $error = $this->verifyAccessPassword($request);
        if ($error) {
            return $error;
        }

        $data = $this->validateConnectionInput($request);
        $result = $this->attemptConnection($data);

        return $result === null
            ? $this->success(null, 'Koneksi berhasil.')
            : $this->error("Koneksi gagal: {$result}", 422);
    }

    public function update(Request $request): JsonResponse
    {
        $error = $this->verifyAccessPassword($request);
        if ($error) {
            return $error;
        }

        $data = $this->validateConnectionInput($request);
        $connectionError = $this->attemptConnection($data);
        if ($connectionError !== null) {
            return $this->error("Koneksi gagal, .env TIDAK diubah: {$connectionError}", 422);
        }

        $writer = new EnvFileWriter(config('backup.env_file'));
        $writer->update([
            'DB_HOST' => $data['host'],
            'DB_PORT' => (string) $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['username'],
            'DB_PASSWORD' => $data['password'],
        ]);

        // SENGAJA TIDAK memanggil Artisan::call('config:clear') di sini.
        // Memanggil command yang me-reload config (LoadConfiguration bootstrapper)
        // di tengah request yang sesi/auth-nya masih dipakai bisa merusak state
        // container untuk sisa request ini (termasuk penyimpanan sesi saat
        // terminate()) — pernah menyebabkan super admin ter-logout langsung
        // setelah klik Simpan. .env sudah dibaca ulang otomatis di request
        // BERIKUTNYA (tidak ada config cache aktif di dev); kalau nanti
        // `php artisan config:cache` pernah dijalankan di server ini, hapus
        // manual bootstrap/cache/config.php setelah mengubah koneksi.
        if (File::exists(base_path('bootstrap/cache/config.php'))) {
            File::delete(base_path('bootstrap/cache/config.php'));
        }

        return $this->success(
            null,
            'Koneksi database berhasil diubah. Backup .env sebelumnya tersimpan di .env.bak. '
                . 'Sesi Anda saat ini mungkin akan berakhir jika database yang dituju berbeda — login ulang bila diminta.',
        );
    }

    private function verifyAccessPassword(Request $request): ?JsonResponse
    {
        $accessPassword = (string) $request->input('access_password', '');
        $hash = Setting::getGlobal(self::SETTING_GROUP, self::SETTING_KEY);

        if ($hash === null) {
            return $this->error('Password akses belum dibuat. Buat dulu password akses sebelum melihat/mengubah koneksi database.', 422);
        }

        if (! Hash::check($accessPassword, $hash)) {
            return $this->error('Password akses salah.', 403);
        }

        return null;
    }

    /**
     * @return array{host:string,port:int,database:string,username:string,password:string}
     */
    private function validateConnectionInput(Request $request): array
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            // Kosong = pertahankan password DB yang aktif sekarang (tidak diubah).
            'password' => ['nullable', 'string'],
        ]);

        if (empty($data['password'])) {
            $data['password'] = config('database.connections.pgsql.password');
        }

        return $data;
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string} $data
     * @return string|null Pesan error, atau null bila berhasil connect.
     */
    private function attemptConnection(array $data): ?string
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=5', $data['host'], $data['port'], $data['database']);

        try {
            new PDO($dsn, $data['username'], $data['password']);

            return null;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }
}
