<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run {--retention-days= : Override BACKUP_RETENTION_DAYS}';

    protected $description = 'Backup database Postgres aktif via pg_dump ke storage/app/private/backups, lalu hapus backup lama sesuai retensi';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error("Koneksi database aktif ({$connection}) bukan pgsql. Command ini hanya mendukung Postgres.");

            return self::FAILURE;
        }

        $pgDumpBinary = config('backup.pg_dump_path', 'pg_dump');
        $directory = config('backup.directory');
        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            '%s_%s.sql',
            $config['database'],
            Carbon::now()->format('Y-m-d_His'),
        );
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $this->info("Membackup database \"{$config['database']}\" ke {$filename} ...");

        $process = new Process([
            $pgDumpBinary,
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--username=' . $config['username'],
            '--format=plain',
            '--no-owner',
            '--no-privileges',
            '--file=' . $path,
            $config['database'],
        ], env: ['PGPASSWORD' => $config['password']]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            File::delete($path);
            $this->error('Backup gagal: ' . trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $size = File::size($path);
        $this->info(sprintf('Backup selesai: %s (%s).', $filename, $this->formatBytes($size)));

        $this->pruneOldBackups($directory, (int) ($this->option('retention-days') ?: config('backup.retention_days', 14)));

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $directory, int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Menghapus {$deleted} backup lama (lebih dari {$retentionDays} hari).");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2) . ' ' . $units[$i];
    }
}
