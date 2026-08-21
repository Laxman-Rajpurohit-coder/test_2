<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

app(\App\Services\TenantResolverService::class)->setActiveTenantId(7);

try {
    $m = \App\Models\Message::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => 7,
        'conversation_id' => 189,
        'direction' => 'inbound',
        'status' => 'received',
        'content' => json_encode(['type' => 'interactive', 'text' => 'Menu options']),
        'created_at' => now(),
    ]);

    $res = \Illuminate\Support\Facades\DB::table('messages')
        ->where('id', $m->id)
        ->selectRaw("content, json_valid(content) as is_valid, json_extract(content, '$.type') as extracted_type")
        ->first();
    
    echo "Content: " . $res->content . "\n";
    echo "Is Valid: " . $res->is_valid . "\n";
    echo "Extracted Type: '" . $res->extracted_type . "'\n";
    
    $m->delete();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
