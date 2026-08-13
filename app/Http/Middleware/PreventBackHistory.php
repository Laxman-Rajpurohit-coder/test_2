<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    /**
     * Force browsers to NEVER cache authenticated pages.
     *
     * THE PROBLEM this solves:
     *   After logout, pressing the browser back button shows cached dashboard/chat pages
     *   straight from browser memory (bfcache). The server is never contacted, so
     *   Laravel's auth checks never run — the user sees real data without a session.
     *
     * THE FIX:
     *   These headers tell every browser and proxy:
     *   - no-store    : Do not save this page at all (strongest)
     *   - no-cache    : Always revalidate with server before showing
     *   - must-revalidate : Expired cache must not be served
     *   - private     : CDN/proxy must not cache this (user-specific data)
     *
     *   Additionally, "Vary: Cookie" ensures proxy servers treat each session
     *   as a separate cache entry so one user's data is never shown to another.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply to authenticated pages (not login page, not public webhook etc.)
        if (auth()->check() || auth('admin')->check()) {
            // Use replace=true to force-override any defaults Laravel already set
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0', true);
            $response->headers->set('Pragma', 'no-cache', true);
            // Expires must be set as a raw string — Laravel's DateTimeInterface helper
            // will silently drop '0'; we bypass that by setting the header directly.
            $response->headers->remove('Expires');
            $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
            $response->headers->set('Vary', 'Cookie', true);

            // Clear-Site-Data: "cache" actively evicts the page from bfcache
            // in Chrome 96+, Firefox 94+, and Safari 16.4+
            $response->headers->set('Clear-Site-Data', '"cache"', true);
        }

        return $response;
    }
}
