<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$messages = DB::table('messages')->latest()->take(20)->get();

foreach ($messages as $m) {
    echo "ID: {$m->id} | DIR: {$m->direction} | CREATED: {$m->created_at}\n";
    echo "CONTENT: " . substr($m->content ?? '', 0, 150) . "\n-------------------\n";
}
