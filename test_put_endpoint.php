<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Services\TenantResolverService;

$user = App\Models\User::first();
auth()->login($user);
$tenantId = $user->tenant_id;
$resolver = app(TenantResolverService::class);
$resolver->setActiveTenantId($tenantId);

$authKey = $resolver->getMsg91AuthKey($tenantId);
$number = $resolver->getIntegratedNumber($tenantId);

$testName = 'test_put_verify_' . time();

echo "1. Creating template on MSG91...\n";
$payload = [
    'integrated_number' => $number,
    'template_name' => $testName,
    'language' => 'en',
    'category' => 'MARKETING',
    'components' => [
        ['type' => 'BODY', 'text' => 'This is the initial text.']
    ]
];

$postResponse = Http::withHeaders([
    'authkey' => $authKey,
    'Content-Type' => 'application/json',
])->post("https://api.msg91.com/api/v5/whatsapp/client-panel-template/", $payload);

echo "POST Response: " . $postResponse->body() . "\n";

if ($postResponse->failed() || json_decode($postResponse->body())->hasError ?? false) {
    echo "Creation failed. Cannot test PUT.\n";
    exit(1);
}

// Give MSG91 a second to process
sleep(2);

echo "\n2. Attempting to update template via PUT...\n";
$putPayload = [
    'integrated_number' => $number,
    'template_name' => $testName,
    'language' => 'en', // sometimes required to specify what language version you're editing
    'category' => 'MARKETING',
    'components' => [
        ['type' => 'BODY', 'text' => 'This is the UPDATED text via PUT.']
    ]
];

$putResponse = Http::withHeaders([
    'authkey' => $authKey,
    'Content-Type' => 'application/json',
])->put("https://api.msg91.com/api/v5/whatsapp/client-panel-template/", $putPayload);

echo "PUT Response: " . $putResponse->body() . "\n";

// Clean up
echo "\n3. Cleaning up (Deleting)...\n";
$url = "https://api.msg91.com/api/v5/whatsapp/client-panel-template/?integrated_number={$number}&template_name={$testName}";
$delResponse = Http::withHeaders([
    'authkey' => $authKey,
])->delete($url);
echo "Delete Response: " . $delResponse->body() . "\n";
