<?php

namespace Modules\AiBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AiBot\Models\AiBotSetting;

class AiBotController extends Controller
{
    /**
     * Display AI Bot Settings Page
     */
    public function show()
    {
        $setting = AiBotSetting::firstOrCreate(
            [],
            [
                'provider'                 => 'openai',
                'model_or_chatflow_id'     => 'gpt-4o-mini',
                'system_prompt'            => 'You are a helpful customer support assistant for WhatsApp.',
                'is_active'                => false,
                'human_escalation_enabled' => true,
            ]
        );

        return Inertia::render('Modules/AiBot/Settings', [
            'setting' => [
                'id'                       => $setting->id,
                'provider'                 => $setting->provider,
                'api_key_configured'       => !empty($setting->api_key),
                'model_or_chatflow_id'     => $setting->model_or_chatflow_id,
                'system_prompt'            => $setting->system_prompt,
                'is_active'                => $setting->is_active,
                'human_escalation_enabled' => $setting->human_escalation_enabled,
            ]
        ]);
    }

    /**
     * Update AI Bot Settings
     */
    public function update(Request $request)
    {
        $setting = AiBotSetting::firstOrCreate([]);

        $validated = $request->validate([
            'provider'                 => 'required|string|in:openai,flowise',
            'api_key'                  => 'nullable|string|max:500',
            'model_or_chatflow_id'     => 'required|string|max:255',
            'system_prompt'            => 'nullable|string|max:2000',
            'is_active'                => 'required|boolean',
            'human_escalation_enabled' => 'required|boolean',
        ]);

        // Don't overwrite existing encrypted api_key if field left empty
        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $setting->update($validated);

        return back()->with('success', 'AI Bot settings updated successfully.');
    }
}
