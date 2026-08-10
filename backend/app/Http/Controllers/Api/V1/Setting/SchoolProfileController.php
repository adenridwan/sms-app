<?php

namespace App\Http\Controllers\Api\V1\Setting;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\System\Setting;
use App\Models\Tenant;
use App\Services\EmailGenerator;
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

    public function __construct(private EmailGenerator $emailGenerator) {}

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
            // Domain email otomatis siswa/guru (docs/EMAIL-OTOMATIS-AKUN.md).
            // Nama host saja — tanpa "@", tanpa skema, tanpa spasi.
            'email_domain' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
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

        if (array_key_exists('email_domain', $data)) {
            $domain = strtolower(trim((string) $data['email_domain']));

            // Domain wajib unik lintas sekolah: keunikan email siswa
            // ({nama}.{nis}@{domain}) bersandar pada NIS yang hanya unik dalam
            // satu tenant, jadi dua sekolah berdomain sama bisa bentrok.
            if ($domain !== '' && $this->domainTakenByOtherTenant($domain, $tenant->id)) {
                return $this->error('Domain email itu sudah dipakai sekolah lain.', 422, [
                    'email_domain' => ['Domain email itu sudah dipakai sekolah lain.'],
                ]);
            }

            Setting::setForTenant(
                $tenant->id,
                EmailGenerator::SETTING_GROUP,
                EmailGenerator::SETTING_KEY,
                $domain !== '' ? $domain : null,
                'Domain email otomatis untuk akun siswa & guru',
            );
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
            'email_domain' => $this->emailGenerator->domainFor($tenant->id),
        ];
    }

    /**
     * Apakah domain ini sudah dipakai sekolah lain?
     */
    private function domainTakenByOtherTenant(string $domain, string $tenantId): bool
    {
        return Setting::where('group', EmailGenerator::SETTING_GROUP)
            ->where('key', EmailGenerator::SETTING_KEY)
            ->whereRaw('lower(value) = ?', [$domain])
            ->where('tenant_id', '!=', $tenantId)
            ->exists();
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
