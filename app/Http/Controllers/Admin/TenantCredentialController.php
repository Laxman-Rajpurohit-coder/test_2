<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TenantCredentialController extends Controller
{
    /**
     * Display the masked credentials for the given tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $setting = TenantSetting::where('tenant_id', $tenant->id)->first();

        // Helper function to safely mask a string
        $mask = function ($value) {
            if (empty($value)) {
                return '';
            }
            return '••••••••' . substr($value, -4);
        };

        return response()->json([
            'msg91_auth_key' => $mask($setting->msg91_auth_key ?? ''),
            'openai_api_key' => $mask($setting->openai_api_key ?? ''),
            'grok_api_key' => $mask($setting->grok_api_key ?? ''),
            'gemini_api_key' => $mask($setting->gemini_api_key ?? ''),
            'flowise_endpoint' => $setting->flowise_endpoint ?? '',
            'meta_phone_number_id' => $setting->meta_phone_number_id ?? '',
            'meta_access_token' => $mask($setting->meta_access_token ?? ''),
            'meta_waba_id' => $setting->meta_waba_id ?? '',
            'facebook_page_id' => $setting->facebook_page_id ?? '',
            'instagram_account_id' => $setting->instagram_account_id ?? '',
            'meta_app_secret' => $mask($setting->meta_app_secret ?? ''),
            'meta_webhook_verify_token' => $mask($setting->meta_webhook_verify_token ?? ''),
        ]);
    }

    /**
     * Update the credentials for the given tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $request->validate([
            'msg91_auth_key' => 'nullable|string',
            'openai_api_key' => 'nullable|string',
            'grok_api_key' => 'nullable|string',
            'gemini_api_key' => 'nullable|string',
            'flowise_endpoint' => 'nullable|url',
            'meta_phone_number_id' => 'nullable|string',
            'meta_access_token' => 'nullable|string',
            'meta_waba_id' => 'nullable|string',
            'facebook_page_id' => 'nullable|string',
            'instagram_account_id' => 'nullable|string',
            'meta_app_secret' => 'nullable|string',
            'meta_webhook_verify_token' => 'nullable|string',
        ]);

        // Explicitly extract credential keys from the request
        $credentials = $request->only([
            'msg91_auth_key',
            'openai_api_key',
            'grok_api_key',
            'gemini_api_key',
            'flowise_endpoint',
            'meta_phone_number_id',
            'meta_access_token',
            'meta_waba_id',
            'facebook_page_id',
            'instagram_account_id',
            'meta_app_secret',
            'meta_webhook_verify_token',
        ]);

        $setting = TenantSetting::firstOrNew(['tenant_id' => $tenant->id]);

        // Only update if the field is present and NOT a masked string
        if (array_key_exists('msg91_auth_key', $credentials) && !empty($credentials['msg91_auth_key']) && !str_contains($credentials['msg91_auth_key'], '••••')) {
            $setting->msg91_auth_key = $credentials['msg91_auth_key'];
        }
        if (array_key_exists('openai_api_key', $credentials) && !empty($credentials['openai_api_key']) && !str_contains($credentials['openai_api_key'], '••••')) {
            $setting->openai_api_key = $credentials['openai_api_key'];
        }
        if (array_key_exists('grok_api_key', $credentials) && !empty($credentials['grok_api_key']) && !str_contains($credentials['grok_api_key'], '••••')) {
            $setting->grok_api_key = $credentials['grok_api_key'];
        }
        if (array_key_exists('gemini_api_key', $credentials) && !empty($credentials['gemini_api_key']) && !str_contains($credentials['gemini_api_key'], '••••')) {
            $setting->gemini_api_key = $credentials['gemini_api_key'];
        }
        if (array_key_exists('meta_access_token', $credentials) && !empty($credentials['meta_access_token']) && !str_contains($credentials['meta_access_token'], '••••')) {
            $setting->meta_access_token = $credentials['meta_access_token'];
        }
        if (array_key_exists('meta_app_secret', $credentials) && !empty($credentials['meta_app_secret']) && !str_contains($credentials['meta_app_secret'], '••••')) {
            $setting->meta_app_secret = $credentials['meta_app_secret'];
        }
        if (array_key_exists('meta_webhook_verify_token', $credentials) && !empty($credentials['meta_webhook_verify_token']) && !str_contains($credentials['meta_webhook_verify_token'], '••••')) {
            $setting->meta_webhook_verify_token = $credentials['meta_webhook_verify_token'];
        }
        
        // Unmasked string IDs and URLs
        if (array_key_exists('flowise_endpoint', $credentials)) {
            $setting->flowise_endpoint = $credentials['flowise_endpoint'];
        }
        if (array_key_exists('meta_phone_number_id', $credentials)) {
            $setting->meta_phone_number_id = $credentials['meta_phone_number_id'];
        }
        if (array_key_exists('meta_waba_id', $credentials)) {
            $setting->meta_waba_id = $credentials['meta_waba_id'];
        }
        if (array_key_exists('facebook_page_id', $credentials)) {
            $setting->facebook_page_id = $credentials['facebook_page_id'];
        }
        if (array_key_exists('instagram_account_id', $credentials)) {
            $setting->instagram_account_id = $credentials['instagram_account_id'];
        }

        $setting->save();

        return redirect()->back()->with('success', 'Tenant credentials updated successfully.');
    }
}
