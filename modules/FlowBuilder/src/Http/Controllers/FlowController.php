<?php

namespace Modules\FlowBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Modules\FlowBuilder\Models\Flow;
use Modules\FlowBuilder\Services\FlowGraphValidatorService;

class FlowController extends Controller
{
    protected FlowGraphValidatorService $validator;

    public function __construct(FlowGraphValidatorService $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Display Flow List Page (Scoped by BelongsToTenant)
     */
    public function index()
    {
        $flows = Flow::withCount(['sessions' => function ($query) {
            $query->where('status', 'active');
        }])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(fn($f) => [
            'id'              => $f->id,
            'name'            => $f->name,
            'is_active'       => $f->is_active,
            'active_sessions' => $f->sessions_count,
            'created_at'      => $f->created_at ? $f->created_at->diffForHumans() : 'Just now',
        ]);

        return Inertia::render('Modules/FlowBuilder/Workspace', [
            'flows'       => $flows,
            'active_flow' => null,
        ]);
    }

    /**
     * Store new Flow
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'graph' => 'nullable|array',
        ]);

        $defaultGraph = [
            'nodes' => [
                [
                    'id'   => 'node_start_1',
                    'type' => 'message',
                    'data' => ['text' => 'Welcome to our WhatsApp service!', 'is_start' => true],
                ]
            ],
            'edges' => [],
        ];

        $graph = $validated['graph'] ?? $defaultGraph;

        // Validate Graph Schema & SSRF Guards
        try {
            $this->validator->validateGraph($graph);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['graph' => $e->getMessage()]);
        }

        $flow = Flow::create([
            'id'        => Str::uuid()->toString(),
            'name'      => $validated['name'],
            'graph'     => $graph,
            'is_active' => true,
        ]);

        return redirect()->route('flows.show', $flow->id)->with('success', 'Flow created successfully.');
    }

    /**
     * Render Visual Flow Editor Canvas within Workspace
     */
    public function show(string $id)
    {
        // 1. Fetch all flows for the sidebar
        $flows = Flow::withCount(['sessions' => function ($query) {
            $query->where('status', 'active');
        }])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(fn($f) => [
            'id'              => $f->id,
            'name'            => $f->name,
            'is_active'       => $f->is_active,
            'active_sessions' => $f->sessions_count,
            'created_at'      => $f->created_at ? $f->created_at->diffForHumans() : 'Just now',
        ]);

        // 2. Fetch specific flow for the canvas
        $flow = Flow::findOrFail($id);

        return Inertia::render('Modules/FlowBuilder/Workspace', [
            'flows'       => $flows,
            'active_flow' => [
                'id'        => $flow->id,
                'name'      => $flow->name,
                'graph'     => $flow->graph,
                'is_active' => $flow->is_active,
            ]
        ]);
    }

    /**
     * Update Flow Graph Schema & Settings with Save-Time SSRF Validation
     */
    public function update(Request $request, string $id)
    {
        $flow = Flow::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'graph'     => 'sometimes|required|array',
        ]);

        if (isset($validated['graph'])) {
            try {
                // Save-Time Node Schema & SSRF Guard Validation
                $this->validator->validateGraph($validated['graph']);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['graph' => $e->getMessage()]);
            }
        }

        $flow->update($validated);

        return back()->with('success', 'Flow graph saved successfully.');
    }

    /**
     * Delete Flow
     */
    public function destroy(string $id)
    {
        $flow = Flow::findOrFail($id);
        $flow->delete();

        return redirect()->route('flows.index')->with('success', 'Flow deleted successfully.');
    }
}
