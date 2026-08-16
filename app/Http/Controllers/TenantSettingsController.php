<?php

namespace App\Http\Controllers;

use App\Models\TenantSetting;
use App\Models\TenantNumber;
use App\Services\TenantResolverService;
use App\Services\MetaBusinessProfileService;
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

        $metaCreds = $resolver->getMetaCredentials($tenantId);
        $hasMetaConfig = !empty($metaCreds['access_token']) && !empty($metaCreds['phone_number_id']);

        // Decoupled: do NOT synchronously block page rendering with external Graph API calls
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
                'meta_phone_number_id' => $setting->meta_phone_number_id ?? '',
                'meta_waba_id' => $setting->meta_waba_id ?? '',
                'facebook_page_id' => $setting->facebook_page_id ?? '',
                'instagram_account_id' => $setting->instagram_account_id ?? '',
                'has_meta_access_token' => !empty($setting->meta_access_token),
            ],
            'meta' => [
                'is_configured' => $hasMetaConfig,
                'phone_number_id' => $metaCreds['phone_number_id'],
                'waba_id' => $metaCreds['waba_id'],
            ],
            'numbers' => $numbers,
        ]);
    }

    public function getBusinessProfile(TenantResolverService $resolver, MetaBusinessProfileService $profileService)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isOwner') && !$user->isOwner() && !$user->isAdmin() && !($user instanceof \App\Models\AdminUser)) {
            abort(403, 'Only tenant owners or admins can view WhatsApp Business Profile.');
        }

        $tenantId = $resolver->getActiveTenantId();
        $creds = $resolver->getMetaCredentials($tenantId);

        if (empty($creds['access_token']) || empty($creds['phone_number_id'])) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Meta Access Token and Phone Number ID are not configured yet.',
            ], 422);
        }

        $result = $profileService->getProfile($creds['access_token'], $creds['phone_number_id']);
        return response()->json($result);
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

        // Enforce Target Phone Number ownership validation
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

    public function updateMetaCredentials(Request $request, TenantResolverService $resolver)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isOwner') && !$user->isOwner() && !$user->isAdmin() && !($user instanceof \App\Models\AdminUser)) {
            abort(403, 'Only tenant owners or admins can modify Meta credentials.');
        }

        $validated = $request->validate([
            'meta_phone_number_id' => 'nullable|string|max:255',
            'meta_waba_id' => 'nullable|string|max:255',
            'meta_access_token' => 'nullable|string',
            'facebook_page_id' => 'nullable|string|max:255',
            'instagram_account_id' => 'nullable|string|max:255',
        ]);

        $tenantId = $resolver->getActiveTenantId();
        $setting = TenantSetting::firstOrNew(['tenant_id' => $tenantId]);

        if (array_key_exists('meta_phone_number_id', $validated)) {
            $setting->meta_phone_number_id = $validated['meta_phone_number_id'];
        }
        if (array_key_exists('meta_waba_id', $validated)) {
            $setting->meta_waba_id = $validated['meta_waba_id'];
        }
        if (!empty($validated['meta_access_token'])) {
            $setting->meta_access_token = $validated['meta_access_token'];
        }
        if (array_key_exists('facebook_page_id', $validated)) {
            $setting->facebook_page_id = $validated['facebook_page_id'];
        }
        if (array_key_exists('instagram_account_id', $validated)) {
            $setting->instagram_account_id = $validated['instagram_account_id'];
        }

        $setting->save();

        return redirect()->back()->with('success', 'Meta / WhatsApp Cloud API Credentials updated successfully.');
    }

    public function updateBusinessProfile(Request $request, TenantResolverService $resolver, MetaBusinessProfileService $profileService)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isOwner') && !$user->isOwner() && !$user->isAdmin() && !($user instanceof \App\Models\AdminUser)) {
            abort(403, 'Only tenant owners or admins can modify WhatsApp Business Profile.');
        }

        $tenantId = $resolver->getActiveTenantId();
        $creds = $resolver->getMetaCredentials($tenantId);

        if (empty($creds['access_token']) || empty($creds['phone_number_id'])) {
            return redirect()->back()->withErrors([
                'meta_error' => 'Meta Access Token and WhatsApp Phone Number ID must be configured in Tenant Settings before updating Business Profile.',
            ]);
        }

        $validated = $request->validate([
            'about' => 'nullable|string|max:139',
            'address' => 'nullable|string|max:256',
            'description' => 'nullable|string|max:512',
            'email' => 'nullable|email|max:128',
            'vertical' => 'nullable|string|max:64',
            'websites' => 'nullable|array|max:2',
            'websites.*' => 'nullable|url|max:256',
            'profile_picture' => 'nullable|image|max:5120',
        ]);

        $reqVertical = strtoupper($validated['vertical'] ?? 'OTHER');
        $allowedVerticals = ['OTHER', 'AUTO', 'BEAUTY', 'APPAREL', 'EDU', 'ENTERTAIN', 'EVENT_PLAN', 'FINANCE', 'GROCERY', 'GOVT', 'HOTEL', 'HEALTH', 'NONPROFIT', 'PROF_SERVICES', 'RETAIL', 'TRAVEL', 'RESTAURANT', 'ALCOHOL', 'ONLINE_GAMBLING', 'PHYSICAL_GAMBLING', 'OTC_DRUGS', 'MATRIMONY_SERVICE'];
        $vertical = in_array($reqVertical, $allowedVerticals) ? $reqVertical : 'OTHER';

        $payload = [
            'about' => $validated['about'] ?? '',
            'address' => $validated['address'] ?? '',
            'description' => $validated['description'] ?? '',
            'email' => $validated['email'] ?? '',
            'vertical' => $vertical,
            'websites' => $validated['websites'] ?? [],
        ];

        if ($request->hasFile('profile_picture')) {
            $mediaResult = $profileService->uploadMedia($creds['access_token'], $creds['phone_number_id'], $request->file('profile_picture'));
            if ($mediaResult['success']) {
                $payload['profile_picture_handle'] = $mediaResult['handle'];
            } else {
                return redirect()->back()
                    ->withErrors(['profile_picture' => 'Failed to upload profile picture to Meta: ' . $mediaResult['error']])
                    ->with('error', 'Profile Picture Upload Failed: ' . $mediaResult['error']);
            }
        }

        $result = $profileService->updateProfile($creds['access_token'], $creds['phone_number_id'], $payload);

        if ($result['success']) {
            return redirect()->back()->with('success', 'WhatsApp Business Profile updated successfully on Meta Cloud API.');
        }

        return redirect()->back()
            ->withErrors(['meta_error' => 'Graph API Error: ' . $result['error']])
            ->with('error', 'Meta Graph API Error: ' . $result['error']);
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
