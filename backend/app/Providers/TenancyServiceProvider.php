<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure tenant identification
        $this->configureTenantIdentification();

        // Configure tenant scoping
        $this->configureTenantScoping();
    }

    /**
     * Configure how tenants are identified.
     */
    protected function configureTenantIdentification(): void
    {
        // Tenant can be identified by:
        // 1. Subdomain (e.g., school1.sms.app)
        // 2. Domain (e.g., school1.com)
        // 3. Header (X-Tenant-ID)
        // 4. Path prefix (e.g., /tenant/school1)
    }

    /**
     * Configure automatic tenant scoping for models.
     */
    protected function configureTenantScoping(): void
    {
        // Models that should be automatically scoped to current tenant
        $tenantScopedModels = [
            \App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear::class,
            \App\Infrastructure\Persistence\Eloquent\Academic\Classroom::class,
            \App\Infrastructure\Persistence\Eloquent\Academic\Subject::class,
            \App\Infrastructure\Persistence\Eloquent\Student\Student::class,
            \App\Infrastructure\Persistence\Eloquent\Teacher\Teacher::class,
            \App\Infrastructure\Persistence\Eloquent\Finance\Payment::class,
            // ... add more models
        ];
    }
}
