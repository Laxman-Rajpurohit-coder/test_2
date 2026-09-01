<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$settings = \App\Models\TenantSetting::all();
echo "=== TENANT SETTINGS TABLE DUMP ===\n";
foreach ($settings as $s) {
    echo "Tenant ID: {$s->tenant_id}\n";
    echo "MSG91 Auth Key: " . ($s->msg91_auth_key ? $s->msg91_auth_key : "NULL / Empty") . "\n";
}

echo "\n.env MSG91_AUTH_KEY: " . env('MSG91_AUTH_KEY') . "\n";
