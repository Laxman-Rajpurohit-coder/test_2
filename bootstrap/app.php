<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\IdentifyTenant::class,          // Structural Global Web Middleware
            \App\Http\Middleware\CheckTenantSuspended::class,    // Block suspended tenants (data-safe)
            \App\Http\Middleware\PreventBackHistory::class,      // No-cache headers: blocks back-button data leak
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\IdentifyTenant::class,          // Structural Global API Middleware
            \App\Http\Middleware\CheckTenantSuspended::class,    // Block suspended tenants (data-safe)
        ]);

        $middleware->alias([
            'admin'     => \App\Http\Middleware\AdminMiddleware::class,
            'feature'   => \App\Http\Middleware\CheckTenantFeature::class,
            'role'      => \App\Http\Middleware\RoleMiddleware::class,
            'suspended' => \App\Http\Middleware\CheckTenantSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

