<?php

namespace Modules\AiBot;

use App\Services\BotResponderPipeline;
use Illuminate\Support\ServiceProvider;
use Modules\AiBot\Responders\AiBotResponder;

class AiBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Register AiBotResponder at Priority 90 in BotResponderPipeline
        if ($this->app->bound(BotResponderPipeline::class)) {
            $pipeline = $this->app->make(BotResponderPipeline::class);
            $responder = $this->app->make(AiBotResponder::class);
            $pipeline->register($responder, $responder->priority());
        }
    }
}
