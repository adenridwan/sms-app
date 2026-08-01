<?php

namespace App\Http\Controllers\Api\V1\Setting;

use App\Domain\Setting\Services\MenuVisibilityService;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengaturan visibilitas menu per-role (Opsi A). Di-guard
 * `permission:settings.manage` di routes/api_v1.php.
 */
class MenuSettingController extends ApiController
{
    public function __construct(private MenuVisibilityService $service) {}

    /**
     * Matriks role × menu + status tiap sel.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        if (! $tenantId) {
            return $this->error('Konteks tenant tidak ditemukan', 422);
        }

        return $this->success($this->service->matrix($tenantId));
    }

    /**
     * Simpan konfigurasi. Body: { hidden: { role: [menu_key, ...] } }.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden' => ['present', 'array'],
            'hidden.*' => ['array'],
            'hidden.*.*' => ['string'],
        ]);

        $tenantId = $this->currentTenantId($request);

        if (! $tenantId) {
            return $this->error('Konteks tenant tidak ditemukan', 422);
        }

        $this->service->replaceHidden($tenantId, $data['hidden']);

        return $this->success(null, 'Pengaturan menu berhasil disimpan');
    }
}
