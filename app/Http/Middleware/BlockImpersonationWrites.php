<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockImpersonationWrites
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonating_tenant_id') && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Read-only during impersonation. Writes are disabled.'], 403);
            }
            
            return redirect()->back()->with('error', 'Read-only during impersonation. Writes are disabled.');
        }

        return $next($request);
    }
}
