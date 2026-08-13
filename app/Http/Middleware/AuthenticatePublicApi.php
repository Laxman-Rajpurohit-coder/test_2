<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePublicApi
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthorized. Missing Bearer Token.'], 401);
        }

        $hashedToken = hash('sha256', $token);
        $setting = \App\Models\TenantSetting::where('public_api_key', $hashedToken)->first();

        if (!$setting || !$setting->tenant_id) {
            return response()->json(['error' => 'Unauthorized. Invalid API Key.'], 401);
        }

        // Set the active tenant for the request so downstream controllers/models work correctly
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($setting->tenant_id);

        return $next($request);
    }
}
