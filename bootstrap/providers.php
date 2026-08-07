<?php

use App\Providers\AppServiceProvider;
use Modules\AiBot\AiBotServiceProvider;
use Modules\Analytics\AnalyticsServiceProvider;
use Modules\FlowBuilder\FlowBuilderServiceProvider;
use Modules\Templates\TemplatesServiceProvider;

return [
    AppServiceProvider::class,
    AnalyticsServiceProvider::class,
    FlowBuilderServiceProvider::class,
    AiBotServiceProvider::class,
    TemplatesServiceProvider::class,
];
