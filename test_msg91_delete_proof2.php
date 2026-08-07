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

if ($targetTemplate) {
    echo "2. Deleting template: $targetTemplate\n";
    try {
        // Explicitly build the query string to guarantee MSG91 parses it correctly
        $query = http_build_query([
            'integrated_number' => $number,
            'template_name' => $targetTemplate
        ]);
        
        $res = Illuminate\Support\Facades\Http::withHeaders([
            'authkey' => $authKey,
        ])->delete("https://api.msg91.com/api/v5/whatsapp/client-panel-template/?{$query}");
        
        echo "Status: " . $res->status() . "\n";
        echo "Body: " . $res->body() . "\n";
    } catch (\Exception $e) {
        echo "FAILED! " . $e->getMessage() . "\n";
    }
} else {
    echo "No test template found.\n";
}
