<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = App\Models\Tenant::find(7);
echo "Current features: " . json_encode($tenant->features) . "\n";

$features = $tenant->features ?? [];
$features['bot_auto_responder'] = true;
$tenant->features = $features;
$tenant->save();
echo "Forced bot_auto_responder to true.\n";
