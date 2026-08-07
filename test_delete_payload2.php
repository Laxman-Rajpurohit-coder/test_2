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

$testName = 'test_delete_verify_1785994630';

echo "Attempting to delete $testName with query params in URL...\n";
$url = "https://api.msg91.com/api/v5/whatsapp/client-panel-template/?integrated_number={$number}&template_name={$testName}";
$response = Http::withHeaders([
    'authkey' => $authKey,
])->delete($url);
echo "Delete (URL query) Response: " . $response->body() . "\n";

echo "Attempting to delete $testName with form data...\n";
$response2 = Http::asForm()->withHeaders([
    'authkey' => $authKey,
])->delete("https://api.msg91.com/api/v5/whatsapp/client-panel-template/", [
    'integrated_number' => $number,
    'template_name' => $testName
]);
echo "Delete (Form) Response: " . $response2->body() . "\n";
