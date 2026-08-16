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
        $this->app->scoped(TenantResolverService::class);
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

        // Auto-heal schema if running behind or missing table renames
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('whatsapp_messages') && !\Illuminate\Support\Facades\Schema::hasTable('messages')) {
                \Illuminate\Support\Facades\Schema::rename('whatsapp_messages', 'messages');
            }
        } catch (\Throwable $e) {
            // Ignore DB connection errors during early bootstrap
        }

        Vite::prefetch(concurrency: 3);

        // Register Core Bot Responders into Pipeline
        $pipeline = $this->app->make(BotResponderPipeline::class);
        $pipeline->register($this->app->make(\App\Responders\FirstMessageResponder::class), 20);
        $pipeline->register($this->app->make(\App\Responders\KeywordBotResponder::class), 50);
        $pipeline->register($this->app->make(\App\Responders\FallbackInteractiveResponder::class), 80);

        \Illuminate\Support\Facades\RateLimiter::for('public-api', function (\Illuminate\Http\Request $request) {
            $token = $request->bearerToken();
            $tenantSetting = null;

            if ($token) {
                $tokenHash = hash('sha256', $token);
                $tenantSetting = \App\Models\TenantSetting::where('public_api_key', $tokenHash)->first();
            }

            if ($tenantSetting && $tenantSetting->tenant_id) {
                return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('tenant:' . $tenantSetting->tenant_id);
            }

            // Backstop for missing OR invalid Bearer tokens: limit strictly per IP address
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('ip:' . $request->ip());
        });
    }
}
