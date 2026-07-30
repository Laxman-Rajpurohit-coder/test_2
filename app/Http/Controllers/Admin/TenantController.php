<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderBy('created_at', 'desc')->get();
        return Inertia::render('Admin/Tenants/Index', ['tenants' => $tenants]);
    }

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

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return back()->with('success', 'Tenant deleted.');
    }
}
