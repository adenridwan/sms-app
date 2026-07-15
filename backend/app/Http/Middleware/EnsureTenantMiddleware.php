<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for super admin
        if ($request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        // Check if tenant is set
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant not found or not specified.',
            ], 404);
        }

        // Verify user belongs to tenant
        if ($request->user() && $request->user()->tenant_id !== $tenantId) {
            return response()->json([
                'message' => 'You do not have access to this tenant.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resolve the tenant ID from the request.
     */
    protected function resolveTenantId(Request $request): ?string
    {
        // From authenticated user
        if ($request->user()?->tenant_id) {
            return $request->user()->tenant_id;
        }

        // From request header
        if ($request->hasHeader('X-Tenant-ID')) {
            return $request->header('X-Tenant-ID');
        }

        // From subdomain
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0] ?? null;

        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            // Lookup tenant by subdomain
            $tenant = \App\Infrastructure\Persistence\Eloquent\Tenant\Tenant::where('slug', $subdomain)->first();
            return $tenant?->id;
        }

        return null;
    }
}
