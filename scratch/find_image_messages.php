<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$images = DB::table('messages')
    ->where('content', 'like', '%"type":"image"%')
    ->orWhere('content', 'like', '%"type":"photo"%')
    ->orWhere('content', 'like', '%media_url%')
    ->orWhere('content', 'like', '%.jpg%')
    ->orWhere('content', 'like', '%.png%')
    ->get();

echo "Total matching image/media messages in database: " . count($images) . "\n";
foreach ($images as $img) {
    echo "ID: {$img->id} | DIR: {$img->direction} | CREATED: {$img->created_at}\n";
    echo "CONTENT: " . substr($img->content ?? '', 0, 150) . "\n-------------------\n";
}
