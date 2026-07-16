<?php

namespace App\Http\Middleware;

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
                ] : null,
            ],

            // Tenant
            'tenant' => tenant() ? [
                'id' => tenant()->id,
                'name' => tenant()->name,
                'logo' => tenant()->logo,
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
            ],
        ];
    }
}
