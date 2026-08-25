<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Handle an incoming request and attach modern HTTP security headers.
     *
     * CSP Directives Rationale:
     * - script-src 'self' 'unsafe-inline': Required because Ziggy (@routes) generates an inline route definitions block.
     *   Note: 'unsafe-eval' is intentionally excluded as production Vite/React builds do not require eval().
     * - style-src 'self' 'unsafe-inline' https://fonts.bunny.net: Needed for Tailwind utility styles, inline CSS, and Bunny Fonts.
     * - font-src 'self' https://fonts.bunny.net data:: Needed for Figtree font files from Bunny Fonts.
     * - img-src 'self' data: blob: https:: Needed for avatars, chat attachments, and WhatsApp media previews.
     * - media-src 'self' data: blob:: Needed for audio voice notes and video playback in chat.
     * - connect-src 'self' ws: wss:: Needed for Laravel Reverb WebSocket connections. External origins (like MSG91 and Meta)
     *   are excluded because their APIs are accessed strictly server-to-server via Laravel backend services.
     * - frame-ancestors 'none': Protects against clickjacking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $scriptSrc = "script-src 'self' 'unsafe-inline'";
        $connectSrc = "connect-src 'self' ws: wss:";

        // Allow local Vite dev server origins (http://localhost:*, http://[::1]:*, etc.) strictly in local environment
        if (app()->isLocal()) {
            $scriptSrc .= " http://localhost:* http://127.0.0.1:* http://[::1]:*";
            $connectSrc .= " http://localhost:* http://127.0.0.1:* http://[::1]:* ws://localhost:* ws://127.0.0.1:* ws://[::1]:*";
        }

        $csp = implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob: https:",
            "media-src 'self' data: blob: https:",
            $connectSrc,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp, false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()', false);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
