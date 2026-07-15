<?php

namespace App\Domain\Tenant\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantService
{
    /**
     * Current tenant instance.
     */
    protected ?Tenant $currentTenant = null;

    /**
     * Set the current tenant.
     */
    public function setTenant(?Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Get the current tenant ID.
     */
    public function getTenantId(): ?string
    {
        return $this->currentTenant?->id;
    }

    /**
     * Check if tenant is set.
     */
    public function hasTenant(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Find tenant by domain.
     */
    public function findByDomain(string $domain): ?Tenant
    {
        return Cache::remember(
            "tenant:domain:{$domain}",
            now()->addHours(1),
            fn () => Tenant::where('domain', $domain)->first()
        );
    }

    /**
     * Find tenant by ID.
     */
    public function findById(string $id): ?Tenant
    {
        return Cache::remember(
            "tenant:id:{$id}",
            now()->addHours(1),
            fn () => Tenant::find($id)
        );
    }

    /**
     * Clear tenant cache.
     */
    public function clearCache(?Tenant $tenant = null): void
    {
        if ($tenant) {
            Cache::forget("tenant:id:{$tenant->id}");
            Cache::forget("tenant:domain:{$tenant->domain}");
        } else {
            // Clear all tenant cache - use tags if available
            Cache::flush();
        }
    }

    /**
     * Get default tenant (for single-tenant mode).
     */
    public function getDefaultTenant(): ?Tenant
    {
        return Cache::remember(
            'tenant:default',
            now()->addHours(1),
            fn () => Tenant::where('is_active', true)->first()
        );
    }
}
