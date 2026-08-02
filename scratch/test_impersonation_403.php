<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\ChatController;

echo "\n--- RUNNING IMPERSONATION 403 TEST ---\n";

// 1. Simulate an Impersonation Session
session()->put('impersonating_tenant_id', 1);
echo "Simulated impersonation session started for Tenant ID 1.\n";

// 2. Instantiate Controller
$controller = app(ChatController::class);

// 3. Create a fake request for store()
$request = Request::create('/api/conversations/123/messages', 'POST', [
    'content' => 'Hello this is an unauthorized send',
    'type' => 'text'
]);

// 4. Execute the controller method
$response = $controller->store($request, '123');

if ($response->getStatusCode() === 403) {
    echo "SUCCESS: store() returned 403 Forbidden.\n";
    echo "Response data: " . $response->getContent() . "\n";
} else {
    echo "FAIL: store() returned " . $response->getStatusCode() . "\n";
}

// 5. Test storeMedia()
$requestMedia = Request::create('/api/conversations/123/media', 'POST', [
    'type' => 'image'
]);

$responseMedia = $controller->storeMedia($requestMedia, '123');

if ($responseMedia->getStatusCode() === 403) {
    echo "SUCCESS: storeMedia() returned 403 Forbidden.\n";
    echo "Response data: " . $responseMedia->getContent() . "\n";
} else {
    echo "FAIL: storeMedia() returned " . $responseMedia->getStatusCode() . "\n";
}

echo "\n--- TEST COMPLETE ---\n";
