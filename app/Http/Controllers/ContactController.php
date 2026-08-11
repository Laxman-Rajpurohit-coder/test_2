<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ContactImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        
        $query = Contact::where('tenant_id', $tenantId);
        
        // Basic search by phone or name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }
        
        // Cursor pagination for performance on large tables
        $contacts = $query->with('contactTags')->orderBy('created_at', 'desc')->paginate(50);
        
        $teamMembers = [];
        $user = auth()->user();
        $isOwnerOrAdmin = (method_exists($user, 'isOwner') && $user->isOwner()) || $user instanceof \App\Models\AdminUser;
        
        if ($isOwnerOrAdmin) {
            $teamMembers = \App\Models\User::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $allTags = \App\Models\ContactTag::where('tenant_id', $tenantId)->orderBy('name')->get();
        $approvedTemplates = \App\Models\WhatsappTemplate::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->get();
        
        $customFieldKeys = \App\Models\Contact::where('tenant_id', $tenantId)
            ->whereNotNull('custom_fields')
            ->get(['custom_fields'])
            ->flatMap(function ($contact) {
                return is_array($contact->custom_fields) ? array_keys($contact->custom_fields) : [];
            })
            ->unique()
            ->values()
            ->toArray();

        $availableContactFields = array_merge(['name', 'phone_number', 'email'], $customFieldKeys);
        
        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'teamMembers' => $teamMembers,
            'allTags' => $allTags,
            'approvedTemplates' => $approvedTemplates,
            'availableContactFields' => $availableContactFields
        ]);
    }
    
    public function store(Request $request, ContactImportService $importService)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:50',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        // Normalize phone number (strips characters, prepends 91 to 10-digit numbers)
        $normalizedPhone = \App\Support\PhoneNumber::normalize($validated['phone_number']);

        if (empty($normalizedPhone)) {
            return back()->with('error', 'Invalid phone number provided.');
        }

        Contact::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'phone_number' => $normalizedPhone,
            ],
            [
                'name' => $validated['name'] ?? null,
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'custom_fields' => [], // Ensure valid JSON structure
            ]
        );

        return back()->with('success', 'Contact added successfully.');
    }

    public function import(Request $request, ContactImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        
        // Verify the assigned user (if provided) belongs to the same tenant
        $assignedUserId = $request->input('assigned_user_id');
        if ($assignedUserId) {
            \App\Models\User::where('id', $assignedUserId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }

        $result = $importService->import($request->file('file'), $tenantId, $assignedUserId);

        if (!empty($result['errors'])) {
            return back()->with('warning', sprintf(
                'Imported %d, Updated %d. Encountered %d errors (e.g. %s)',
                $result['imported'],
                $result['updated'],
                count($result['errors']),
                $result['errors'][0]
            ));
        }

        return back()->with('success', sprintf(
            'Successfully imported %d new contacts and updated %d existing contacts.',
            $result['imported'],
            $result['updated']
        ));
    }
    
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['uuid'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        // Verify the assigned user (if provided) belongs to the same tenant
        if ($validated['assigned_user_id']) {
            $user = \App\Models\User::where('id', $validated['assigned_user_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }

        // Only update contacts that belong to this tenant
        Contact::where('tenant_id', $tenantId)
            ->whereIn('id', $validated['contact_ids'])
            ->update(['assigned_user_id' => $validated['assigned_user_id']]);

        return back()->with('success', 'Contacts assigned successfully.');
    }
    
    public function bulkAssignAll(Request $request)
    {
        $validated = $request->validate([
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        // Verify the assigned user (if provided) belongs to the same tenant
        if ($validated['assigned_user_id']) {
            \App\Models\User::where('id', $validated['assigned_user_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }

        // Update all contacts that belong to this tenant
        Contact::where('tenant_id', $tenantId)
            ->update(['assigned_user_id' => $validated['assigned_user_id']]);

        return back()->with('success', 'All contacts assigned successfully.');
    }
    
    public function bulkTag(Request $request)
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'uuid|exists:contacts,id',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'uuid|exists:contact_tags,id',
            'mode' => 'nullable|in:add,remove'
        ]);
        
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        
        $contacts = Contact::where('tenant_id', $tenantId)
            ->whereIn('id', $validated['contact_ids'])
            ->get();
            
        $mode = $validated['mode'] ?? 'add';
            
        foreach ($contacts as $contact) {
            if ($mode === 'remove') {
                $contact->contactTags()->detach($validated['tag_ids']);
            } else {
                $contact->contactTags()->syncWithoutDetaching($validated['tag_ids']);
            }
        }
        
        $message = $mode === 'remove' ? 'Tags removed successfully.' : 'Contacts tagged successfully.';
        return back()->with('success', $message);
    }

    public function quickSend(Request $request)
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'uuid|exists:contacts,id',
            'message_type' => 'required|in:text,template',
            'template_name' => 'required_if:message_type,template|string|nullable',
            'template_language' => 'required_if:message_type,template|string|nullable',
            'text_content' => 'required_if:message_type,text|string|nullable',
            'template_variable_map' => 'nullable|array',
        ]);
        
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        
        $contacts = Contact::where('tenant_id', $tenantId)
            ->whereIn('id', $validated['contact_ids'])
            ->get();
            
        if ($contacts->isEmpty()) {
            return back()->with('error', 'No valid contacts selected.');
        }

        if (!empty($validated['template_variable_map'])) {
            $customFieldKeys = Contact::where('tenant_id', $tenantId)
                ->whereNotNull('custom_fields')
                ->get(['custom_fields'])
                ->flatMap(function ($contact) {
                    return is_array($contact->custom_fields) ? array_keys($contact->custom_fields) : [];
                })
                ->unique()
                ->toArray();
            $allowedFields = array_merge(['name', 'phone_number', 'email'], $customFieldKeys);
            
            foreach ($validated['template_variable_map'] as $key => $field) {
                if (!in_array($field, $allowedFields)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'template_variable_map' => "Invalid template variable field: '{$field}'. Must be an existing contact field or custom field."
                    ]);
                }
            }
        }
        
        $campaign = \App\Models\Campaign::create([
            'tenant_id' => $tenantId,
            'name' => 'Quick Send - ' . now()->format('M d, H:i'),
            'message_type' => $validated['message_type'],
            'template_name' => $validated['template_name'] ?? null,
            'template_language' => $validated['template_language'] ?? null,
            'template_variable_map' => $validated['template_variable_map'] ?? null,
            'text_content' => $validated['text_content'] ?? null,
            'status' => 'queued',
            'target_type' => 'all', 
            'is_quick_send' => true,
            'total_recipients' => $contacts->count(),
        ]);
        
        $recipientsData = $contacts->map(function ($contact) use ($campaign, $tenantId) {
            return [
                'id' => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenantId,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();
        
        \App\Models\CampaignRecipient::insert($recipientsData);
        
        \App\Jobs\SendCampaignJob::dispatchAfterResponse($campaign->id);
        
        return back()->with('success', 'Message queued for sending.');
    }

    public function show(Request $request, $id)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $contact = Contact::where('tenant_id', $tenantId)
            ->with(['contactTags', 'assignedUser', 'contactGroups'])
            ->findOrFail($id);
            
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($contact);
        }

        $conversation = \App\Models\Conversation::where('tenant_id', $tenantId)
            ->where('customer_number', $contact->phone_number)
            ->first();

        $messages = [];
        if ($conversation) {
            $messages = \App\Models\WhatsappMessage::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $campaignHistory = \App\Models\CampaignRecipient::where('tenant_id', $tenantId)
            ->where('contact_id', $contact->id)
            ->with('campaign:id,name,status,message_type,created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $teamMembers = [];
        $user = auth()->user();
        $isOwnerOrAdmin = (method_exists($user, 'isOwner') && $user->isOwner()) || $user instanceof \App\Models\AdminUser;
        if ($isOwnerOrAdmin) {
            $teamMembers = \App\Models\User::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $allTags = \App\Models\ContactTag::where('tenant_id', $tenantId)->orderBy('name')->get();
        $approvedTemplates = \App\Models\WhatsappTemplate::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->get();

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'conversation' => $conversation,
            'messages' => $messages,
            'campaignHistory' => $campaignHistory,
            'allTags' => $allTags,
            'teamMembers' => $teamMembers,
            'approvedTemplates' => $approvedTemplates,
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);
        
        $contact->update($request->all());
        
        return response()->json($contact);
    }
    
    public function destroy($id)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);
        
        $contact->delete();
        
        return response()->json(['message' => 'Deleted']);
    }
}
