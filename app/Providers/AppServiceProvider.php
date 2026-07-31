<?php

namespace App\Providers;

use App\Responders\KeywordBotResponder;
use App\Services\BotResponderPipeline;
use App\Services\TenantResolverService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantResolverService::class);
        $this->app->singleton(BotResponderPipeline::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        // Register Core Keyword Bot Responder into Pipeline (Priority 50)
        $pipeline = $this->app->make(BotResponderPipeline::class);
        $pipeline->register($this->app->make(KeywordBotResponder::class), 50);
    }
}
