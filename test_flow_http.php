<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Boot the application fully using the Console Kernel so Eloquent is ready
$consoleKernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$consoleKernel->bootstrap();

$tenant = \App\Models\Tenant::first() ?? \App\Models\Tenant::create(['id' => 1, 'name' => 'Test Tenant', 'features' => []]);
$user = \App\Models\User::first();
if (!$user) {
    $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
} else {
    $user->tenant_id = $tenant->id;
    $user->save();
}

app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

$flow = \Modules\FlowBuilder\Models\Flow::create([
    'id' => \Illuminate\Support\Str::uuid()->toString(),
    'name' => 'Initial Flow',
    'is_active' => true,
    'graph' => [
        'nodes' => [['id' => 'node_1', 'type' => 'message', 'data' => ['text' => 'hello']]], 
        'edges' => []
    ]
]);

$multiNodeGraph = [
    'nodes' => [
        [
            'id' => 'node_start_1',
            'type' => 'message',
            'data' => ['text' => 'Welcome to the test flow!']
        ],
        [
            'id' => 'node_question_2',
            'type' => 'question',
            'data' => ['text' => 'What is your email?', 'variable_name' => 'email']
        ],
        [
            'id' => 'node_condition_3',
            'type' => 'condition',
            'data' => [
                'rules' => [
                    [
                        'variable' => 'email',
                        'operator' => 'contains',
                        'value' => '@',
                        'target_node_id' => 'node_api_4'
                    ]
                ]
            ]
        ],
        [
            'id' => 'node_api_4',
            'type' => 'api_call',
            'data' => [
                'method' => 'GET',
                'url' => 'https://api.github.com',
                'response_variable' => 'github_data'
            ]
        ]
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

// Login
auth()->login($user);

// Now process the HTTP Request
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = \Illuminate\Http\Request::create(
    '/flows/' . $flow->id,
    'PUT',
    [], // query
    [], // cookies
    [], // files
    ['HTTP_X-Inertia' => 'true', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], // server
    json_encode([
        'name' => 'Updated Flow via HTTP',
        'is_active' => true,
        'graph' => $multiNodeGraph
    ])
);

$response = $httpKernel->handle($request);

echo "HTTP STATUS: " . $response->getStatusCode() . "\n";

if (in_array($response->getStatusCode(), [200, 302, 303])) {
    $reloaded = \Modules\FlowBuilder\Models\Flow::find($flow->id);
    echo "RELOADED NAME: " . $reloaded->name . "\n";
    echo "NODE COUNT: " . count($reloaded->graph['nodes']) . "\n";
    echo "EDGE COUNT: " . count($reloaded->graph['edges']) . "\n";
    if (session()->has('errors')) {
        echo "SESSION ERRORS: " . json_encode(session('errors')->all()) . "\n";
    }
} else {
    echo "ERROR RESPONSE: " . $response->getContent() . "\n";
}
