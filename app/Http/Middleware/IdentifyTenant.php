<?php

namespace App\Http\Middleware;

use App\Services\TenantResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming web request.
     * Binds current user's tenant_id to TenantResolverService.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && !empty(auth()->user()->tenant_id)) {
            app(TenantResolverService::class)->setActiveTenantId((int) auth()->user()->tenant_id);
        }

        return $next($request);
    }
}
