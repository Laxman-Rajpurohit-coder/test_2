<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMsg91Webhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('services.msg91.webhook_secret');

        // Fail-closed: If secret is not configured on the server, block immediately
        if (empty($expectedSecret)) {
            abort(500, 'Webhook secret is not configured on the server.');
        }

        // Fail-closed: Enforce X-MSG91-Secret header strictly (no query string or fallback checks)
        $incomingSecret = $request->header('X-MSG91-Secret');

        // Strict constant-time hash comparison
        if (!hash_equals($expectedSecret, (string) $incomingSecret)) {
            abort(401, 'Unauthorized webhook signature.');
        }

        return $next($request);
    }
}
