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
        $tenantId = auth()->user()->tenant_id;
        
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
        $contacts = $query->orderBy('id', 'desc')->paginate(50);
        
        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts
        ]);
    }
    
    public function import(Request $request, ContactImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $tenantId = auth()->user()->tenant_id;
        $result = $importService->import($request->file('file'), $tenantId);

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
    
    // Add show endpoint as requested by the test
    public function show($id)
    {
        $tenantId = auth()->user()->tenant_id;
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);
        
        return response()->json($contact);
    }
    
    public function update(Request $request, $id)
    {
        $tenantId = auth()->user()->tenant_id;
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);
        
        $contact->update($request->all());
        
        return response()->json($contact);
    }
    
    public function destroy($id)
    {
        $tenantId = auth()->user()->tenant_id;
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);
        
        $contact->delete();
        
        return response()->json(['message' => 'Deleted']);
    }
}
