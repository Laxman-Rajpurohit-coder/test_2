<?php

namespace Tests\Feature;

use App\Events\MessageReceived;
use App\Jobs\ProcessMsg91Webhook;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatInboxTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $tenantNumber;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup Tenant and User
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant'
        ]);

        $this->tenantNumber = TenantNumber::create([
            'tenant_id' => $this->tenant->id,
            'phone_number' => '1234567890',
            'integrated_number' => '918888888888'
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    public function test_it_fetches_conversations_with_unread_count_and_message_preview()
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'tenant_number_id' => $this->tenantNumber->id,
            'customer_number' => '9999999999',
            'unread_count' => 3,
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'direction' => 'inbound',
            'status' => 'received',
            'content' => json_encode(['type' => 'text', 'text' => 'Hello preview']),
            'vendor_timestamp' => now()
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/conversations');

        $response->assertStatus(200);
        $response->assertJsonFragment(['customer_number' => '9999999999']);
        $response->assertJsonFragment(['unread_count' => 3]);
        
        // Ensure the latest message preview is loaded
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals('Hello preview', $responseData[0]['preview']);
    }

    public function test_it_marks_conversation_as_read_and_resets_unread_count_to_zero()
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'tenant_number_id' => $this->tenantNumber->id,
            'customer_number' => '9999999999',
            'unread_count' => 5,
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/conversations/{$conversation->id}/read");

        $response->assertStatus(200);
        $this->assertEquals(0, $conversation->fresh()->unread_count);
    }

    public function test_inbound_webhook_increments_unread_count_and_broadcasts_event()
    {
        Event::fake([MessageReceived::class]);

        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'tenant_number_id' => $this->tenantNumber->id,
            'customer_number' => '919999999999',
            'unread_count' => 1, // Started at 1
            'last_message_at' => now(),
        ]);

        $payload = [
            'integratedNumber' => '918888888888',
            'customerNumber' => '919999999999',
            'text' => 'New inbound message',
            'type' => 'message',
            // No explicit direction, should default to inbound (0)
        ];

        $job = new ProcessMsg91Webhook($payload);
        $job->handle();

        // The unread count should be incremented to 2
        $this->assertEquals(2, $conversation->fresh()->unread_count);

        Event::assertDispatched(MessageReceived::class, function ($event) use ($conversation) {
            return $event->conversationId === $conversation->id 
                   && $event->message->direction === 'inbound';
        });
    }

    public function test_outbound_webhook_status_update_does_not_increment_unread_count()
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'tenant_number_id' => $this->tenantNumber->id,
            'customer_number' => '919999999999',
            'unread_count' => 0, // Starts at 0
            'last_message_at' => now(),
        ]);

        $payload = [
            'integratedNumber' => '918888888888',
            'customerNumber' => '919999999999',
            'eventName' => 'delivered',
            'direction' => 1, // OUTBOUND status update
        ];

        $job = new ProcessMsg91Webhook($payload);
        $job->handle();

        // The unread count should remain 0
        $this->assertEquals(0, $conversation->fresh()->unread_count);
    }
}
