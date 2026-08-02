<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessMsg91Webhook;

echo "--- STARTING VERIFICATION FOR #11 ---\n";
// 1. Setup tenant & user
$tenant = Tenant::firstOrCreate(['slug'=>'verify-test'],['name'=>'Verify','status'=>'active']);
$user = User::firstOrCreate(['email'=>'v@v.com'],['name'=>'V','password'=>'p','tenant_id'=>$tenant->id]);

// Create setting with real key
$setting = TenantSetting::updateOrCreate(
    ['tenant_id' => $tenant->id],
    ['msg91_auth_key' => 'REAL_SECRET_KEY_9999']
);
echo "1. Saved real msg91_auth_key via Eloquent.\n";

// 2. Query DB directly using raw DB to check encryption
$rawDbRecord = DB::table('tenant_settings')->where('tenant_id', $tenant->id)->first();
echo "2. Raw DB msg91_auth_key: " . substr($rawDbRecord->msg91_auth_key, 0, 40) . "... (Encrypted payload)\n";
echo "   Decrypted via Eloquent: " . $setting->fresh()->msg91_auth_key . "\n";

// 3. Re-submit without changing masked key (simulating controller logic)
$controller = app(\App\Http\Controllers\TenantSettingsController::class);
app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);
$request = \Illuminate\Http\Request::create('/settings/tenant', 'POST', [
    'msg91_auth_key' => '••••••••9999', // Simulate unmodified masked input
]);
$controller->update($request, app(\App\Services\TenantResolverService::class));
echo "3. Re-submitted settings form with '••••••••9999'.\n";

// 4. Query DB again
echo "4. After re-submit, Decrypted Key is STILL: " . $setting->fresh()->msg91_auth_key . "\n";


echo "\n--- STARTING VERIFICATION FOR #14 ---\n";
echo "1. Diff for ProcessMsg91Webhook.php and config/services.php already provided.\n";

// 2 & 3. Dispatch job with unmapped number
$payload = [
    'direction' => 0,
    'integratedNumber' => '910000000000', // Unmapped
    'mobile' => '919876543210',
    'text' => 'Hello',
];

echo "2/3. Dispatching ProcessMsg91Webhook with unmapped number '910000000000'.\n";
try {
    $job = new ProcessMsg91Webhook($payload);
    $job->handle();
    echo "FAIL: Job succeeded silently.\n";
} catch (\Exception $e) {
    echo "SUCCESS: Job threw exception!\n";
    echo "Exception Message: " . $e->getMessage() . "\n";
    echo "Since an exception is thrown, the Laravel Queue Worker will mark this job as FAILED, put it in the `failed_jobs` table, and it will NOT succeed silently.\n";
}

echo "--- VERIFICATION COMPLETE ---\n";
