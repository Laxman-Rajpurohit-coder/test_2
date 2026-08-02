<?php

namespace Modules\Analytics;

use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind AnalyticsService as a singleton in service container
        $this->app->singleton(\Modules\Analytics\Services\AnalyticsService::class, function ($app) {
            return new \Modules\Analytics\Services\AnalyticsService();
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
