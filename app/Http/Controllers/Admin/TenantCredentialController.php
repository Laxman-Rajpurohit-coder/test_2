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
        ]);

        // Explicitly extract ONLY the 5 credential keys from the request
        $credentials = $request->only([
            'msg91_auth_key',
            'openai_api_key',
            'grok_api_key',
            'gemini_api_key',
            'flowise_endpoint',
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
        
        // Flowise endpoint is a URL, not masked
        if (array_key_exists('flowise_endpoint', $credentials)) {
            $setting->flowise_endpoint = $credentials['flowise_endpoint'];
        }

        $setting->save();

        return redirect()->back()->with('success', 'Tenant credentials updated successfully.');
    }
}
