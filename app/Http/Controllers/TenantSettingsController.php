<?php

namespace App\Http\Controllers;

use App\Models\TenantSetting;
use App\Models\TenantNumber;
use App\Services\TenantResolverService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller
{
    public function edit(TenantResolverService $resolver): Response
    {
        $tenantId = $resolver->getActiveTenantId();
        $setting = TenantSetting::where('tenant_id', $tenantId)->first();
        $numbers = TenantNumber::where('tenant_id', $tenantId)->get();

        return Inertia::render('Settings/Tenant', [
            'tenantId' => $tenantId,
            'settings' => [
                'msg91_auth_key'   => $setting ? ($setting->msg91_auth_key ? '••••••••' . substr($setting->msg91_auth_key, -4) : '') : '',
                'openai_api_key'   => $setting ? ($setting->openai_api_key ? '••••••••' . substr($setting->openai_api_key, -4) : '') : '',
                'flowise_endpoint' => $setting->flowise_endpoint ?? '',
            ],
            'numbers' => $numbers,
        ]);
    }

    public function update(Request $request, TenantResolverService $resolver)
    {
        $validated = $request->validate([
            'msg91_auth_key'   => 'nullable|string',
            'openai_api_key'   => 'nullable|string',
            'flowise_endpoint' => 'nullable|url',
        ]);

        $tenantId = $resolver->getActiveTenantId();
        $setting = TenantSetting::firstOrNew(['tenant_id' => $tenantId]);

        if (!empty($validated['msg91_auth_key']) && !str_contains($validated['msg91_auth_key'], '••••')) {
            $setting->msg91_auth_key = $validated['msg91_auth_key'];
        }
        if (!empty($validated['openai_api_key']) && !str_contains($validated['openai_api_key'], '••••')) {
            $setting->openai_api_key = $validated['openai_api_key'];
        }
        $setting->flowise_endpoint = $validated['flowise_endpoint'] ?? null;
        $setting->save();

        return redirect()->back()->with('success', 'Tenant Integration Settings updated successfully.');
    }

    public function storeNumber(Request $request, TenantResolverService $resolver)
    {
        $validated = $request->validate([
            'integrated_number' => 'required|string|unique:tenant_numbers,integrated_number',
        ]);

        $tenantId = $resolver->getActiveTenantId();
        TenantNumber::create([
            'tenant_id'         => $tenantId,
            'integrated_number' => trim($validated['integrated_number']),
        ]);

        return redirect()->back()->with('success', 'WhatsApp Integrated Number registered successfully.');
    }
}
