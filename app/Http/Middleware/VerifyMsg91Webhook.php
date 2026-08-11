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

        // Enforce secret matching via X-MSG91-Secret header or 'secret' query parameter
        $incomingSecret = $request->header('X-MSG91-Secret') ?? $request->query('secret');

        // Strict constant-time hash comparison
        if (!hash_equals($expectedSecret, (string) $incomingSecret)) {
            \Illuminate\Support\Facades\Log::warning('Webhook Auth Failed', [
                'expected' => $expectedSecret,
                'received_header' => $request->header('X-MSG91-Secret'),
                'received_query' => $request->query('secret'),
                'all_headers' => $request->headers->all(),
            ]);
            abort(401, 'Unauthorized webhook signature.');
        }

        return $next($request);
    }
}
