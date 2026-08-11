<?php

namespace App\Http\Controllers;

use App\Models\ContactTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactTagController extends Controller
{
    public function index()
    {
        $tags = ContactTag::where('tenant_id', auth()->user()->tenant_id)
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

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

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

        $tag = ContactTag::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $tag->update(['name' => $request->name]);

        return response()->json($tag);
    }

    public function destroy(string $id)
    {
        $tag = ContactTag::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $tag->delete();

        return response()->json(['message' => 'Tag deleted']);
    }
}
