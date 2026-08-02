<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactTag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::withCount('recipients')->orderBy('created_at', 'desc')->paginate(20);
        return Inertia::render('Campaigns/Index', ['campaigns' => $campaigns]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'message_type' => 'required|in:text,template',
            'template_name' => 'nullable|required_if:message_type,template|string',
            'template_language' => 'nullable|required_if:message_type,template|string',
            'text_content' => 'nullable|required_if:message_type,text|string',
            'target_type' => 'required|in:all,group,tag',
            'target_id' => 'nullable|uuid',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Verify target isolation
        if ($validated['target_type'] === 'group') {
            ContactGroup::where('tenant_id', $tenantId)->findOrFail($validated['target_id']);
        } elseif ($validated['target_type'] === 'tag') {
            ContactTag::where('tenant_id', $tenantId)->findOrFail($validated['target_id']);
        }

        DB::transaction(function () use ($validated, $tenantId) {
            $campaign = Campaign::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'message_type' => $validated['message_type'],
                'template_name' => $validated['template_name'] ?? null,
                'template_language' => $validated['template_language'] ?? null,
                'text_content' => $validated['text_content'] ?? null,
                'target_type' => $validated['target_type'],
                'target_id' => $validated['target_id'] ?? null,
                'status' => 'queued',
            ]);

            // Query contacts based on target type
            $contactsQuery = Contact::where('tenant_id', $tenantId);
            if ($validated['target_type'] === 'group') {
                $contactsQuery->whereHas('contactGroups', function ($q) use ($validated) {
                    $q->where('contact_groups.id', $validated['target_id']);
                });
            } elseif ($validated['target_type'] === 'tag') {
                $contactsQuery->whereHas('contactTags', function ($q) use ($validated) {
                    $q->where('contact_tags.id', $validated['target_id']);
                });
            }

            $recipients = [];
            $now = now();
            // Process in chunks to prevent memory issues for large lists
            $contactsQuery->select('id')->chunk(500, function ($contacts) use ($campaign, &$recipients, $now, $tenantId) {
                $inserts = [];
                foreach ($contacts as $c) {
                    $inserts[] = [
                        'id' => \Illuminate\Support\Str::uuid(),
                        'tenant_id' => $tenantId, // Ensure tenant scoping
                        'campaign_id' => $campaign->id,
                        'contact_id' => $c->id,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                \App\Models\CampaignRecipient::insert($inserts);
                $campaign->increment('total_recipients', count($inserts));
            });

            // Dispatch job
            SendCampaignJob::dispatch($campaign->id);
        });

        return back()->with('success', 'Campaign queued successfully.');
    }

    public function show($id)
    {
        $tenantId = auth()->user()->tenant_id;
        $campaign = Campaign::where('tenant_id', $tenantId)->withCount('recipients')->findOrFail($id);
        
        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign
        ]);
    }
}
