<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// Use first tenant or tenant_id = 7
$tenant = Tenant::first();
if (!$tenant) {
    echo "No tenant found on local database.\n";
    exit(1);
}

$tenantId = $tenant->id;
echo "Seeding 1,000 test contacts & conversations for Tenant ID: {$tenantId}...\n";

// Set active tenant in service
app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenantId);

DB::beginTransaction();

try {
    // 1. Create or find Contact Group
    $group = ContactGroup::firstOrCreate(
        ['tenant_id' => $tenantId, 'name' => '1000 Test Campaign Group']
    );

    $now = now();
    $contactsData = [];
    $messagesData = [];
    $pivotData = [];

    $startPhone = 9800000001;

    for ($i = 0; $i < 1000; $i++) {
        $contactUuid = (string) Str::uuid();
        $phone = '+' . ($startPhone + $i);
        $name = "Test User " . ($i + 1);

        // Contact insert
        $contactsData[] = [
            'id' => $contactUuid,
            'tenant_id' => $tenantId,
            'phone_number' => $phone,
            'name' => $name,
            'email' => "testuser" . ($i + 1) . "@example.com",
            'is_subscribed' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Group pivot
        $pivotData[] = [
            'contact_id' => $contactUuid,
            'contact_group_id' => $group->id,
        ];
    }

    // Insert contacts in chunks of 250
    foreach (array_chunk($contactsData, 250) as $chunk) {
        DB::table('contacts')->insert($chunk);
    }

    // Insert group pivot
    foreach (array_chunk($pivotData, 250) as $chunk) {
        DB::table('contact_group_contact')->insert($chunk);
    }

    echo "1,000 Contacts created and added to '1000 Test Campaign Group'.\n";

    // 2. Create Conversations & Initial Messages for Inbox
    for ($i = 0; $i < 1000; $i++) {
        $phone = '+' . ($startPhone + $i);
        $name = "Test User " . ($i + 1);
        $messageUuid = (string) Str::uuid();

        $convId = DB::table('conversations')->insertGetId([
            'tenant_id' => $tenantId,
            'customer_number' => $phone,
            'customer_name' => $name,
            'last_message_at' => $now->subSeconds(1000 - $i),
            'unread_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $messagesData[] = [
            'id' => $messageUuid,
            'tenant_id' => $tenantId,
            'conversation_id' => $convId,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'delivered',
            'content' => json_encode(['text' => 'Campaign Test Message #' . ($i + 1) . ' to ' . $name, 'type' => 'text']),
            'vendor_timestamp' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    // Insert messages in chunks of 250
    foreach (array_chunk($messagesData, 250) as $chunk) {
        DB::table('messages')->insert($chunk);
    }

    DB::commit();

    echo "SUCCESS: 1,000 Contacts, Conversations, and Messages seeded into LOCAL database.\n";
    echo "You can now open http://localhost:8000/chat or http://localhost:8000/contacts to view all 1,000 chats!\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR SEEDING DATA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
