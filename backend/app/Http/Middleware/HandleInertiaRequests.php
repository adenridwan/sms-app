<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\System\Setting;
use Illuminate\Http\Request;
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
        // Resolusi tenant aktif untuk request web/Inertia. `tenant()` (TenantService)
        // hanya terisi bila ada middleware yang menyetelnya; untuk halaman Inertia
        // biasa hal itu tidak terjadi, jadi kita resolusi langsung dari user yang
        // login dan sekaligus set ke service agar konsisten di seluruh request.
        $tenant = tenant();
        if (! $tenant && $request->user()?->tenant_id) {
            $tenant = \App\Models\Tenant::find($request->user()->tenant_id);
            if ($tenant) {
                app('tenant')->setTenant($tenant);
            }
        }

        return [
            ...parent::share($request),

            // Auth user
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'user_type' => $request->user()->user_type,
                    'full_name' => $request->user()->full_name,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    'must_change_password' => $request->user()->mustChangePassword(),
                ] : null,
            ],

            // Menu yang boleh tampil untuk user ini (permission + config
            // visibilitas per-role, Opsi A). Dipakai sidebar MainLayout.tsx.
            'menu' => [
                'visible' => fn () => $request->user()
                    ? app(\App\Domain\Setting\Services\MenuVisibilityService::class)
                        ->visibleKeysFor($request->user())
                    : [],
            ],

            // Tenant
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo' => $tenant->logo
                    ? (parse_url(\Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo), PHP_URL_PATH)
                        ?: '/storage/'.$tenant->logo)
                    : null,
            ] : null,

            // Tenant list for super admin (used by the tenant switcher)
            'tenants' => fn () => $request->user()?->isSuperAdmin()
                ? \App\Models\Tenant::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'logo'])
                : null,

            // Flash messages
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'info' => fn() => $request->session()->get('info'),
            ],

            // App settings
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                // Diisi lewat menu Pengaturan > Backup Database ("Versi
                // Aplikasi"), ditampilkan di footer MainLayout.tsx.
                'version' => Setting::getGlobal('app', 'version') ?? '1.0.0',
            ],
        ];
    }
}
