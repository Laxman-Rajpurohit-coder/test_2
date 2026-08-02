<?php

namespace Tests\Feature;

use Tests\TestCase;
use Modules\FlowBuilder\Models\Flow;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class FlowHttpTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    public function test_it_updates_flow_graph_via_http_put_endpoint()
    {
        $tenant = Tenant::first() ?? Tenant::factory()->create(['features' => ['flow_builder']]);
        $user = User::first() ?? User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        
        $tenant->features = ['flow_builder'];
        $tenant->save();

        $user->tenant_id = $tenant->id;
        $user->email_verified_at = now();
        $user->save();

        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $flow = Flow::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Initial Flow',
            'is_active' => true,
            'graph' => [
                'nodes' => [['id' => 'node_1', 'type' => 'message', 'data' => ['text' => 'hello']]], 
                'edges' => []
            ]
        ]);

        $multiNodeGraph = [
            'nodes' => [
                ['id' => 'node_start_1', 'type' => 'message', 'data' => ['text' => 'Welcome to the test flow!']],
                ['id' => 'node_question_2', 'type' => 'question', 'data' => ['text' => 'What is your email?', 'variable_name' => 'email']],
                ['id' => 'node_condition_3', 'type' => 'condition', 'data' => ['rules' => [['variable' => 'email', 'operator' => 'contains', 'value' => '@', 'target_node_id' => 'node_api_4']]]],
                ['id' => 'node_api_4', 'type' => 'api_call', 'data' => ['method' => 'GET', 'url' => 'https://api.github.com', 'response_variable' => 'github_data']]
            ],
            'edges' => [
                ['source' => 'node_start_1', 'target' => 'node_question_2'],
                ['source' => 'node_question_2', 'target' => 'node_condition_3']
            ]
        ];

        echo "== EXACT TEST PAYLOAD ==\n";
        echo "Edges:\n" . json_encode($multiNodeGraph['edges'], JSON_PRETTY_PRINT) . "\n";
        echo "Condition Node Target:\n" . json_encode($multiNodeGraph['nodes'][2]['data']['rules'][0]['target_node_id'], JSON_PRETTY_PRINT) . "\n";
        echo "========================\n\n";

        $response = $this->actingAs($user)
            ->putJson("/flows/{$flow->id}", [
                'name' => 'Updated Flow via HTTP',
                'is_active' => true,
                'graph' => $multiNodeGraph
            ], [
                'X-Inertia' => 'true'
            ]);

        // Assert no validation/server errors in session
        $response->assertSessionHasNoErrors();
        
        // Assert redirect back (standard Inertia behavior for success)
        $response->assertStatus(302);

        $reloaded = Flow::find($flow->id);
        
        echo "HTTP STATUS: " . $response->getStatusCode() . "\n";
        echo "RELOADED NAME: " . $reloaded->name . "\n";
        echo "NODE COUNT: " . count($reloaded->graph['nodes']) . "\n";
        echo "EDGE COUNT: " . count($reloaded->graph['edges']) . "\n";
    }
}
