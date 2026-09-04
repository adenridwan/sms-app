<?php

namespace App\Domain\Payroll\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Service untuk tracking progress generate slip gaji.
 *
 * Menggunakan cache untuk menyimpan status progress yang dapat
 * di-poll oleh frontend.
 */
class PayrollProgressService
{
    protected const CACHE_PREFIX = 'payroll_progress_';
    protected const CACHE_TTL = 300; // 5 menit

    /**
     * Buat progress baru untuk periode.
     */
    public function start(string $periodId, int $total): string
    {
        $progressId = $periodId;

        Cache::put($this->getCacheKey($progressId), [
            'status' => 'processing',
            'current' => 0,
            'total' => $total,
            'percentage' => 0,
            'message' => 'Memulai proses...',
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ], self::CACHE_TTL);

        return $progressId;
    }

    /**
     * Update progress.
     */
    public function update(string $progressId, int $current, ?string $message = null): void
    {
        $data = Cache::get($this->getCacheKey($progressId));

        if (!$data) {
            return;
        }

        $percentage = $data['total'] > 0
            ? round(($current / $data['total']) * 100)
            : 0;

        Cache::put($this->getCacheKey($progressId), [
            ...$data,
            'current' => $current,
            'percentage' => $percentage,
            'message' => $message ?? "Memproses {$current} dari {$data['total']}...",
            'updated_at' => now()->toISOString(),
        ], self::CACHE_TTL);
    }

    /**
     * Tandai progress selesai.
     */
    public function complete(string $progressId, string $message = 'Selesai', array $result = []): void
    {
        $data = Cache::get($this->getCacheKey($progressId));

        if (!$data) {
            return;
        }

        Cache::put($this->getCacheKey($progressId), [
            ...$data,
            'status' => 'completed',
            'current' => $data['total'],
            'percentage' => 100,
            'message' => $message,
            'result' => $result,
            'completed_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ], self::CACHE_TTL);
    }

    /**
     * Tandai progress error.
     */
    public function error(string $progressId, string $message): void
    {
        $data = Cache::get($this->getCacheKey($progressId));

        Cache::put($this->getCacheKey($progressId), [
            ...($data ?? []),
            'status' => 'error',
            'message' => $message,
            'updated_at' => now()->toISOString(),
        ], self::CACHE_TTL);
    }

    /**
     * Ambil status progress.
     */
    public function get(string $progressId): ?array
    {
        return Cache::get($this->getCacheKey($progressId));
    }

    /**
     * Hapus progress.
     */
    public function clear(string $progressId): void
    {
        Cache::forget($this->getCacheKey($progressId));
    }

    /**
     * Generate cache key.
     */
    protected function getCacheKey(string $progressId): string
    {
        return self::CACHE_PREFIX . $progressId;
    }
}
