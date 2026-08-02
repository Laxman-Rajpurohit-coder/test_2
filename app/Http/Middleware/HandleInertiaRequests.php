<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle the incoming request. Ensure no restrictive CSP header is set in local environment.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $response = parent::handle($request, $next);
        
        // In local development, remove any restrictive CSP headers to allow Vite HMR and dynamic evaluation cleanly
        if (app()->environment('local') || config('app.debug')) {
            $response->headers->remove('Content-Security-Policy');
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines shared Inertia props for the authenticated user and tenant impersonation state.
     *
     * @return array<string, mixed> The shared props, including authenticated user and impersonation details.
     */
    public function share(Request $request): array
    {
        $user = $request->user('web') ?? $request->user('admin');
        // Only regular tenant Users have a tenant relationship; AdminUser does not.
        $isRegularUser = $user instanceof \App\Models\User;
        $tenant = $isRegularUser ? $user->tenant : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? ($isRegularUser ? $user->load('tenant') : $user) : null,
            ],
            'tenant_features' => $tenant?->features ?? [],
            'impersonation' => [
                'is_impersonating' => session()->has('impersonating_tenant_id'),
                'tenant_id'        => session('impersonating_tenant_id'),
                'tenant_name'      => session()->has('impersonating_tenant_id') ? \App\Models\Tenant::find(session('impersonating_tenant_id'))?->name ?? 'Unknown' : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ];
    }
}
