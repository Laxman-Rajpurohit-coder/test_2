<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactTag;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::withCount('recipients')->orderBy('created_at', 'desc')->paginate(20);
        $approvedTemplates = WhatsappTemplate::where('status', 'approved')
            ->select('id', 'name', 'language', 'category')
            ->get();
            
        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'approvedTemplates' => $approvedTemplates,
        ]);
    }

    public function create()
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $approvedTemplates = WhatsappTemplate::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->get();
        
        $groups = ContactGroup::where('tenant_id', $tenantId)->get();
        $tags = ContactTag::where('tenant_id', $tenantId)->get();

        return Inertia::render('Campaigns/Create', [
            'approvedTemplates' => $approvedTemplates,
            'groups' => $groups,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'message_type'          => 'required|in:text,template',
            'template_name'         => 'nullable|required_if:message_type,template|string',
            'template_language'     => 'nullable|required_if:message_type,template|string',
            // Ordered array: index 0 → {{1}}, index 1 → {{2}}, etc.
            // Each value is a contact field name (e.g. "first_name", "city").
            'template_variable_map'   => 'nullable|array',
            'template_variable_map.*' => 'string|max:64',
            'text_content'          => 'nullable|required_if:message_type,text|string',
            'target_type'           => 'required|in:all,group,tag',
            'target_id'             => 'nullable|uuid',
            'scheduled_at'          => 'nullable|date',
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        // Verify target isolation
        if ($validated['target_type'] === 'group') {
            ContactGroup::where('tenant_id', $tenantId)->findOrFail($validated['target_id']);
        } elseif ($validated['target_type'] === 'tag') {
            ContactTag::where('tenant_id', $tenantId)->findOrFail($validated['target_id']);
        }

        DB::transaction(function () use ($validated, $tenantId) {
            $isScheduled = !empty($validated['scheduled_at']);
            $status = $isScheduled ? 'scheduled' : 'queued';

            $campaign = Campaign::create([
                'tenant_id'             => $tenantId,
                'name'                  => $validated['name'],
                'message_type'          => $validated['message_type'],
                'template_name'         => $validated['template_name'] ?? null,
                'template_language'     => $validated['template_language'] ?? null,
                'template_variable_map' => $validated['template_variable_map'] ?? null,
                'text_content'          => $validated['text_content'] ?? null,
                'target_type'           => $validated['target_type'],
                'target_id'             => $validated['target_id'] ?? null,
                'scheduled_at'          => $isScheduled ? \Carbon\Carbon::parse($validated['scheduled_at'])->setTimezone(config('app.timezone')) : null,
                'status'                => $status,
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
            if ($isScheduled) {
                SendCampaignJob::dispatch($campaign->id)->delay($campaign->scheduled_at);
            } else {
                SendCampaignJob::dispatch($campaign->id);
            }
        });

        return back()->with('success', 'Campaign created successfully.');
    }

    public function show($id)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $campaign = Campaign::where('tenant_id', $tenantId)->withCount('recipients')->findOrFail($id);
        
        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign
        ]);
    }

    public function cancel($id)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        if (!in_array($campaign->status, ['scheduled', 'queued', 'sending'])) {
            return back()->with('error', 'Only active or scheduled campaigns can be cancelled.');
        }

        $campaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Campaign cancelled successfully.');
    }
}
