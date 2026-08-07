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

echo "1. Fetching templates...\n";
$templates = $service->list($authKey, $number);

$targetTemplate = null;
foreach ($templates as $t) {
    if (str_starts_with($t['name'] ?? '', 'test_api_abc')) {
        $targetTemplate = $t['name'];
        break;
    }
}

if (!$targetTemplate) {
    echo "No test_api_abc templates found to delete. I will create a new one, wait a few seconds, list again, and then delete it.\n";
    
    $payload = [
        'name' => 'test_api_abc_proof_' . time(),
        'language' => 'en_US',
        'category' => 'UTILITY',
        'components' => [
            ['type' => 'BODY', 'text' => 'Final proof template.']
        ]
    ];
    $createResult = $service->create($authKey, $number, $payload);
    echo "Created: " . json_encode($createResult) . "\n";
    
    echo "Waiting 5 seconds...\n";
    sleep(5);
    
    $templates = $service->list($authKey, $number);
    foreach ($templates as $t) {
        if (str_starts_with($t['name'] ?? '', 'test_api_abc_proof')) {
            $targetTemplate = $t['name'];
            break;
        }
    }
}

if ($targetTemplate) {
    echo "2. Deleting template: $targetTemplate\n";
    try {
        $res = Illuminate\Support\Facades\Http::withHeaders([
            'authkey' => $authKey,
            'Content-Type' => 'application/json'
        ])->delete("https://api.msg91.com/api/v5/whatsapp/client-panel-template/", [
            'integrated_number' => $number,
            'template_name' => $targetTemplate
        ]);
        
        echo "Status: " . $res->status() . "\n";
        echo "Body: " . $res->body() . "\n";
    } catch (\Exception $e) {
        echo "FAILED! " . $e->getMessage() . "\n";
    }
} else {
    echo "Still couldn't find the created template in the list API. MSG91 might have a delay.\n";
}
