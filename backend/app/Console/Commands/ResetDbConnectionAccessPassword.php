<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\System\Setting;
use Illuminate\Console\Command;

/**
 * "Lupa password akses" untuk menu Backup Database > Koneksi Database
 * Aplikasi. Password itu disimpan sebagai HASH bcrypt (tabel `settings`,
 * group=security) — satu arah, tidak bisa "dibongkar" balik ke teks asli.
 * Satu-satunya jalan pulih adalah hapus setting-nya lewat akses langsung ke
 * database (command ini), lalu buat password akses baru dari UI.
 */
class ResetDbConnectionAccessPassword extends Command
{
    protected $signature = 'db-connection:reset-access-password {--force : Lewati konfirmasi}';

    protected $description = 'Hapus password akses "Koneksi Database Aplikasi" (menu Backup Database) supaya bisa dibuat ulang';

    private const GROUP = 'security';

    private const KEY = 'db_config_access_password';

    public function handle(): int
    {
        $setting = Setting::whereNull('tenant_id')
            ->where('group', self::GROUP)
            ->where('key', self::KEY)
            ->first();

        if (! $setting) {
            $this->info('Password akses belum pernah dibuat — tidak ada yang perlu direset.');

            return self::SUCCESS;
        }

        $this->warn('Ini akan menghapus password akses "Koneksi Database Aplikasi" saat ini.');
        $this->line('Setelah ini, menu Backup Database akan menganggap password akses belum pernah dibuat,');
        $this->line('dan Anda bisa membuat yang baru dari tombol "Buat Password Akses" tanpa perlu password lama.');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan reset?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $setting->delete();

        $this->info('Password akses berhasil direset. Buka menu Backup Database dan buat password akses baru.');

        return self::SUCCESS;
    }
}
