<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Displays the tenant index page with tenants ordered by creation date.
     *
     * @return \Inertia\Response The rendered tenant index page.
     */
    public function index()
    {
        $tenants = Tenant::orderBy('created_at', 'desc')->get();
        return Inertia::render('Admin/Tenants/Index', ['tenants' => $tenants]);
    }

    /**
     * Displays overview metrics for a tenant within the requested date range and timezone.
     *
     * @param Request $request Provides optional `from`, `to`, and `tz` query parameters.
     * @param Tenant $tenant The tenant whose metrics are displayed.
     * @param \Modules\Analytics\Services\AnalyticsService $analytics Provides tenant overview metrics.
     * @return \Inertia\Response The rendered tenant statistics page.
     */
    public function stats(Request $request, Tenant $tenant, \Modules\Analytics\Services\AnalyticsService $analytics)
    {
        $dateFrom = $request->query('from');
        $dateTo = $request->query('to');
        $timezone = $request->query('tz', 'UTC');

        $metrics = $analytics->getOverviewMetrics($dateFrom, $dateTo, $timezone, $tenant->id);

        return Inertia::render('Admin/Tenants/Stats', [
            'tenant' => $tenant,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Creates an active tenant from validated request data.
     *
     * @param Request $request The request containing the tenant name and slug.
     * @return \Illuminate\Http\RedirectResponse The response redirecting back with a success message.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
        ]);

        Tenant::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
            'status' => 'active',
        ]);

        return back()->with('success', 'Tenant created.');
    }

    /**
     * Updates a tenant's status and suspension timestamp.
     *
     * @param Tenant $tenant The tenant whose status is being updated.
     * @return \Illuminate\Http\RedirectResponse The redirect response with a success message.
     */
    public function updateStatus(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $tenant->update([
            'status' => $validated['status'],
            'suspended_at' => $validated['status'] === 'suspended' ? now() : null,
        ]);

        return back()->with('success', 'Tenant status updated.');
    }

    /**
     * Deletes a tenant and redirects back with a success message.
     *
     * @param Tenant $tenant The tenant to delete.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return back()->with('success', 'Tenant deleted.');
    }
}
