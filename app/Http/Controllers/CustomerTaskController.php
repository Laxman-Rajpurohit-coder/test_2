<?php

namespace App\Http\Controllers;

use App\Models\CustomerTask;
use App\Services\TenantResolverService;
use Illuminate\Http\Request;

class CustomerTaskController extends Controller
{
    /**
     * Retrieves tasks associated with a given conversation.
     */
    public function index($conversationId)
    {
        $tasks = CustomerTask::where('conversation_id', $conversationId)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('due_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Stores a new customer task / reminder.
     */
    public function store(Request $request, TenantResolverService $resolver)
    {
        $tenantId = $resolver->getActiveTenantId();

        $validated = $request->validate([
            'contact_id' => 'nullable|exists:contacts,id',
            'conversation_id' => 'nullable|exists:conversations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'due_at' => 'nullable|date|after_or_equal:now',
            'type' => 'nullable|string|in:task,auto_message',
            'template_name' => 'nullable|string|max:255',
            'template_language' => 'nullable|string|max:10',
            'template_components' => 'nullable|array',
        ]);

        $task = CustomerTask::create([
            'tenant_id' => $tenantId,
            'contact_id' => $validated['contact_id'] ?? null,
            'conversation_id' => $validated['conversation_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'status' => 'open',
            'type' => $validated['type'] ?? 'task',
            'template_name' => $validated['template_name'] ?? null,
            'template_language' => $validated['template_language'] ?? null,
            'template_components' => $validated['template_components'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task reminder created successfully.',
                'task' => $task
            ]);
        }

        return redirect()->back()->with('success', 'Task reminder created successfully.');
    }

    /**
     * Updates the status of an existing task (e.g. resolve or in progress).
     * Since CustomerTask uses BelongsToTenant, Eloquent automatically appends
     * the tenant scope check on firstOrFail(), returning a 404 ModelNotFoundException
     * if the task does not belong to the active tenant.
     */
    public function updateStatus(Request $request, $id)
    {
        $task = CustomerTask::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved',
        ]);

        $task->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Task status updated successfully.');
    }

    /**
     * Deletes a task.
     * Enforces tenant context automatically via BelongsToTenant.
     */
    public function destroy($id)
    {
        $task = CustomerTask::findOrFail($id);
        $task->delete();

        return redirect()->back()->with('success', 'Task reminder deleted successfully.');
    }
}
