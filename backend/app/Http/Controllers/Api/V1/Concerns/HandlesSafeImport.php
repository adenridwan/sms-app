<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bungkus Excel::import() supaya exception yang lolos dari penanganan
 * per-baris (mis. StudentsImport/TeachersImport::collection()'s try/catch)
 * tidak sampai bocor sebagai pesan SQL mentah ke frontend.
 *
 * Detail teknis (pesan exception asli) hanya disertakan saat APP_DEBUG=true
 * (mode develop) — di produksi cukup pesan generik, detailnya tetap tercatat
 * di storage/logs lewat Log::error().
 */
trait HandlesSafeImport
{
    protected function runImport(callable $importer, string $genericMessage): JsonResponse
    {
        try {
            return $importer();
        } catch (Throwable $e) {
            Log::error('Import gagal: ' . $e->getMessage(), ['exception' => $e]);

            $message = $genericMessage;
            if (config('app.debug')) {
                $message .= ' [' . get_class($e) . '] ' . $e->getMessage();
            }

            return $this->error($message, 500);
        }
    }
}
