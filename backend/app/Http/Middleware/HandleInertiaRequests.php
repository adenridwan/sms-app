<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\System\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = null;

        // Eager load semua relasi yang dibutuhkan SEKALI di awal
        // Ini mengurangi ~10 query menjadi 4 query saja
        if ($user) {
            $user->loadMissing([
                'tenant',                // 1 query - untuk data tenant
                'profile',               // 1 query - untuk full_name, first_name, dll
                'roles.permissions',     // 2 query - roles + permissions per role
                'permissions',           // 1 query - direct permissions
            ]);

            // Ambil tenant dari relasi yang sudah di-load (0 query tambahan)
            $tenant = $user->tenant ?? tenant();
            if ($tenant) {
                app('tenant')->setTenant($tenant);
            }
        }

        return [
            ...parent::share($request),

            // Auth user - semua data diambil dari relasi yang sudah eager-loaded
            'auth' => [
                'user' => $user ? $this->getUserData($user) : null,
            ],

            // Menu yang boleh tampil untuk user ini (permission + config
            // visibilitas per-role, Opsi A). Dipakai sidebar MainLayout.tsx.
            // Menggunakan lazy loading (fn) agar hanya dihitung jika diakses
            'menu' => [
                'visible' => fn () => $user
                    ? $this->getVisibleMenuKeys($user)
                    : [],
            ],

            // Tenant - diambil dari relasi yang sudah di-load.
            // Catatan: null untuk super admin, karena akunnya tidak terikat
            // satu sekolah (tenant_id NULL). Sekolah aktifnya hanya diketahui
            // browser (localStorage + header X-Tenant-ID), tidak oleh server
            // saat merender. Karena itu MainLayout jatuh ke `tenants` di bawah.
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo' => $this->tenantLogoUrl($tenant->logo),
            ] : null,

            // Tenant list for super admin (used by the tenant switcher).
            // Logonya WAJIB berbentuk URL yang sama dengan `tenant.logo` di
            // atas — sebelumnya kolomnya dikirim mentah ("logos/x.png"),
            // sehingga tidak bisa dipakai langsung sebagai src <img>.
            'tenants' => fn () => $user?->isSuperAdmin()
                ? \App\Models\Tenant::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'logo'])
                    ->map(fn ($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'logo' => $this->tenantLogoUrl($t->logo),
                    ])
                : null,

            // Flash messages
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'info' => fn() => $request->session()->get('info'),
            ],

            // App settings - cache untuk mengurangi query Setting
            'app' => $this->getAppSettings(),
        ];
    }

    /**
     * URL logo sekolah sebagai path root-relatif (mis. "/storage/logos/x.png"),
     * bukan URL absolut — supaya cocok dengan host/port mana pun yang dipakai
     * (localhost:8000 saat dev, domain sungguhan saat produksi). Pola yang sama
     * dipakai SchoolProfileController::logoUrl().
     */
    protected function tenantLogoUrl(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        return parse_url(\Illuminate\Support\Facades\Storage::disk('public')->url($logo), PHP_URL_PATH)
            ?: '/storage/' . $logo;
    }

    /**
     * Get user data from eager-loaded relations.
     * Tidak ada query tambahan karena semua relasi sudah di-load.
     */
    protected function getUserData($user): array
    {
        // Ambil permissions dari relasi yang sudah di-load
        // Ini menggantikan getAllPermissions() yang trigger N+1 query
        $permissions = $this->getPermissionsFromLoadedRelations($user);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar
                ? (parse_url(\Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar), PHP_URL_PATH)
                    ?: '/storage/'.$user->avatar)
                : null,
            'user_type' => $user->user_type,
            // full_name accessor akan menggunakan profile yang sudah di-load
            'full_name' => $user->full_name,
            // Ambil roles dari relasi yang sudah di-load
            'roles' => $user->roles->pluck('name'),
            // Permissions dari helper method (tanpa query)
            'permissions' => $permissions,
            'must_change_password' => $user->mustChangePassword(),
        ];
    }

    /**
     * Get all permissions from already-loaded relations.
     * Menggantikan getAllPermissions() yang menyebabkan N+1 query.
     */
    protected function getPermissionsFromLoadedRelations($user): array
    {
        // Kumpulkan permissions dari semua roles
        $rolePermissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'));

        // Gabungkan dengan direct permissions
        $directPermissions = $user->permissions->pluck('name');

        return $rolePermissions
            ->merge($directPermissions)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get visible menu keys with caching.
     * Cache per user untuk mengurangi 50+ permission check per request.
     */
    protected function getVisibleMenuKeys($user): array
    {
        $cacheKey = "menu_visible:{$user->id}:" . ($user->tenant_id ?? 'global');

        // Cache selama 5 menit - akan di-invalidate saat permission berubah
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            return app(\App\Domain\Setting\Services\MenuVisibilityService::class)
                ->visibleKeysFor($user);
        });
    }

    /**
     * Get app settings with caching.
     */
    protected function getAppSettings(): array
    {
        // Cache app settings selama 1 jam karena jarang berubah
        return Cache::remember('app_settings', now()->addHour(), function () {
            return [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'version' => Setting::getGlobal('app', 'version') ?? '1.0.0',
                'env' => config('app.env'),
            ];
        });
    }
}
