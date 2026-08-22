<?php

namespace App\Http\Controllers;

use App\Models\ContactTag;
use App\Services\TenantResolverService;
use Illuminate\Http\Request;

class ContactTagController extends Controller
{
    public function index()
    {
        $tenantId = app(TenantResolverService::class)->getActiveTenantId();
        $tags = ContactTag::where('tenant_id', $tenantId)
            ->withCount('contacts')
            ->orderBy('name')
            ->get();
            
        return response()->json($tags);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $tenantId = app(TenantResolverService::class)->getActiveTenantId();

        $tag = ContactTag::firstOrCreate([
            'tenant_id' => $tenantId,
            'name' => trim($validated['name'])
        ]);

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Tag created successfully.');
        }

        return response()->json($tag);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $tenantId = app(TenantResolverService::class)->getActiveTenantId();
        $tag = ContactTag::where('tenant_id', $tenantId)->findOrFail($id);
        $tag->update(['name' => $request->name]);

        return response()->json($tag);
    }

    public function destroy(string $id)
    {
        $tenantId = app(TenantResolverService::class)->getActiveTenantId();
        $tag = ContactTag::where('tenant_id', $tenantId)->findOrFail($id);
        $tag->delete();

        return response()->json(['message' => 'Tag deleted']);
    }
}
