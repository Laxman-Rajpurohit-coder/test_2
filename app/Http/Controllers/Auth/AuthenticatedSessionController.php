<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * DATA SAFETY: Suspension only blocks access — no data is ever deleted.
     * Reactivating the tenant instantly restores full access.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // After credentials are verified, check if the tenant is suspended
        // before completing the login flow.
        $user = Auth::user();
        if ($user && !empty($user->tenant_id)) {
            $tenantStatus = DB::table('tenants')
                ->where('id', $user->tenant_id)
                ->value('status');

            if ($tenantStatus === 'suspended') {
                // Log them back out immediately — session cleared, zero data touched
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Your account has been suspended. Please contact support.',
                ])->onlyInput('email');
            }
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     *
     * Clear-Site-Data: "cache" tells the browser to evict ALL bfcache entries
     * for this origin at logout, so pressing Back cannot restore a stale page.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')
            ->withHeaders([
                // Actively evicts bfcache in Chrome 96+, Firefox 94+, Safari 16.4+
                // Works alongside the JS pageshow handler as defense-in-depth
                'Clear-Site-Data' => '"cache"',
            ]);
    }
}
