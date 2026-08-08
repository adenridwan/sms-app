<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Restore/import file .sql (hasil `backup:run` atau dump lain yang kompatibel)
 * ke database yang SEDANG AKTIF lewat psql. Dipakai dari menu Backup Database
 * (super_admin) supaya tidak perlu input ulang data manual saat pindah
 * database — lihat CLAUDE.md § Keselamatan Database.
 *
 * SENGAJA tidak ada opsi --clean/--drop: file .sql dari backup:run murni
 * CREATE + data (tanpa DROP), jadi restore paling aman ke database yang
 * masih kosong. Restore ke database yang sudah berisi tabel akan gagal di
 * banyak statement CREATE TABLE (aman — tidak menghapus data lama), tapi
 * bisa meninggalkan campuran data lama+baru yang tidak konsisten kalau
 * sebagian statement INSERT sempat jalan. Controller pemanggil WAJIB
 * mengingatkan user soal ini sebelum memanggil command ini.
 */
class RestoreDatabase extends Command
{
    protected $signature = 'backup:restore {file : Path lengkap ke file .sql} {--force : Lewati konfirmasi}';

    protected $description = 'Restore file .sql ke database yang sedang aktif lewat psql';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! File::exists($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error("Koneksi database aktif ({$connection}) bukan pgsql. Command ini hanya mendukung Postgres.");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn("Ini akan menjalankan isi {$path} terhadap database \"{$config['database']}\" yang SEDANG AKTIF.");
            if (! $this->confirm('Lanjutkan?')) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $psqlBinary = config('backup.psql_path', 'psql');

        $this->info("Restore ke database \"{$config['database']}\" dari {$path} ...");

        $process = new Process([
            $psqlBinary,
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--username=' . $config['username'],
            '--dbname=' . $config['database'],
            '--set=ON_ERROR_STOP=0',
            '--file=' . $path,
        ], env: ['PGPASSWORD' => $config['password']]);

        $process->setTimeout(1800);
        $process->run();

        // psql keluar dengan kode 0 walau ada error statement individual
        // (ON_ERROR_STOP=0, disengaja — lihat catatan class di atas: restore
        // ke DB yang sudah ada isinya WAJAR menghasilkan error "already
        // exists" per tabel, itu bukan alasan menganggap seluruh proses
        // gagal). Deteksi error lewat stderr, bukan cuma exit code.
        $stderr = $process->getErrorOutput();
        $errorLines = array_filter(explode("\n", $stderr), fn ($line) => stripos($line, 'ERROR') !== false);

        if (! $process->isSuccessful()) {
            $this->error('Restore gagal dijalankan: ' . trim($stderr));

            return self::FAILURE;
        }

        if (! empty($errorLines)) {
            $this->warn(count($errorLines) . ' statement gagal (kemungkinan karena tabel/data sudah ada):');
            foreach (array_slice($errorLines, 0, 20) as $line) {
                $this->line('  ' . trim($line));
            }
        }

        $this->info('Restore selesai.');

        return self::SUCCESS;
    }
}
