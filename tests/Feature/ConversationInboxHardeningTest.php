<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationInboxHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_conversations_returns_cursor_paginated_lightweight_dtos()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $conv = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Laxman Test',
            'customer_number' => '919876543210',
            'channel' => 'whatsapp',
            'unread_count' => 2,
            'is_favorite' => true,
            'last_message_at' => now(),
        ]);

        Message::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'status' => 'delivered',
            'content' => json_encode(['type' => 'text', 'text' => 'Hello Laxman']),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'customer_name',
                    'customer_number',
                    'channel',
                    'unread_count',
                    'is_favorite',
                    'last_message_at',
                    'preview',
                    'last_message_direction',
                    'last_message_status',
                ]
            ],
            'next_cursor',
            'prev_cursor',
            'has_more',
        ]);

        $first = $response->json('data.0');
        $this->assertEquals($conv->id, $first['id']);
        $this->assertEquals('Laxman Test', $first['customer_name']);
        $this->assertTrue($first['is_favorite']);
        $this->assertEquals('Hello Laxman', $first['preview']);
        $this->assertEquals('outbound', $first['last_message_direction']);
    }

    public function test_cursor_pagination_fetches_next_page_without_duplicates()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        // Create 60 conversations with decreasing timestamps
        for ($i = 1; $i <= 60; $i++) {
            Conversation::create([
                'tenant_id' => $tenant->id,
                'customer_name' => "Customer {$i}",
                'customer_number' => "91900000" . sprintf("%04d", $i),
                'channel' => 'whatsapp',
                'unread_count' => 0,
                'last_message_at' => now()->subMinutes(60 - $i),
            ]);
        }

        // 1. Fetch First Batch (default limit = 40)
        $res1 = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp&limit=40');
        $res1->assertStatus(200);
        $page1Data = $res1->json('data');
        $this->assertCount(40, $page1Data);
        $this->assertTrue($res1->json('has_more'));
        $nextCursor = $res1->json('next_cursor');
        $this->assertNotNull($nextCursor);

        // 2. Fetch Second Batch using cursor
        $res2 = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp&limit=40&cursor=' . $nextCursor);
        $res2->assertStatus(200);
        $page2Data = $res2->json('data');
        $this->assertCount(20, $page2Data);
        $this->assertFalse($res2->json('has_more'));

        // 3. Assert Zero Duplicate IDs between Batch 1 and Batch 2
        $page1Ids = collect($page1Data)->pluck('id')->all();
        $page2Ids = collect($page2Data)->pluck('id')->all();
        $intersection = array_intersect($page1Ids, $page2Ids);
        $this->assertEmpty($intersection);
    }

    public function test_server_side_search_filters_conversations()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'John SearchTarget',
            'customer_number' => '919111111111',
            'channel' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Alice Normal',
            'customer_number' => '919222222222',
            'channel' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        // Search by name
        $response = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp&search=SearchTarget');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('John SearchTarget', $data[0]['customer_name']);

        // Search by phone
        $responsePhone = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp&search=922222');
        $responsePhone->assertStatus(200);
        $dataPhone = $responsePhone->json('data');
        $this->assertCount(1, $dataPhone);
        $this->assertEquals('Alice Normal', $dataPhone[0]['customer_name']);
    }

    public function test_favorite_toggle_and_filtering()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $conv1 = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Favorite Contact',
            'customer_number' => '919333333333',
            'channel' => 'whatsapp',
            'is_favorite' => false,
            'last_message_at' => now(),
        ]);

        $conv2 = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Regular Contact',
            'customer_number' => '919444444444',
            'channel' => 'whatsapp',
            'is_favorite' => false,
            'last_message_at' => now(),
        ]);

        // 1. Toggle Favorite
        $toggleRes = $this->actingAs($user)->postJson("/api/conversations/{$conv1->id}/favorite");
        $toggleRes->assertStatus(200);
        $this->assertTrue($toggleRes->json('is_favorite'));

        $this->assertDatabaseHas('conversations', [
            'id' => $conv1->id,
            'is_favorite' => true,
        ]);

        // 2. Filter by Favorite Only
        $filterRes = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp&favorite_only=1');
        $filterRes->assertStatus(200);
        $favData = $filterRes->json('data');
        $this->assertCount(1, $favData);
        $this->assertEquals($conv1->id, $favData[0]['id']);

        // 3. Toggle back to un-favorite
        $toggleBackRes = $this->actingAs($user)->postJson("/api/conversations/{$conv1->id}/favorite");
        $toggleBackRes->assertStatus(200);
        $this->assertFalse($toggleBackRes->json('is_favorite'));
    }

    public function test_unread_filter_on_api()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $convUnread = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Unread Chat',
            'customer_number' => '919555555555',
            'channel' => 'whatsapp',
            'unread_count' => 3,
            'last_message_at' => now(),
        ]);

        $convRead = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Read Chat',
            'customer_number' => '919666666666',
            'channel' => 'whatsapp',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp&unread_only=1');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($convUnread->id, $data[0]['id']);
    }

    public function test_tenant_isolation_on_paginated_inbox()
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenantA->id);

        Conversation::create([
            'tenant_id' => $tenantA->id,
            'customer_name' => 'Tenant A Contact',
            'customer_number' => '919777777777',
            'channel' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        Conversation::create([
            'tenant_id' => $tenantB->id,
            'customer_name' => 'Tenant B Secret Contact',
            'customer_number' => '919888888888',
            'channel' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($userA)->getJson('/api/conversations?channel=whatsapp');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Tenant A Contact', $data[0]['customer_name']);
    }

    public function test_malformed_message_json_handled_safely_in_preview()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $conv = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Corrupt Message Contact',
            'customer_number' => '919999999999',
            'channel' => 'whatsapp',
            'last_message_at' => now(),
        ]);

        Message::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'status' => 'received',
            'content' => '{"type": "text", "text": "Unclosed string...', // Malformed JSON
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/conversations?channel=whatsapp');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNotEmpty($data[0]['preview']);
    }
}
