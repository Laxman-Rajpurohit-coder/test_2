<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::withoutGlobalScopes()->where('tenant_id', 7)->first();
if (!$user) {
    echo "USER NOT FOUND FOR TENANT 7!\n";
    exit(1);
}

auth()->login($user);
app(\App\Services\TenantResolverService::class)->setActiveTenantId(7);

try {
    $request = \Illuminate\Http\Request::create('/api/conversations', 'GET', [
        'channel' => 'whatsapp',
        'limit' => 40
    ]);
    
    $controller = app(\App\Http\Controllers\ChatController::class);
    $response = $controller->index($request);
    
    echo "STATUS: " . $response->getStatusCode() . "\n";
    echo "BODY PREVIEW:\n" . substr($response->getContent(), 0, 500) . "\n";
} catch (\Throwable $e) {
    echo "================ EXACT 500 ERROR TRACE ================\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo "IN " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo $e->getTraceAsString() . "\n";
    echo "=======================================================\n";
}
