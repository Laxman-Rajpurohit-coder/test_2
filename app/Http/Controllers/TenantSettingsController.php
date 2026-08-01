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
                'ai_provider' => $setting->ai_provider ?? 'openai',
                'ai_model' => $setting->ai_model ?? '',
                'ai_system_prompt' => $setting->ai_system_prompt ?? '',
                'ai_is_active' => $setting->ai_is_active ?? false,
                'ai_human_escalation_enabled' => $setting->ai_human_escalation_enabled ?? true,
                'ai_confidence_threshold' => $setting->ai_confidence_threshold ?? 0.70,
            ],
            'webhook' => [
                'url' => config('app.url') . '/api/msg91/webhook',
                'secret' => config('services.msg91.webhook_secret'),
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
            'ai_provider' => 'nullable|string|in:openai,flowise',
            'ai_model' => 'nullable|string',
            'ai_system_prompt' => 'nullable|string',
            'ai_is_active' => 'nullable|boolean',
            'ai_human_escalation_enabled' => 'nullable|boolean',
            'ai_confidence_threshold' => 'nullable|numeric|min:0|max:1',
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
        $setting->ai_provider = $validated['ai_provider'] ?? 'openai';
        $setting->ai_model = $validated['ai_model'] ?? null;
        $setting->ai_system_prompt = $validated['ai_system_prompt'] ?? null;
        $setting->ai_is_active = $validated['ai_is_active'] ?? false;
        $setting->ai_human_escalation_enabled = $validated['ai_human_escalation_enabled'] ?? true;
        $setting->ai_confidence_threshold = $validated['ai_confidence_threshold'] ?? 0.70;
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
