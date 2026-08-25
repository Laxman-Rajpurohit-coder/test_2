<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$messages = DB::table('messages')
    ->latest()
    ->take(30)
    ->get(['id', 'direction', 'content', 'created_at']);

echo "=== PRODUCTION RECENT MESSAGES ===\n";
foreach ($messages as $m) {
    $contentStr = is_string($m->content) ? $m->content : json_encode($m->content);
    $typeStr = 'UNKNOWN';
    if ($contentStr) {
        $parsed = json_decode($contentStr, true);
        if (is_array($parsed)) {
            $typeStr = $parsed['type'] ?? $parsed['message_type'] ?? 'NO_TYPE';
        }
    }
    echo "ID: {$m->id} | DIR: {$m->direction} | PARSED_TYPE: {$typeStr} | RAW: " . substr($contentStr, 0, 100) . "\n";
}
