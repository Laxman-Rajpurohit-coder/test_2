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
        $user = auth()->user();
        if ($user && method_exists($user, 'isOwner') && !$user->isOwner() && !$user->isAdmin() && !($user instanceof \App\Models\AdminUser)) {
            abort(403, 'Only tenant owners or admins can manage API settings.');
        }

        $tenantId = $resolver->getActiveTenantId();
        $setting = TenantSetting::where('tenant_id', $tenantId)->first();
        $numbers = TenantNumber::where('tenant_id', $tenantId)->get();

        $previewKey = $setting && $setting->public_api_key_last_four 
            ? 'sk_live_••••••••' . $setting->public_api_key_last_four 
            : null;

        return Inertia::render('Settings/Tenant', [
            'tenantId' => $tenantId,
            'public_api_key_preview' => $previewKey,
            'new_public_api_key' => session('new_public_api_key'),
            'settings' => [
                'ai_provider' => $setting->ai_provider ?? 'openai',
                'ai_model' => $setting->ai_model ?? '',
                'ai_system_prompt' => $setting->ai_system_prompt ?? '',
                'ai_is_active' => $setting->ai_is_active ?? false,
                'ai_human_escalation_enabled' => $setting->ai_human_escalation_enabled ?? true,
                'ai_confidence_threshold' => $setting->ai_confidence_threshold ?? 0.70,
                'widget_title' => $setting->widget_title ?? 'Chat with us on WhatsApp',
                'widget_welcome_msg' => $setting->widget_welcome_msg ?? 'Hi there! How can we help you today?',
                'widget_color' => $setting->widget_color ?? '#00a884',
                'widget_position' => $setting->widget_position ?? 'bottom-right',
                'widget_auto_redirect_wa' => $setting->widget_auto_redirect_wa ?? true,
                'widget_target_phone' => $setting->widget_target_phone ?? '',
            ],
            'numbers' => $numbers,
        ]);
    }

    public function regenerateApiKey(TenantResolverService $resolver)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isOwner') && !$user->isOwner() && !$user->isAdmin() && !($user instanceof \App\Models\AdminUser)) {
            abort(403, 'Only tenant owners or admins can modify API settings.');
        }

        $tenantId = $resolver->getActiveTenantId();
        $setting = TenantSetting::firstOrNew(['tenant_id' => $tenantId]);
        
        $plainKey = 'sk_live_' . \Illuminate\Support\Str::random(40);
        $setting->public_api_key = hash('sha256', $plainKey);
        $setting->public_api_key_last_four = substr($plainKey, -4);
        $setting->save();

        return redirect()->back()
            ->with('new_public_api_key', $plainKey)
            ->with('success', 'Public API Key generated successfully. Please copy it now; you won\'t be able to see it again!');
    }

    public function update(Request $request, TenantResolverService $resolver)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isOwner') && !$user->isOwner() && !$user->isAdmin() && !($user instanceof \App\Models\AdminUser)) {
            abort(403, 'Only tenant owners or admins can modify API settings.');
        }
        $validated = $request->validate([
            'ai_provider' => 'nullable|string|in:openai,flowise,grok,gemini',
            'ai_model' => 'nullable|string',
            'ai_system_prompt' => 'nullable|string',
            'ai_is_active' => 'nullable|boolean',
            'ai_human_escalation_enabled' => 'nullable|boolean',
            'ai_confidence_threshold' => 'nullable|numeric|min:0|max:1',
            'widget_title' => 'nullable|string|max:255',
            'widget_welcome_msg' => 'nullable|string|max:1000',
            'widget_color' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'widget_position' => 'nullable|string|in:bottom-right,bottom-left',
            'widget_auto_redirect_wa' => 'nullable|boolean',
            'widget_target_phone' => 'nullable|string|max:50',
        ]);

        $tenantId = $resolver->getActiveTenantId();

        // Enforce Target Phone Number ownership validation!
        if (!empty($validated['widget_target_phone'])) {
            $numberExists = TenantNumber::where('tenant_id', $tenantId)
                ->where('integrated_number', $validated['widget_target_phone'])
                ->exists();

            if (!$numberExists) {
                return redirect()->back()->withErrors([
                    'widget_target_phone' => 'Selected WhatsApp target number must belong to your integrated tenant numbers.'
                ]);
            }
        }

        $setting = TenantSetting::firstOrNew(['tenant_id' => $tenantId]);

        if ($request->has('ai_provider')) $setting->ai_provider = $validated['ai_provider'] ?? 'openai';
        if ($request->has('ai_model')) $setting->ai_model = $validated['ai_model'] ?? null;
        if ($request->has('ai_system_prompt')) $setting->ai_system_prompt = $validated['ai_system_prompt'] ?? null;
        if ($request->has('ai_is_active')) $setting->ai_is_active = $validated['ai_is_active'] ?? false;
        if ($request->has('ai_human_escalation_enabled')) $setting->ai_human_escalation_enabled = $validated['ai_human_escalation_enabled'] ?? true;
        if ($request->has('ai_confidence_threshold')) $setting->ai_confidence_threshold = $validated['ai_confidence_threshold'] ?? 0.70;
        
        if ($request->has('widget_title')) $setting->widget_title = $validated['widget_title'] ?? 'Chat with us on WhatsApp';
        if ($request->has('widget_welcome_msg')) $setting->widget_welcome_msg = $validated['widget_welcome_msg'] ?? 'Hi there! How can we help you today?';
        if ($request->has('widget_color')) $setting->widget_color = $validated['widget_color'] ?? '#00a884';
        if ($request->has('widget_position')) $setting->widget_position = $validated['widget_position'] ?? 'bottom-right';
        if ($request->has('widget_auto_redirect_wa')) $setting->widget_auto_redirect_wa = $validated['widget_auto_redirect_wa'] ?? true;
        if ($request->has('widget_target_phone')) $setting->widget_target_phone = $validated['widget_target_phone'] ?? null;

        $setting->save();

        return redirect()->back()->with('success', 'Tenant Integration Settings updated successfully.');
    }

    public function storeNumber(Request $request, TenantResolverService $resolver)
    {
        $validated = $request->validate([
            'country_code' => 'nullable|string',
            'integrated_number' => 'required|string',
        ]);

        $cleanNumber = preg_replace('/[^0-9]/', '', $validated['integrated_number']);
        $countryCode = preg_replace('/[^0-9]/', '', $validated['country_code'] ?? '');

        $finalNumber = $countryCode . $cleanNumber;

        // Check if this concatenated number is already used globally
        if (TenantNumber::where('integrated_number', $finalNumber)->exists()) {
            return redirect()->back()->withErrors(['integrated_number' => 'This exact number configuration is already integrated.']);
        }

        $tenantId = $resolver->getActiveTenantId();
        TenantNumber::create([
            'tenant_id'         => $tenantId,
            'integrated_number' => $finalNumber,
        ]);

        return redirect()->back()->with('success', 'WhatsApp Integrated Number registered successfully.');
    }

    public function destroyNumber(Request $request, TenantResolverService $resolver, string $number)
    {
        $tenantId = $resolver->getActiveTenantId();
        
        TenantNumber::where('tenant_id', $tenantId)
            ->where('integrated_number', $number)
            ->delete();

        return redirect()->back()->with('success', 'WhatsApp Integrated Number removed successfully.');
    }
}
