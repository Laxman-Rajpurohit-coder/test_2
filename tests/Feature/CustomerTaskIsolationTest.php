<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CustomerTask;
use App\Models\Message;
use App\Services\TenantResolverService;
use App\Console\Commands\SendTaskReminderAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerTaskIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_b_cannot_access_or_modify_tenant_a_task()
    {
        // 1. Create two isolated tenants
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'features' => []]);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'features' => []]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        // Set context to Tenant A to create its resources
        app(TenantResolverService::class)->setActiveTenantId($tenantA->id);

        $contactA = Contact::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'phone_number' => '919999999999',
            'name' => 'Contact A',
        ]);

        $conversationA = Conversation::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'tenant_number_id' => 1,
            'customer_number' => '919999999999',
            'last_message_at' => now(),
        ]);

        $taskA = CustomerTask::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'contact_id' => $contactA->id,
            'conversation_id' => $conversationA->id,
            'title' => 'Tenant A Private Task',
            'status' => 'open',
            'due_at' => now()->subMinute(),
        ]);

        $this->assertEquals($tenantA->id, $taskA->tenant_id);

        // 2. Perform actions authenticated as Tenant B
        $this->actingAs($userB);

        echo "\n[Isolation Test] Attempting cross-tenant task updates...\n";

        // Try to update status of Tenant A's task
        $responsePatch = $this->patch('/tasks/' . $taskA->id . '/status', [
            'status' => 'resolved',
        ]);
        echo "PATCH /tasks/" . $taskA->id . "/status -> Status: " . $responsePatch->status() . "\n";
        $responsePatch->assertStatus(404);

        // Try to delete Tenant A's task
        $responseDelete = $this->delete('/tasks/' . $taskA->id);
        echo "DELETE /tasks/" . $taskA->id . " -> Status: " . $responseDelete->status() . "\n";
        $responseDelete->assertStatus(404);

        echo "[Isolation Test] Cross-tenant security validated. Returned 404 successfully.\n";
    }

    public function test_reminder_scheduler_does_not_dispatch_outbound_jobs()
    {
        // Prevent actual background job execution queues
        Queue::fake();

        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'features' => []]);
        app(TenantResolverService::class)->setActiveTenantId($tenantA->id);

        $conversation = Conversation::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'tenant_number_id' => 1,
            'customer_number' => '919999999999',
            'last_message_at' => now(),
        ]);

        // Create due task
        $task = CustomerTask::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantA->id,
            'conversation_id' => $conversation->id,
            'title' => 'Important Due Reminder',
            'status' => 'open',
            'due_at' => now()->subMinutes(5),
        ]);

        // Execute scheduled reminder command
        echo "\n[Reminder Test] Running tasks:send-reminders command...\n";
        $this->artisan('tasks:send-reminders');

        // Assert reminder is created inside local database
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'is_internal' => true,
        ]);

        // Assert task is marked as reminder sent
        $task->refresh();
        $this->assertNotNull($task->reminder_sent_at);

        // Assert NO SendMsg91Message queue jobs were dispatched!
        Queue::assertNothingPushed();
        echo "[Reminder Test] Verified local database write bypasses outbound dispatcher completely.\n";
    }

    public function test_auto_message_scheduler_dispatches_outbound_job()
    {
        // Fake outbound queue system
        Queue::fake();

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'features' => []]);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $conversation = Conversation::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'tenant_number_id' => 1,
            'customer_number' => '919999999999',
            'last_message_at' => now(),
        ]);

        // Seed integrated number for tenant
        \App\Models\TenantNumber::create([
            'tenant_id' => $tenant->id,
            'integrated_number' => '919999999999',
            'integrated_number_id' => '123',
            'whatsapp_business_account_id' => 'abc',
            'pin' => '123456',
        ]);

        // Create due task of type auto_message
        $task = CustomerTask::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'title' => 'Scheduled Auto-Msg Action',
            'status' => 'open',
            'due_at' => now()->subMinutes(5),
            'type' => 'auto_message',
            'template_name' => 'marketing_promo',
            'template_language' => 'en',
            'template_components' => []
        ]);

        echo "\n[Auto Message Test] Running tasks:send-reminders command...\n";
        $this->artisan('tasks:send-reminders');

        // Confirm database has a row matching the queued outbound template message
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'is_internal' => false,
            'status' => 'queued',
        ]);

        // Verify the outbound send job WAS pushed to the queue!
        Queue::assertPushed(\App\Jobs\SendMsg91Message::class);
        echo "[Auto Message Test] Verified scheduled outbound job was successfully pushed.\n";
    }

    public function test_bulk_delete_contacts_deletes_specified_tenant_contacts()
    {
        $tenant = Tenant::create(['name' => 'Tenant Delete', 'slug' => 'tenant-delete', 'features' => ['contacts_bulk_messaging' => true]]);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $user = \App\Models\User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner'
        ]);

        $contact1 = Contact::create([
            'tenant_id' => $tenant->id,
            'phone_number' => '918888888881',
            'name' => 'Contact 1',
        ]);

        $contact2 = Contact::create([
            'tenant_id' => $tenant->id,
            'phone_number' => '918888888882',
            'name' => 'Contact 2',
        ]);

        $this->actingAs($user);

        echo "\n[Bulk Delete Contacts Test] Posting bulk-delete requests...\n";
        $response = $this->post(route('contacts.bulk-delete'), [
            'contact_ids' => [$contact1->id, $contact2->id]
        ]);

        $response->assertStatus(302);
        
        $this->assertDatabaseMissing('contacts', ['id' => $contact1->id]);
        $this->assertDatabaseMissing('contacts', ['id' => $contact2->id]);
        echo "[Bulk Delete Contacts Test] Successfully deleted bulk contacts.\n";
    }

    public function test_bulk_delete_conversations_cleans_associated_child_messages_and_tasks()
    {
        $tenant = Tenant::create(['name' => 'Tenant Chat Delete', 'slug' => 'tenant-chat-delete']);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        $user = \App\Models\User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner'
        ]);

        $conversation = Conversation::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'tenant_number_id' => 1,
            'customer_number' => '917777777777',
            'last_message_at' => now(),
        ]);

        // Add child message
        $message = \App\Models\Message::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'content' => json_encode(['type' => 'text', 'text' => 'Hello World']),
            'status' => 'sent'
        ]);

        // Add child task
        $task = CustomerTask::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'title' => 'Assoc Task',
            'status' => 'open',
            'due_at' => now()
        ]);

        $this->actingAs($user);

        echo "\n[Bulk Delete Conversations Test] Requesting bulk conversation deletion...\n";
        $response = $this->post(route('api.conversations.bulk-delete'), [
            'conversation_ids' => [$conversation->id]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('customer_tasks', ['id' => $task->id]);
        echo "[Bulk Delete Conversations Test] Conversations, child messages, and tasks resolved and cleaned up.\n";
    }
}
