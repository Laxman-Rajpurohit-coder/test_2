<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$conv = DB::table('conversations')
    ->where('customer_number', 'like', '%8830718466%')
    ->first();

if (!$conv) {
    echo "No conversation found for 8830718466\n";
    exit;
}

echo "CONVERSATION ID: {$conv->id} | NUM: {$conv->customer_number} | LAST_MSG_AT: {$conv->last_message_at}\n";

$messages = DB::table('messages')
    ->where('conversation_id', $conv->id)
    ->orderBy('created_at', 'desc')
    ->take(15)
    ->get();

echo "=== LATEST 15 MESSAGES FOR 8830718466 ===\n";
foreach ($messages as $m) {
    echo "ID: {$m->id} | DIR: {$m->direction} | CREATED: {$m->created_at} | CONTENT: " . substr(is_string($m->content) ? $m->content : json_encode($m->content), 0, 100) . "\n";
}
