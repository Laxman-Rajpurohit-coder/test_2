<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request, \App\Services\ContactIngestionService $ingestionService)
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'whatsapp_consent' => 'nullable|boolean',
            'custom_fields' => 'nullable|array',
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        try {
            $contact = $ingestionService->ingest($tenantId, array_merge($validated, [
                'source' => 'Public API',
                'whatsapp_consent' => $validated['whatsapp_consent'] ?? true,
            ]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Invalid phone number format.'], 422);
        }

        return response()->json([
            'message' => $contact->wasRecentlyCreated ? 'Contact created successfully.' : 'Contact updated successfully.',
            'contact' => $contact,
        ], $contact->wasRecentlyCreated ? 201 : 200);
    }
}
