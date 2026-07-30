<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

echo "\n--- TESTING EXIT IMPERSONATION ROUTE ---\n";

session()->put('impersonating_tenant_id', 1);
echo "1. Set impersonating_tenant_id = 1 in session.\n";

$controller = app(\App\Http\Controllers\Admin\ImpersonationController::class);
$response = $controller->stop();

echo "2. Controller->stop() executed.\n";
echo "Status Code: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 302) {
    echo "SUCCESS: Route returned 302 Redirect (expected), not 403.\n";
    echo "Redirect Target: " . $response->headers->get('Location') . "\n";
} else {
    echo "FAIL: Expected 302, got " . $response->getStatusCode() . "\n";
    echo "Response: " . $response->getContent() . "\n";
}

echo "Session impersonating_tenant_id after exit: " . (session()->has('impersonating_tenant_id') ? session('impersonating_tenant_id') : 'NULL (Cleared!)') . "\n";

echo "--- TEST COMPLETE ---\n";
