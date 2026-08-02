<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Restricts access to authenticated administrators.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next The next middleware or request handler.
     * @return \Symfony\Component\HttpFoundation\Response The redirected login response or the response from the next request handler.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
