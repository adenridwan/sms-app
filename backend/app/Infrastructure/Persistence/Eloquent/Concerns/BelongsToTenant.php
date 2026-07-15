<?php

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Auto-set tenant_id on creating
        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && $tenantId = static::getCurrentTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });

        // Global scope to filter by tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = static::getCurrentTenantId()) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Get the current tenant ID.
     */
    protected static function getCurrentTenantId(): ?string
    {
        // Try to get from authenticated user
        if ($user = auth()->user()) {
            return $user->tenant_id;
        }

        // Try to get from app container
        if (app()->has('tenant') && app('tenant')->current()) {
            return app('tenant')->current()->id;
        }

        // Try to get from request header
        if (request()->hasHeader('X-Tenant-ID')) {
            return request()->header('X-Tenant-ID');
        }

        return null;
    }

    /**
     * Get the tenant that owns this model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Tenant\Tenant::class,
            'tenant_id'
        );
    }

    /**
     * Scope a query to a specific tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query without tenant filtering.
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
