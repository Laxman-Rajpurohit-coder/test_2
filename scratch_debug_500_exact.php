<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::withoutGlobalScopes()->where('tenant_id', 7)->first();
auth()->login($user);
app(\App\Services\TenantResolverService::class)->setActiveTenantId(7);

try {
    $request = \Illuminate\Http\Request::create('/api/conversations', 'GET', [
        'channel' => 'whatsapp',
        'limit' => 40
    ]);
    
    $controller = app(\App\Http\Controllers\ChatController::class);
    $response = $controller->index($request);
    
    echo "SUCCESS: " . $response->getStatusCode() . "\n";
    echo "CONTENT: " . substr($response->getContent(), 0, 300) . "\n";
} catch (\Throwable $e) {
    echo "EXACT ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "TRACE:\n" . $e->getTraceAsString() . "\n";
}
