<?php

use App\Providers\AppServiceProvider;
use Modules\AiBot\AiBotServiceProvider;
use Modules\Analytics\AnalyticsServiceProvider;
use Modules\FlowBuilder\FlowBuilderServiceProvider;

return [
    AppServiceProvider::class,
    AnalyticsServiceProvider::class,
    FlowBuilderServiceProvider::class,
    AiBotServiceProvider::class,
];
