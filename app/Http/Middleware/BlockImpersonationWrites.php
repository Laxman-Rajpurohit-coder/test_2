<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockImpersonationWrites
{
    /**
     * Enforces read-only access during tenant impersonation.
     *
     * Requests that modify data are blocked with a 403 JSON response or a redirect
     * containing an error message, depending on the request's expected response.
     *
     * @return Response The next handler's response or a response blocking the request.
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
