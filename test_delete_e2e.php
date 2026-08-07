<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Templates\Services\Msg91TemplateService;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Modules\Templates\Http\Controllers\TemplateController;
use App\Services\TenantResolverService;

$user = App\Models\User::first();
auth()->login($user);
$tenantId = $user->tenant_id;
$resolver = app(TenantResolverService::class);
$resolver->setActiveTenantId($tenantId);

$authKey = $resolver->getMsg91AuthKey($tenantId);
$number = $resolver->getIntegratedNumber($tenantId);

$service = new Msg91TemplateService();

echo "1. Creating test template on MSG91...\n";
$testName = 'test_delete_verify_' . time();
try {
    $service->create($authKey, $number, [
        'name' => $testName,
        'category' => 'MARKETING',
        'language' => 'en',
        'components' => [
            ['type' => 'BODY', 'text' => 'This is a test template to verify deletion.']
        ]
    ]);
    echo "Created successfully on MSG91: $testName\n";
} catch (\Exception $e) {
    echo "Failed to create on MSG91: " . $e->getMessage() . "\n";
    exit(1);
}

// Add to local DB so we can test the controller destroy method
$template = WhatsappTemplate::create([
    'tenant_id' => $tenantId,
    'name' => $testName,
    'language' => 'en',
    'category' => 'MARKETING',
    'status' => 'pending',
    'components' => [['type' => 'BODY', 'text' => 'This is a test template to verify deletion.']]
]);
echo "Added to local DB with ID: {$template->id}\n";

echo "\n2. Simulating Controller Delete...\n";
$controller = new TemplateController($service, $resolver);
try {
    $response = $controller->destroy($template->id);
    echo "Controller destroy executed.\n";
} catch (\Exception $e) {
    echo "Controller destroy threw exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n3. Verifying Deletion...\n";
$stillInDb = WhatsappTemplate::find($template->id);
echo "Local DB Check: " . ($stillInDb ? "FAILED (Still exists)" : "PASSED (Deleted)") . "\n";

echo "MSG91 Check: Fetching list from MSG91...\n";
$list = $service->list($authKey, $number);
$foundInMsg91 = false;
foreach ($list as $group) {
    if (($group['name'] ?? '') === $testName) {
        $foundInMsg91 = true;
        break;
    }
}
echo "MSG91 List Check: " . ($foundInMsg91 ? "FAILED (Still exists on MSG91)" : "PASSED (Deleted from MSG91)") . "\n";
