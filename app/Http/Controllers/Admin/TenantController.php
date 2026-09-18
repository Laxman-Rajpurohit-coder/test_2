<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Message;
use App\Models\BillingSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Displays the tenant index page with tenant-wise billing usage details.
     * Safe for Super Admin (uses withoutGlobalScopes() to bypass tenant isolation resolution).
     *
     * @return \Inertia\Response The rendered tenant index page.
     */
    public function index()
    {
        $billing = BillingSetting::getForTenant(null);
        $unitDivider = $billing->rate_unit > 0 ? $billing->rate_unit : 1000;

        $tenants = Tenant::orderBy('created_at', 'desc')->get()->map(function ($tenant) use ($billing, $unitDivider) {
            try {
                $conversationIds = \App\Models\Conversation::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->pluck('id');

                $messages = Message::withoutGlobalScopes()
                    ->whereIn('conversation_id', $conversationIds)
                    ->get(['content']);

                $totalCount = $messages->count();
                $totalCost = 0.0;
                $marketingCount = 0;
                $utilityCount = 0;
                $authCount = 0;
                $serviceCount = 0;

                foreach ($messages as $msg) {
                    $unitRate = $billing->service_message_rate;
                    $contentJson = $msg->content;

                    if (is_string($contentJson)) {
                        try {
                            $parsed = json_decode($contentJson, true);
                            if (is_array($parsed)) {
                                $type = $parsed['type'] ?? 'text';
                                if ($type === 'template') {
                                    $category = strtolower($parsed['category'] ?? $parsed['template_category'] ?? 'utility');
                                    if (str_contains($category, 'market')) {
                                        $marketingCount++;
                                        $unitRate = $billing->marketing_template_rate;
                                    } elseif (str_contains($category, 'auth')) {
                                        $authCount++;
                                        $unitRate = $billing->authentication_template_rate;
                                    } else {
                                        $utilityCount++;
                                        $unitRate = $billing->utility_template_rate;
                                    }
                                } else {
                                    $serviceCount++;
                                    $unitRate = in_array($type, ['image', 'audio', 'video', 'document']) ? $billing->base_message_rate : $billing->service_message_rate;
                                }
                            }
                        } catch (\Throwable $e) {}
                    } else {
                        $serviceCount++;
                    }

                    $totalCost += ($unitRate / $unitDivider);
                }

                $tenant->total_messages = $totalCount;
                $tenant->total_cost = round($totalCost, 4);
                $tenant->marketing_count = $marketingCount;
                $tenant->utility_count = $utilityCount;
                $tenant->auth_count = $authCount;
                $tenant->service_count = $serviceCount;
            } catch (\Throwable $e) {
                $tenant->total_messages = 0;
                $tenant->total_cost = 0.0;
                $tenant->marketing_count = 0;
                $tenant->utility_count = 0;
                $tenant->auth_count = 0;
                $tenant->service_count = 0;
            }

            return $tenant;
        });

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'billing' => $billing,
            'webhook' => [
                'url' => rtrim(config('app.url'), '/') . '/api/msg91/webhook',
                'secret' => config('services.msg91.webhook_secret'),
            ],
        ]);
    }

    /**
     * Updates global/tenant billing rates configuration from Super Admin panel.
     */
    public function updateBillingSettings(Request $request)
    {
        $validated = $request->validate([
            'rate_unit' => 'required|integer|in:1000,10000',
            'currency' => 'required|string|max:10',
            'base_message_rate' => 'required|numeric|min:0',
            'utility_template_rate' => 'required|numeric|min:0',
            'marketing_template_rate' => 'required|numeric|min:0',
            'authentication_template_rate' => 'required|numeric|min:0',
            'service_message_rate' => 'required|numeric|min:0',
        ]);

        $setting = BillingSetting::getForTenant(null);
        $setting->update($validated);

        return back()->with('success', 'Admin billing rates updated successfully.');
    }

    /**
     * Displays overview metrics for a tenant within the requested date range and timezone.
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
     * Updates a tenant's feature toggles.
     */
    public function updateFeatures(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'features' => 'array',
        ]);

        $tenant->update([
            'features' => $validated['features'] ?? [],
        ]);

        return back()->with('success', 'Tenant features updated.');
    }

    /**
     * Adds balance to a tenant upon receiving payment or granting credit.
     */
    public function addBalance(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'type' => 'nullable|string|in:topup,promotional_credit,adjustment',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'auto_activate' => 'nullable|boolean',
            'enable_billing' => 'nullable|boolean',
        ]);

        $autoActivate = $request->has('auto_activate') ? $request->boolean('auto_activate') : true;
        $type = $validated['type'] ?? 'topup';
        $enableBilling = $request->has('enable_billing') ? $request->boolean('enable_billing') : null;

        \App\Services\MessageBillingService::addBalance(
            $tenant,
            (string) $validated['amount'],
            $validated['payment_reference'] ?? null,
            $validated['notes'] ?? null,
            auth()->guard('admin')->id(),
            $autoActivate,
            $type,
            $enableBilling
        );

        $typeLabel = ucfirst(str_replace('_', ' ', $type));
        return back()->with('success', "Added ₹{$validated['amount']} ({$typeLabel}) to {$tenant->name}.");
    }

    /**
     * Toggles prepaid billing enforcement for a tenant.
     */
    public function toggleBilling(Request $request, Tenant $tenant)
    {
        $newState = !$tenant->billing_enabled;
        $tenant->update([
            'billing_enabled' => $newState,
            'billing_status' => $newState ? (bccomp((string)$tenant->balance, '0.0000', 4) <= 0 ? 'exhausted' : 'active') : 'active',
        ]);

        $msg = $newState 
            ? "Prepaid billing enforcement enabled for {$tenant->name}." 
            : "Prepaid billing disabled for {$tenant->name} (unmetered mode).";

        return back()->with('success', $msg);
    }

    /**
     * Fetches balance transactions ledger for a tenant with rich audit metadata.
     */
    public function transactions(Tenant $tenant)
    {
        $transactions = $tenant->transactions()
            ->with('adminUser:id,name,email')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'balance' => (string) $tenant->balance,
                'billing_enabled' => (bool) $tenant->billing_enabled,
                'billing_status' => $tenant->billing_status,
                'status' => $tenant->status,
                'suspension_reason' => $tenant->suspension_reason,
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Deletes a tenant record.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return back()->with('success', 'Tenant deleted.');
    }
}
