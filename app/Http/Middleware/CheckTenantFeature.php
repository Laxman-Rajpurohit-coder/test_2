<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantFeature
{
    /**
     * Handle an incoming request.
     * Aborts with 403 if the active tenant does not have the required feature enabled.
     *
     * Usage in routes: ->middleware('feature:bot_auto_responder')
     *
     * Fails closed: if the tenant cannot be found or the features column is null/empty,
     * access is denied by default.
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenantId = app(TenantResolverService::class)->getActiveTenantId();
        $tenant = Tenant::find($tenantId);

        if (!$tenant || !$tenant->hasFeature($featureKey)) {
            abort(403, "This feature ({$featureKey}) is not enabled for your account.");
        }

        return $next($request);
    }
}
