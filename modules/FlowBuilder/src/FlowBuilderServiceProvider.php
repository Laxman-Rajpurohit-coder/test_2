<?php

namespace Modules\FlowBuilder;

use App\Services\BotResponderPipeline;
use Illuminate\Support\ServiceProvider;
use Modules\FlowBuilder\Responders\FlowResponder;
use Modules\FlowBuilder\Services\FlowExecutionService;
use Modules\FlowBuilder\Services\FlowGraphValidatorService;

class FlowBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FlowGraphValidatorService::class);
        $this->app->singleton(FlowExecutionService::class);
        $this->app->singleton(FlowResponder::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Register FlowResponder into BotResponderPipeline at Priority 10 (Highest Precedence)
        $pipeline = $this->app->make(BotResponderPipeline::class);
        $pipeline->register($this->app->make(FlowResponder::class), 10);
    }
}
