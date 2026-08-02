<?php

namespace App\Http\Middleware;

use App\Services\TenantResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Sets the active tenant for the request and passes it to the next handler.
     *
     * Session-based tenant impersonation takes precedence over the authenticated user's tenant.
     *
     * @return Response The response produced by the next handler.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonating_tenant_id')) {
            app(TenantResolverService::class)->setActiveTenantId((int) session('impersonating_tenant_id'));
        } elseif (auth()->check() && !empty(auth()->user()->tenant_id)) {
            app(TenantResolverService::class)->setActiveTenantId((int) auth()->user()->tenant_id);
        }

        return $next($request);
    }
}
