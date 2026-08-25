<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Message;
use Carbon\Carbon;

// Test filter for today 2026-08-25, Inbound, Image
$startDate = '2026-08-25T00:00';
$endDate = '2026-08-25T23:59';
$direction = 'inbound';
$type = 'image';

$query = Message::withoutGlobalScopes()
    ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
    ->select('messages.*', 'conversations.customer_number');

if ($direction) {
    $query->where('messages.direction', $direction);
}

if ($startDate) {
    $query->where('messages.created_at', '>=', Carbon::parse($startDate));
}

if ($endDate) {
    $query->where('messages.created_at', '<=', Carbon::parse($endDate));
}

if ($type) {
    $query->where('messages.content', 'like', '%"type":"' . $type . '"%');
}

$results = $query->orderBy('messages.created_at', 'desc')->get();

echo "Matching results count: " . $results->count() . "\n";
foreach ($results as $res) {
    echo "ID: {$res->id} | CREATED: {$res->created_at} | DIR: {$res->direction}\n";
}
