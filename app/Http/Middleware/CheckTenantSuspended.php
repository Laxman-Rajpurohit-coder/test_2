<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSuspended
{
    /**
     * Block access for suspended tenants on every authenticated request.
     *
     * DATA SAFETY GUARANTEE:
     *   - No user data, messages, contacts, or campaigns are touched.
     *   - Only the session is cleared (login state).
     *   - Reactivating the tenant restores full access instantly.
     *
     * ADMIN BYPASS:
     *   - Super-admins (admin guard) are never blocked so they can
     *     still access the admin panel and reactivate the tenant.
     *   - Impersonation sessions are also bypassed so admins can
     *     inspect a suspended tenant's data without getting locked out.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Never block super-admins or impersonation sessions
        if (auth('admin')->check() || session()->has('impersonating_tenant_id')) {
            return $next($request);
        }

        // Only enforce for authenticated regular users
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        if (empty($user->tenant_id)) {
            return $next($request);
        }

        // Load tenant status (lightweight — only fetches status column)
        $tenantStatus = DB::table('tenants')
            ->where('id', $user->tenant_id)
            ->value('status');

        if ($tenantStatus === 'suspended') {
            // Log the user out (clears session only — zero data deleted)
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // For Inertia/API requests return JSON so the frontend handles it gracefully
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message'  => 'Your account has been suspended. Please contact support.',
                    'suspended' => true,
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been suspended. Please contact support.',
            ]);
        }

        return $next($request);
    }
}
