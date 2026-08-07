<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenantId = 7;
app(App\Services\TenantResolverService::class)->setActiveTenantId($tenantId);
$authKey = app(App\Services\TenantResolverService::class)->getMsg91AuthKey();
$number = Illuminate\Support\Facades\DB::table('tenant_numbers')->where('tenant_id', $tenantId)->first()->integrated_number;

$templateName = 'test_api_abc_123';

echo "TESTING DELETE TEMPLATE (Query String built-in)...\n";
$resQuery = Illuminate\Support\Facades\Http::withHeaders(['authkey' => $authKey])
    ->delete("https://api.msg91.com/api/v5/whatsapp/client-panel-template/{$templateName}/?integrated_number={$number}");
echo "Status: " . $resQuery->status() . "\n";
echo "Body: " . $resQuery->body() . "\n\n";

