<?php

namespace App\Providers;

use App\Support\TenantConnectionManager;
use Illuminate\Support\ServiceProvider;

class DynamicConnectionProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantConnectionManager::class, function ($app) {
            return new TenantConnectionManager($app['db'], $app['config']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // The active tenant connection is selected per request by middleware.
    }
}
