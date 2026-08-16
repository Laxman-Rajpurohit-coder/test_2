<?php

namespace App\Http\Controllers;

use App\Models\BotTrigger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotTriggerController extends Controller
{
    public function index(): Response
    {
        // Refactored to BotTrigger Eloquent model -> BelongsToTenant global scope applies automatically
        $triggers = BotTrigger::query()
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('BotTriggers/Index', [
            'triggers' => $triggers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trigger_type'           => 'nullable|string|in:keyword,first_message,fallback',
            'keyword'                => 'required_if:trigger_type,keyword|nullable|string|max:255',
            'match_type'             => 'required_if:trigger_type,keyword|nullable|in:exact,contains,starts_with',
            'response_type'          => 'required|in:text,image,document,flow,interactive',
            'response_payload'       => 'required|array',
            'response_payload.text'  => 'required_if:response_type,text,interactive|nullable|string|max:4096',
            'response_payload.url'   => 'required_if:response_type,image,document|nullable|url',
            'response_payload.buttons'=> 'array|max:3',
            'priority'               => 'integer|min:0|max:999',
            'is_active'              => 'boolean',
        ]);

        $triggerType = $validated['trigger_type'] ?? 'keyword';

        // Auto-assigns tenant_id via BelongsToTenant trait
        BotTrigger::create([
            'trigger_type'     => $triggerType,
            'keyword'          => strtolower(trim($validated['keyword'] ?? ($triggerType !== 'keyword' ? $triggerType : ''))),
            'match_type'       => $validated['match_type'] ?? 'contains',
            'response_type'    => $validated['response_type'],
            'response_payload' => $validated['response_payload'],
            'priority'         => $validated['priority'] ?? 0,
            'is_active'        => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Bot trigger created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'trigger_type'           => 'nullable|string|in:keyword,first_message,fallback',
            'keyword'                => 'required_if:trigger_type,keyword|nullable|string|max:255',
            'match_type'             => 'required_if:trigger_type,keyword|nullable|in:exact,contains,starts_with',
            'response_type'          => 'required|in:text,image,document,flow,interactive',
            'response_payload'       => 'required|array',
            'response_payload.text'  => 'required_if:response_type,text,interactive|nullable|string|max:4096',
            'response_payload.url'   => 'required_if:response_type,image,document|nullable|url',
            'response_payload.buttons'=> 'array|max:3',
            'priority'               => 'integer|min:0|max:999',
            'is_active'              => 'boolean',
        ]);

        $trigger = BotTrigger::findOrFail($id);
        $triggerType = $validated['trigger_type'] ?? $trigger->trigger_type ?? 'keyword';

        $trigger->update([
            'trigger_type'     => $triggerType,
            'keyword'          => strtolower(trim($validated['keyword'] ?? ($triggerType !== 'keyword' ? $triggerType : ''))),
            'match_type'       => $validated['match_type'] ?? 'contains',
            'response_type'    => $validated['response_type'],
            'response_payload' => $validated['response_payload'],
            'priority'         => $validated['priority'] ?? 0,
            'is_active'        => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Bot trigger updated successfully.');
    }

    public function toggleActive(int $id)
    {
        $trigger = BotTrigger::findOrFail($id);
        $trigger->update(['is_active' => !$trigger->is_active]);

        return redirect()->back()->with('success', 'Bot trigger status updated.');
    }

    public function destroy(int $id)
    {
        $trigger = BotTrigger::findOrFail($id);
        $trigger->delete();

        return redirect()->back()->with('success', 'Bot trigger deleted.');
    }
}
