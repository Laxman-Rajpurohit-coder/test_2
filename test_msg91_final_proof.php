<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenantId = 7;
app(App\Services\TenantResolverService::class)->setActiveTenantId($tenantId);
$authKey = app(App\Services\TenantResolverService::class)->getMsg91AuthKey();
$number = Illuminate\Support\Facades\DB::table('tenant_numbers')->where('tenant_id', $tenantId)->first()->integrated_number;

$service = app(Modules\Templates\Services\Msg91TemplateService::class);

echo "1. TESTING CREATE TEMPLATE (sanitized payload)...\n";
$templateName = 'test_api_abc_123_' . time();
$payload = [
    'name' => $templateName,
    'language' => 'en_US',
    'category' => 'UTILITY',
    'components' => [
        [
            'type' => 'BODY',
            // 'format' => 'TEXT',  <-- Removed format
            'text' => 'This is a test template for API verification.'
        ]
    ]
];

try {
    $createResult = $service->create($authKey, $number, $payload);
    echo "SUCCESS! Response Payload:\n";
    print_r($createResult);
} catch (\Exception $e) {
    echo "FAILED! " . $e->getMessage() . "\n";
}

echo "\n----------------------------------------\n\n";

echo "2. TESTING DELETE TEMPLATE (Body JSON)...\n";
$resBody = Illuminate\Support\Facades\Http::withHeaders(['authkey' => $authKey, 'Content-Type' => 'application/json'])
    ->send('DELETE', "https://api.msg91.com/api/v5/whatsapp/client-panel-template/{$templateName}/", [
        'json' => ['integrated_number' => $number]
    ]);
echo "Status: " . $resBody->status() . "\n";
echo "Body: " . $resBody->body() . "\n\n";

echo "3. TESTING DELETE TEMPLATE (Query Params)...\n";
$resQuery = Illuminate\Support\Facades\Http::withHeaders(['authkey' => $authKey, 'Content-Type' => 'application/json'])
    ->delete("https://api.msg91.com/api/v5/whatsapp/client-panel-template/{$templateName}/", [
        'integrated_number' => $number
    ]);
echo "Status: " . $resQuery->status() . "\n";
echo "Body: " . $resQuery->body() . "\n\n";

