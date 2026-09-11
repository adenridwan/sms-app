<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manajemen sekolah (tenant) tingkat sistem — khusus super admin.
 * Di-guard `role:super_admin` di routes/api_v1.php.
 */
class SchoolController extends ApiController
{
    /** Jenjang sekolah yang valid (samakan dengan CHECK constraint tabel tenants). */
    private const LEVELS = ['tk', 'sd', 'smp', 'sma', 'smk', 'university'];

    /**
     * Daftar sekolah (ringkas) untuk switcher / manajemen.
     */
    public function index(): JsonResponse
    {
        $schools = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'npsn', 'level', 'status', 'logo', 'is_login_brand'])
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'npsn' => $t->npsn,
                'level' => $t->level,
                'status' => $t->status,
                'logo_url' => $t->logo ? $this->logoUrl($t->logo) : null,
                'is_login_brand' => $t->is_login_brand,
            ]);

        return $this->success($schools);
    }

    /**
     * Buat sekolah baru. Multipart (membawa berkas logo opsional).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'level' => ['nullable', Rule::in(self::LEVELS)],
            'email' => ['required', 'email', 'max:255', Rule::unique('tenants', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
        ]);

        $tenant = new Tenant();
        $tenant->id = Str::uuid()->toString();
        $tenant->name = $data['name'];
        $tenant->slug = $this->uniqueSlug($data['name']);
        $tenant->npsn = $data['npsn'] ?? null;
        $tenant->level = $data['level'] ?? 'sma';
        $tenant->email = $data['email'];
        $tenant->phone = $data['phone'] ?? null;
        $tenant->address = $data['address'] ?? null;
        $tenant->status = 'active';

        if ($request->hasFile('logo')) {
            $tenant->logo = $request->file('logo')->store('logos', 'public');
        }

        $tenant->save();

        return $this->created([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'npsn' => $tenant->npsn,
            'level' => $tenant->level,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'logo_url' => $tenant->logo ? $this->logoUrl($tenant->logo) : null,
        ], 'Sekolah berhasil ditambahkan');
    }

    /**
     * Aktifkan kembali sekolah.
     */
    public function activate(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'active']);

        return $this->success(['id' => $tenant->id, 'status' => $tenant->status], 'Sekolah diaktifkan');
    }

    /**
     * Nonaktifkan sekolah. Dicegah bila ini sekolah aktif yang sedang dipakai
     * atau satu-satunya sekolah yang masih aktif.
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        if ($this->currentTenantId($request) === $tenant->id) {
            return $this->error('Tidak bisa menonaktifkan sekolah yang sedang aktif. Pindah ke sekolah lain dulu.', 422);
        }

        if ($tenant->status === 'active' && Tenant::where('status', 'active')->count() <= 1) {
            return $this->error('Tidak bisa menonaktifkan satu-satunya sekolah yang aktif.', 422);
        }

        $tenant->update(['status' => 'inactive']);

        return $this->success(['id' => $tenant->id, 'status' => $tenant->status], 'Sekolah dinonaktifkan');
    }

    /**
     * Hapus sekolah (soft delete — data tetap tersimpan & bisa dipulihkan).
     * Dicegah bila ini sekolah yang sedang aktif atau sekolah terakhir.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        if ($this->currentTenantId($request) === $tenant->id) {
            return $this->error('Tidak bisa menghapus sekolah yang sedang aktif. Pindah ke sekolah lain dulu.', 422);
        }

        if (Tenant::count() <= 1) {
            return $this->error('Tidak bisa menghapus satu-satunya sekolah.', 422);
        }

        // If this school was the login brand, clear it
        if ($tenant->is_login_brand) {
            Tenant::clearLoginBrand();
        }

        $tenant->delete();

        return $this->deleted('Sekolah berhasil dihapus');
    }

    /**
     * Get the school currently used for login page branding.
     */
    public function getLoginBrand(): JsonResponse
    {
        $tenant = Tenant::where('is_login_brand', true)->first();

        if (! $tenant) {
            return $this->success(null);
        }

        return $this->success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'logo_url' => $tenant->logo ? $this->logoUrl($tenant->logo) : null,
        ]);
    }

    /**
     * Set a school as the login page branding (or clear if id is null/empty).
     */
    public function setLoginBrand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['nullable', 'string'],
        ]);

        $schoolId = $data['school_id'] ?? null;

        // Clear login brand
        if (empty($schoolId)) {
            Tenant::clearLoginBrand();

            return $this->success(null, 'Branding halaman login direset ke bawaan sistem');
        }

        // Set login brand
        $tenant = Tenant::find($schoolId);

        if (! $tenant) {
            return $this->error('Sekolah tidak ditemukan', 404);
        }

        $tenant->setAsLoginBrand();

        return $this->success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'logo_url' => $tenant->logo ? $this->logoUrl($tenant->logo) : null,
        ], 'Branding halaman login diperbarui');
    }

    /**
     * Slug unik dari nama sekolah.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'sekolah';
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * URL logo sebagai path root-relatif agar cocok dengan origin/port mana pun.
     */
    private function logoUrl(string $path): string
    {
        return parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: '/storage/'.$path;
    }
}
