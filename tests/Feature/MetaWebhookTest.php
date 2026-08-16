<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected TenantSetting $setting;
    protected string $appSecret = 'test_meta_app_secret_12345';
    protected string $verifyToken = 'test_meta_verify_token_67890';
    protected string $pageId = '100200300400';
    protected string $igAccountId = '200300400500';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Test Tenant Meta',
            'slug'   => 'test-meta',
            'status' => 'active',
        ]);

        $this->setting = TenantSetting::create([
            'tenant_id'                 => $this->tenant->id,
            'facebook_page_id'          => $this->pageId,
            'instagram_account_id'      => $this->igAccountId,
            'meta_app_secret'           => $this->appSecret,
            'meta_webhook_verify_token' => $this->verifyToken,
            'meta_access_token'         => 'EAAB_test_page_access_token',
        ]);
    }

    /**
     * Test 1: GET /webhooks/meta returns hub.challenge when token matches.
     */
    public function test_verify_challenge_responds_with_hub_challenge(): void
    {
        $challenge = 'CHALLENGE_RANDOM_STRING_9988';

        $response = $this->get("/webhooks/meta?hub.mode=subscribe&hub.verify_token={$this->verifyToken}&hub.challenge={$challenge}");

        $response->assertStatus(200);
        $this->assertEquals($challenge, $response->getContent());
    }

    /**
     * Test 2: GET /webhooks/meta returns 403 when verify_token is invalid.
     */
    public function test_verify_challenge_fails_with_wrong_token(): void
    {
        $response = $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=WRONG_TOKEN&hub.challenge=12345');

        $response->assertStatus(403);
    }

    /**
     * Test 3: POST /webhooks/meta rejects missing or invalid HMAC signature.
     */
    public function test_webhook_rejects_missing_or_invalid_hmac_signature(): void
    {
        $payload = json_encode(['object' => 'page', 'entry' => []]);

        // Missing signature
        $this->postJson('/webhooks/meta', json_decode($payload, true))
            ->assertStatus(403);

        // Invalid signature
        $this->withHeaders(['X-Hub-Signature-256' => 'sha256=invalid_hash_signature'])
            ->postJson('/webhooks/meta', json_decode($payload, true))
            ->assertStatus(403);
    }

    /**
     * Test 4: POST /webhooks/meta rejects tampered payload even with valid JSON structure.
     * (Signs payload A, sends payload B which has valid JSON syntax, asserting 403).
     */
    public function test_webhook_rejects_tampered_payload_even_with_valid_json_structure(): void
    {
        $originalPayload = json_encode([
            'object' => 'page',
            'entry'  => [
                [
                    'id'   => $this->pageId,
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender'    => ['id' => '123456789'],
                            'recipient' => ['id' => $this->pageId],
                            'message'   => ['mid' => 'mid_orig_1', 'text' => 'Legitimate text'],
                        ]
                    ]
                ]
            ]
        ]);

        $tamperedPayload = json_encode([
            'object' => 'page',
            'entry'  => [
                [
                    'id'   => $this->pageId,
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender'    => ['id' => '123456789'],
                            'recipient' => ['id' => $this->pageId],
                            'message'   => ['mid' => 'mid_tampered', 'text' => 'ATTACKER_INJECTED_TEXT'],
                        ]
                    ]
                ]
            ]
        ]);

        // Generate signature for the ORIGINAL payload
        $signatureForOriginal = 'sha256=' . hash_hmac('sha256', $originalPayload, $this->appSecret);

        // Send the TAMPERED payload with the original signature
        $response = $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signatureForOriginal,
                'CONTENT_TYPE'             => 'application/json',
            ],
            $tamperedPayload
        );

        $response->assertStatus(403);
    }

    /**
     * Test 5: Inbound Facebook Messenger creates conversation, message, and sets 24h cache session.
     */
    public function test_facebook_inbound_creates_conversation_and_message(): void
    {
        $psid = 'fb_user_998877';
        $messageText = 'Hello from Facebook Messenger!';
        $mid = 'mid_fb_inbound_101';

        $payload = json_encode([
            'object' => 'page',
            'entry'  => [
                [
                    'id'   => $this->pageId,
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender'    => ['id' => $psid],
                            'recipient' => ['id' => $this->pageId],
                            'message'   => ['mid' => $mid, 'text' => $messageText],
                        ]
                    ]
                ]
            ]
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);

        $response = $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'CONTENT_TYPE'             => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        // Assert Conversation created
        $this->assertDatabaseHas('conversations', [
            'tenant_id'    => $this->tenant->id,
            'channel'      => 'facebook',
            'channel_psid' => $psid,
        ]);

        // Assert Message created
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $this->tenant->id,
            'channel'   => 'facebook',
            'direction' => 'inbound',
            'status'    => 'received',
            'meta_uuid' => $mid,
        ]);

        // Assert 24-hour cache session key is set
        $this->assertTrue(Cache::has("meta_session:facebook:{$psid}"));
    }

    /**
     * Test 6: Inbound Instagram Direct creates conversation, message, and sets 24h cache session.
     */
    public function test_instagram_inbound_creates_conversation_and_message(): void
    {
        $igPsid = 'ig_user_445566';
        $messageText = 'Hello from Instagram DM!';
        $mid = 'mid_ig_inbound_202';

        $payload = json_encode([
            'object' => 'instagram',
            'entry'  => [
                [
                    'id'   => $this->igAccountId,
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender'    => ['id' => $igPsid],
                            'recipient' => ['id' => $this->igAccountId],
                            'message'   => ['mid' => $mid, 'text' => $messageText],
                        ]
                    ]
                ]
            ]
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);

        $response = $this->call(
            'POST',
            '/webhooks/meta',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'CONTENT_TYPE'             => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        // Assert Conversation created with instagram channel
        $this->assertDatabaseHas('conversations', [
            'tenant_id'    => $this->tenant->id,
            'channel'      => 'instagram',
            'channel_psid' => $igPsid,
        ]);

        // Assert Message created
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $this->tenant->id,
            'channel'   => 'instagram',
            'direction' => 'inbound',
            'status'    => 'received',
            'meta_uuid' => $mid,
        ]);

        // Assert 24-hour cache session key is set
        $this->assertTrue(Cache::has("meta_session:instagram:{$igPsid}"));
    }

    /**
     * Test 7: Outbound send is blocked (422) when outside 24h window (no cache key).
     */
    public function test_send_blocked_outside_24h_window(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'owner',
        ]);

        $conversation = Conversation::create([
            'tenant_id'       => $this->tenant->id,
            'customer_number' => 'fb_user_closed',
            'channel'         => 'facebook',
            'channel_psid'    => 'fb_user_closed',
        ]);

        // Ensure cache session key is NOT present
        Cache::forget("meta_session:facebook:fb_user_closed");

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/meta-message", [
                'content' => 'Trying to message outside 24h window',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'window_closed' => true,
        ]);
    }

    /**
     * Test 8: Outbound send succeeds when within 24h window (cache key present).
     */
    public function test_send_succeeds_within_24h_window(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'recipient_id' => 'fb_user_open',
                'message_id'   => 'm_mid_graph_success_777',
            ], 200),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'owner',
        ]);

        $conversation = Conversation::create([
            'tenant_id'       => $this->tenant->id,
            'customer_number' => 'fb_user_open',
            'channel'         => 'facebook',
            'channel_psid'    => 'fb_user_open',
        ]);

        // Put active 24h session in Cache
        Cache::put("meta_session:facebook:fb_user_open", true, now()->addHours(24));

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/meta-message", [
                'content' => 'Hello within 24h window!',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'sent']);

        // Assert message recorded in database as sent
        $this->assertDatabaseHas('messages', [
            'tenant_id'       => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'channel'         => 'facebook',
            'direction'       => 'outbound',
            'status'          => 'sent',
            'request_id'      => 'm_mid_graph_success_777',
        ]);
    }

    /**
     * Test 9: Member cannot send to an unassigned conversation (403).
     */
    public function test_member_cannot_send_to_unassigned_conversation(): void
    {
        $memberUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'member',
        ]);

        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'member',
        ]);

        $conversation = Conversation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_number'  => 'fb_psid_assigned_other',
            'channel'          => 'facebook',
            'channel_psid'     => 'fb_psid_assigned_other',
            'assigned_user_id' => $otherUser->id, // Assigned to other user!
        ]);

        Cache::put("meta_session:facebook:fb_psid_assigned_other", true, now()->addHours(24));

        $response = $this->actingAs($memberUser)
            ->postJson("/api/conversations/{$conversation->id}/meta-message", [
                'content' => 'Unauthorized attempt by unassigned member',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 10: Assigned member CAN send to their assigned conversation.
     */
    public function test_assigned_member_can_send_to_assigned_conversation(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'recipient_id' => 'fb_psid_my_assigned',
                'message_id'   => 'm_mid_member_success_888',
            ], 200),
        ]);

        $memberUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'member',
        ]);

        $conversation = Conversation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_number'  => 'fb_psid_my_assigned',
            'channel'          => 'facebook',
            'channel_psid'     => 'fb_psid_my_assigned',
            'assigned_user_id' => $memberUser->id, // Assigned to THIS member!
        ]);

        Cache::put("meta_session:facebook:fb_psid_my_assigned", true, now()->addHours(24));

        $response = $this->actingAs($memberUser)
            ->postJson("/api/conversations/{$conversation->id}/meta-message", [
                'content' => 'Authorized send by assigned member',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'sent']);
    }

    /**
     * Test 11: Owner or Admin can send to ANY conversation regardless of assignment.
     */
    public function test_owner_or_admin_can_send_to_any_conversation(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'recipient_id' => 'fb_psid_owner_send',
                'message_id'   => 'm_mid_owner_success_999',
            ], 200),
        ]);

        $ownerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => 'owner',
        ]);

        $conversation = Conversation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_number'  => 'fb_psid_owner_send',
            'channel'          => 'facebook',
            'channel_psid'     => 'fb_psid_owner_send',
            'assigned_user_id' => null, // Unassigned or assigned to someone else
        ]);

        Cache::put("meta_session:facebook:fb_psid_owner_send", true, now()->addHours(24));

        $response = $this->actingAs($ownerUser)
            ->postJson("/api/conversations/{$conversation->id}/meta-message", [
                'content' => 'Owner sending to conversation',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'sent']);
    }
}
