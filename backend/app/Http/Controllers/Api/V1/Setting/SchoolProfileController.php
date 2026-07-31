<?php

namespace App\Http\Controllers\Api\V1\Setting;

use App\Http\Controllers\Api\ApiController;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Profil Sekolah (Pengaturan → Umum). Mengelola identitas sekolah pada tabel
 * `tenants`: nama, NPSN, jenjang, kontak, dan logo. Di-guard
 * `permission:settings.school` di routes/api_v1.php.
 */
class SchoolProfileController extends ApiController
{
    /** Jenjang sekolah yang valid (samakan dengan CHECK constraint tabel tenants). */
    private const LEVELS = ['tk', 'sd', 'smp', 'sma', 'smk', 'university'];

    /**
     * Tampilkan profil sekolah yang sedang aktif.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan', 422);
        }

        return $this->success($this->present($tenant));
    }

    /**
     * Perbarui profil sekolah. Dikirim sebagai multipart/form-data karena
     * memuat berkas logo (ikut pola upload avatar di ProfileController).
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'level' => ['nullable', Rule::in(self::LEVELS)],
            'email' => ['required', 'email', 'max:255', Rule::unique('tenants', 'email')->ignore($tenant->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $payload = collect($data)
            ->only(['name', 'npsn', 'level', 'email', 'phone', 'address'])
            ->toArray();

        // Ganti logo baru: hapus yang lama dulu bila ada.
        if ($request->hasFile('logo')) {
            $this->deleteLogo($tenant);
            $payload['logo'] = $request->file('logo')->store('logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($tenant);
            $payload['logo'] = null;
        }

        $tenant->update($payload);

        return $this->success($this->present($tenant->fresh()), 'Profil sekolah berhasil disimpan');
    }

    /**
     * Bentuk respons yang dipakai frontend (logo sebagai URL siap pakai).
     */
    private function present(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'npsn' => $tenant->npsn,
            'level' => $tenant->level,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'logo_url' => $tenant->logo ? $this->logoUrl($tenant->logo) : null,
        ];
    }

    /**
     * URL logo sebagai path root-relatif (mis. "/storage/logos/x.png") agar
     * cocok dengan origin/port berapa pun app diakses — tidak terpaku APP_URL.
     */
    private function logoUrl(string $path): string
    {
        return parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: '/storage/'.$path;
    }

    /**
     * Hapus berkas logo dari storage bila masih tersimpan.
     */
    private function deleteLogo(Tenant $tenant): void
    {
        if ($tenant->logo && Storage::disk('public')->exists($tenant->logo)) {
            Storage::disk('public')->delete($tenant->logo);
        }
    }

    /**
     * Resolusi tenant aktif: user biasa dari tenant_id-nya, super admin dari
     * header X-Tenant-ID (lihat ApiController::currentTenantId).
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $tenantId = $this->currentTenantId($request);

        return $tenantId ? Tenant::find($tenantId) : null;
    }
}
