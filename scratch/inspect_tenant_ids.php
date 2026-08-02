<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CONVERSATIONS BY TENANT ID ===\n";
$convs = DB::table('conversations')->select('tenant_id', DB::raw('count(*) as total'))->groupBy('tenant_id')->get();
foreach ($convs as $c) {
    echo "Tenant ID " . var_export($c->tenant_id, true) . ": " . $c->total . " conversations\n";
}

echo "\n=== WHATSAPP MESSAGES BY TENANT ID ===\n";
$msgs = DB::table('whatsapp_messages')->select('tenant_id', DB::raw('count(*) as total'))->groupBy('tenant_id')->get();
foreach ($msgs as $m) {
    echo "Tenant ID " . var_export($m->tenant_id, true) . ": " . $m->total . " messages\n";
}
