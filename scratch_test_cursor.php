<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::withoutGlobalScopes()->where('tenant_id', 7)->first();
auth()->login($user);
app(\App\Services\TenantResolverService::class)->setActiveTenantId(7);

try {
    $query = \App\Models\Conversation::orderBy('is_favorite', 'desc')
        ->orderBy('last_message_at', 'desc')
        ->orderBy('id', 'desc')
        ->with(['latestMessage']);
        
    $paginator = $query->cursorPaginate(40);
    echo "SUCCESS! TOTAL ITEMS IN PAGE: " . count($paginator->items()) . "\n";
} catch (\Throwable $e) {
    echo "EXACT EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
