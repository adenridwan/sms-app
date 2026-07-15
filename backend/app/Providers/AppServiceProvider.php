<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register singleton services
        $this->app->singleton('tenant', function ($app) {
            return new \App\Domain\Tenant\Services\TenantService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Eloquent strict mode in development
        Model::shouldBeStrict(!$this->app->isProduction());

        // Prevent lazy loading in development
        Model::preventLazyLoading(!$this->app->isProduction());

        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());

        // Default password rules
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->mixedCase()->numbers()->symbols()->uncompromised()
                : $rule;
        });

        // Database query logging in development
        if ($this->app->environment('local') && config('app.debug')) {
            DB::listen(function ($query) {
                if ($query->time > 100) {
                    logger()->warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                    ]);
                }
            });
        }
    }
}
