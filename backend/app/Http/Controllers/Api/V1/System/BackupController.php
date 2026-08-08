<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    /**
     * Nama database aktif saja (bukan host/username/password) — dipakai
     * untuk menampilkan apa yang harus diketik user di dialog konfirmasi
     * restore/import. Sengaja tidak digerbangi password akses "Koneksi
     * Database Aplikasi": nama database sudah terlihat di nama tiap file
     * backup (`{database}_{timestamp}.sql`), jadi bukan informasi rahasia.
     */
    public function activeDatabase(): JsonResponse
    {
        return $this->success(['database' => config('database.connections.pgsql.database')]);
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

    /**
     * Restore salah satu file di daftar backup ke database yang sedang
     * aktif. WAJIB kirim `confirm_database` persis sama dengan nama database
     * aktif — pengecekan ini di server, bukan cuma UI, supaya tidak bisa
     * ke-trigger tanpa sengaja.
     */
    public function restore(Request $request, string $filename): JsonResponse
    {
        if (! $this->isValidFilename($filename)) {
            return $this->notFound('Nama file backup tidak valid');
        }

        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;
        if (! File::exists($path)) {
            return $this->notFound('Backup tidak ditemukan');
        }

        return $this->runRestore($request, $path);
    }

    /**
     * Upload file .sql dari luar (mis. hasil export dari database lain) lalu
     * langsung restore ke database yang sedang aktif. File yang diunggah
     * disimpan ke folder backup juga (bukan dihapus setelah restore) supaya
     * tercatat dan bisa dipakai ulang/diperiksa.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000', 'mimes:sql,txt'], // 500MB
        ]);

        $original = $request->file('file')->getClientOriginalName();
        $safeName = 'import_' . now()->format('Y-m-d_His') . '_' . Str::slug(pathinfo($original, PATHINFO_FILENAME)) . '.sql';

        File::ensureDirectoryExists($this->directory());
        $request->file('file')->move($this->directory(), $safeName);
        $path = $this->directory() . DIRECTORY_SEPARATOR . $safeName;

        return $this->runRestore($request, $path);
    }

    private function runRestore(Request $request, string $path): JsonResponse
    {
        $request->validate([
            'confirm_database' => ['required', 'string'],
        ]);

        $activeDatabase = config('database.connections.pgsql.database');
        if ($request->input('confirm_database') !== $activeDatabase) {
            return $this->error("Konfirmasi tidak cocok. Ketik persis nama database aktif: \"{$activeDatabase}\".", 422);
        }

        $exitCode = Artisan::call('backup:restore', ['file' => $path, '--force' => true]);
        $output = Artisan::output();

        if ($exitCode !== 0) {
            return $this->error('Restore gagal: ' . trim($output), 500);
        }

        return $this->success(['output' => trim($output)], 'Restore berhasil dijalankan ke database "' . $activeDatabase . '".');
    }
}
