<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

app(App\Services\TenantResolverService::class)->setActiveTenantId(7);

$t = App\Models\WhatsappTemplate::first();
echo json_encode($t->toArray(), JSON_PRETTY_PRINT);
