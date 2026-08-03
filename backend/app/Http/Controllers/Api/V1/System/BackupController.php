<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kelola backup database (pg_dump) manual + hasil backup terjadwal
 * (routes/console.php: backup:run). Rute ini di-gate role super_admin saja
 * (lihat routes/api_v1.php) — sengaja tidak lewat sistem permission biasa
 * karena akses ke dump database setara akses penuh ke seluruh data tenant.
 */
class BackupController extends ApiController
{
    private function directory(): string
    {
        return config('backup.directory');
    }

    /**
     * Nama file yang sah: <database>_YYYY-MM-DD_HHmmss.sql — dipakai untuk
     * menolak path traversal pada download/destroy.
     */
    private function isValidFilename(string $filename): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename) && ! str_contains($filename, '..');
    }

    public function index(): JsonResponse
    {
        File::ensureDirectoryExists($this->directory());

        $files = collect(File::files($this->directory()))
            ->map(fn ($file) => [
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
            ])
            ->sortByDesc('created_at')
            ->values();

        return $this->success($files);
    }

    public function store(): JsonResponse
    {
        $exitCode = Artisan::call('backup:run');
        $output = Artisan::output();

        if ($exitCode !== 0) {
            return $this->error('Backup gagal: ' . trim($output), 500);
        }

        return $this->success(['output' => trim($output)], 'Backup berhasil dibuat', 201);
    }

    public function download(string $filename): StreamedResponse|JsonResponse
    {
        if (! $this->isValidFilename($filename)) {
            return $this->notFound('Nama file backup tidak valid');
        }

        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;
        if (! File::exists($path)) {
            return $this->notFound('Backup tidak ditemukan');
        }

        return response()->streamDownload(
            fn () => readfile($path),
            $filename,
            ['Content-Type' => 'application/sql'],
        );
    }

    public function destroy(string $filename): JsonResponse
    {
        if (! $this->isValidFilename($filename)) {
            return $this->notFound('Nama file backup tidak valid');
        }

        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;
        if (! File::exists($path)) {
            return $this->notFound('Backup tidak ditemukan');
        }

        File::delete($path);

        return $this->success(null, 'Backup berhasil dihapus');
    }
}
