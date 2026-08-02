<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;

echo "\n--- RUNNING OUTBOUND NUMBER ISOLATION TEST ---\n";

// 1. Setup Tenant 1 (MTech) Number
$tenant1 = Tenant::find(1) ?: Tenant::create(['id' => 1, 'name' => 'MTech Systems', 'slug' => 'mtech']);
DB::table('tenant_numbers')->updateOrInsert(
    ['tenant_id' => 1],
    ['integrated_number' => '917425889008', 'created_at' => now(), 'updated_at' => now()]
);

// 2. Setup Tenant 2 (Global Logistics) Number
$tenant2 = Tenant::find(2) ?: Tenant::create(['id' => 2, 'name' => 'Global Logistics', 'slug' => 'global-logistics']);
DB::table('tenant_numbers')->updateOrInsert(
    ['tenant_id' => 2],
    ['integrated_number' => '12025550123', 'created_at' => now(), 'updated_at' => now()]
);

// 3. Create dummy conversations
DB::table('conversations')->whereIn('customer_number', ['919876543210', '918765432109'])->delete();

DB::table('conversations')->insert([
    ['tenant_id' => 1, 'customer_number' => '919876543210', 'customer_name' => 'Customer 1', 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ['tenant_id' => 2, 'customer_number' => '918765432109', 'customer_name' => 'Customer 2', 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]
]);

$conv1 = Conversation::withoutGlobalScopes()->where('customer_number', '919876543210')->first();
$conv2 = Conversation::withoutGlobalScopes()->where('customer_number', '918765432109')->first();

// 4. Force 24-hr session cache so ChatController doesn't block free text
\Illuminate\Support\Facades\Cache::put('session:' . $conv1->customer_number, true, now()->addHours(24));
\Illuminate\Support\Facades\Cache::put('session:' . $conv2->customer_number, true, now()->addHours(24));

// We'll mock the Job dispatching so we can inspect the payload
\Illuminate\Support\Facades\Queue::fake();

$controller = new ChatController();

// 5. Send message as Tenant 1
echo "\n[Sending Message via Tenant 1 (Expected Number: 917425889008)]\n";
app(\App\Services\TenantResolverService::class)->setActiveTenantId(1);
$request1 = Request::create('/api/conversations/' . $conv1->id . '/messages', 'POST', [
    'type' => 'text',
    'content' => 'Hello from Tenant 1'
]);
$controller->store($request1, $conv1->id);

\Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendMsg91Message::class, function ($job) {
    $reflection = new ReflectionClass($job);
    $payloadProp = $reflection->getProperty('msg91Payload');
    $payloadProp->setAccessible(true);
    $payload = $payloadProp->getValue($job);
    if ($payload['integrated_number'] === '917425889008') {
        echo "Tenant 1 dispatched job with integrated_number: " . $payload['integrated_number'] . "\n";
        return true;
    }
    return false;
});

// 6. Send message as Tenant 2
echo "\n[Sending Message via Tenant 2 (Expected Number: 12025550123)]\n";
app(\App\Services\TenantResolverService::class)->setActiveTenantId(2);
$request2 = Request::create('/api/conversations/' . $conv2->id . '/messages', 'POST', [
    'type' => 'text',
    'content' => 'Hello from Tenant 2'
]);
$controller->store($request2, $conv2->id);

\Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendMsg91Message::class, function ($job) {
    $reflection = new ReflectionClass($job);
    $payloadProp = $reflection->getProperty('msg91Payload');
    $payloadProp->setAccessible(true);
    $payload = $payloadProp->getValue($job);
    if ($payload['integrated_number'] === '12025550123') {
        echo "Tenant 2 dispatched job with integrated_number: " . $payload['integrated_number'] . "\n";
        return true;
    }
    return false;
});

echo "\n--- VERIFICATION PASSED ---\n";
