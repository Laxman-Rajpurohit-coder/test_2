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
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
        ];
    }
}
