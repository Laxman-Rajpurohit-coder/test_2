<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\FlowBuilder\Models\Flow;
use Illuminate\Http\Request;
use Modules\FlowBuilder\Http\Controllers\FlowController;
use Modules\FlowBuilder\Services\FlowGraphValidatorService;

echo "\n--- RUNNING FLOW CONTROLLER CROSS-TENANT IDOR TEST ---\n";

$validator = app(FlowGraphValidatorService::class);
$controller = new FlowController($validator);

// Ensure Tenant 1 and Tenant 2 exist
\App\Models\Tenant::updateOrCreate(['id' => 1], ['name' => 'MTech Systems', 'slug' => 'mtech']);
\App\Models\Tenant::updateOrCreate(['id' => 2], ['name' => 'Global Logistics', 'slug' => 'global-logistics']);

// 1. Create a Flow for Tenant 2
app(\App\Services\TenantResolverService::class)->setActiveTenantId(2);
$flowId = \Illuminate\Support\Str::uuid()->toString();

$flow = Flow::create([
    'id' => $flowId,
    'name' => 'Tenant 2 Secret Flow',
    'graph' => [],
    'is_active' => true,
]);

echo "Created Flow for Tenant 2 with ID: {$flowId}\n";

// 2. Switch context to Tenant 1
app(\App\Services\TenantResolverService::class)->setActiveTenantId(1);
echo "Switched context to Tenant 1.\n";

// 3. Attempt to access Tenant 2's Flow as Tenant 1
echo "Attempting to access Tenant 2's flow via FlowController@show...\n";

try {
    $controller->show($flowId);
    echo "FAIL: Tenant 1 successfully accessed Tenant 2's flow! IDOR vulnerability present.\n";
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    echo "SUCCESS: Caught ModelNotFoundException. Tenant 1 cannot access Tenant 2's flow.\n";
    echo "Error details: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    echo "UNEXPECTED EXCEPTION: " . get_class($e) . " - " . $e->getMessage() . "\n";
}

echo "\n--- TEST COMPLETE ---\n";
