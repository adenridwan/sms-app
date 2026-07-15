<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Student policies
        \App\Infrastructure\Persistence\Eloquent\Student\Student::class => \App\Http\Policies\StudentPolicy::class,

        // Teacher policies
        \App\Infrastructure\Persistence\Eloquent\Teacher\Teacher::class => \App\Http\Policies\TeacherPolicy::class,

        // Academic policies
        \App\Infrastructure\Persistence\Eloquent\Academic\Classroom::class => \App\Http\Policies\ClassroomPolicy::class,
        \App\Infrastructure\Persistence\Eloquent\Academic\Subject::class => \App\Http\Policies\SubjectPolicy::class,

        // Finance policies
        \App\Infrastructure\Persistence\Eloquent\Finance\Payment::class => \App\Http\Policies\PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Super Admin can do everything
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Define gates for Horizon access
        Gate::define('access-horizon', function ($user) {
            return $user->hasRole(['super_admin', 'admin']);
        });

        // Define gates for specific actions
        Gate::define('manage-tenants', function ($user) {
            return $user->isSuperAdmin();
        });

        Gate::define('manage-users', function ($user) {
            return $user->hasPermissionTo('users.manage');
        });

        Gate::define('view-reports', function ($user) {
            return $user->hasPermissionTo('reports.view');
        });

        Gate::define('manage-finance', function ($user) {
            return $user->hasPermissionTo('finance.manage');
        });

        Gate::define('manage-academic', function ($user) {
            return $user->hasPermissionTo('academic.manage');
        });
    }
}
