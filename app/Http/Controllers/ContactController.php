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
        $contacts = $query->orderBy('created_at', 'desc')->paginate(50);
        
        $teamMembers = [];
        $user = auth()->user();
        $isOwnerOrAdmin = (method_exists($user, 'isOwner') && $user->isOwner()) || $user instanceof \App\Models\AdminUser;
        
        if ($isOwnerOrAdmin) {
            $teamMembers = \App\Models\User::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }
        
        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'teamMembers' => $teamMembers
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
        $normalizedPhone = $importService->normalizePhoneNumber($validated['phone_number']);

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
    
    // Add show endpoint as requested by the test
    public function show($id)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);
        
        return response()->json($contact);
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
