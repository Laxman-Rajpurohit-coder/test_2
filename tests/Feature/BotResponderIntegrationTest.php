<?php

namespace Tests\Feature;

use App\Jobs\ProcessMsg91Webhook;
use App\Models\BotTrigger;
use App\Models\Tenant;
use App\Models\TenantNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotResponderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_keyword_triggers_interactive_outbound_response()
    {
        $tenant = Tenant::factory()->create(['features' => ['bot_auto_responder' => true]]);
        $tenantNumber = TenantNumber::create([
            'tenant_id' => $tenant->id,
            'integrated_number' => '919876543210',
            'status' => 'active'
        ]);

        \App\Models\TenantSetting::create([
            'tenant_id' => $tenant->id,
            'msg91_auth_key' => 'fake_auth_key',
        ]);

        BotTrigger::create([
            'tenant_id' => $tenant->id,
            'keyword' => 'menu',
            'match_type' => 'exact',
            'response_type' => 'interactive',
            'response_payload' => [
                'text' => 'Choose an option',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Support']],
                    ['type' => 'reply', 'reply' => ['id' => 'btn_2', 'title' => 'Sales']]
                ]
            ],
            'priority' => 10,
            'is_active' => true
        ]);

        // Fake the MSG91 API call
        Http::fake([
            '*/api/v5/whatsapp/whatsapp-outbound-message/bulk/' => Http::response(['message' => 'success', 'msgId' => ['fake-msg-id']], 200),
        ]);

        // Simulate Inbound Webhook payload from MSG91 for the keyword 'menu'
        $payload = [
            'integratedNumber' => '919876543210',
            'customerNumber' => '919999999999',
            'customerName' => 'Test User',
            'text' => 'menu',
            'type' => 'text',
            'direction' => 0,
            'ts' => now()->timestamp,
            'uuid' => 'incoming-uuid-1234',
        ];

        // Execute Webhook Job synchronously to trigger the BotResponderPipeline
        $job = new ProcessMsg91Webhook($payload);
        $job->handle();

        // 1. Assert Outbound HTTP Request was sent to MSG91
        Http::assertSent(function ($request) {
            $data = $request->data();
            
            // It should be a bulk payload wrap
            if (!isset($data['integrated_number']) || $data['integrated_number'] !== '919876543210') {
                return false;
            }

            $payload = $data['payload'];
            return $payload['to'] === '919999999999' &&
                   $payload['type'] === 'interactive' &&
                   $payload['interactive']['type'] === 'button' &&
                   $payload['interactive']['body']['text'] === 'Choose an option' &&
                   count($payload['interactive']['action']['buttons']) === 2 &&
                   $payload['interactive']['action']['buttons'][0]['reply']['title'] === 'Support';
        });

        // 2. Assert Message was saved to DB correctly (content struct threading)
        $this->assertDatabaseHas('whatsapp_messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'outbound',
        ]);
        
        $outboundMsg = \App\Models\WhatsappMessage::where('tenant_id', $tenant->id)->where('direction', 'outbound')->first();
        if (!$outboundMsg) {
            dump("NO OUTBOUND MSG FOUND!");
            dump(\App\Models\WhatsappMessage::all()->toArray());
        }
        $content = json_decode($outboundMsg->content, true);
        
        $this->assertEquals('interactive', $content['type']);
        $this->assertEquals('Choose an option', $content['text']);
    }
    public function test_interactive_button_payload_maps_to_keyword_trigger()
    {
        $tenant = Tenant::factory()->create(['features' => ['bot_auto_responder' => true]]);
        $tenantNumber = TenantNumber::create([
            'tenant_id' => $tenant->id,
            'integrated_number' => '919876543210',
            'status' => 'active'
        ]);

        \App\Models\TenantSetting::create([
            'tenant_id' => $tenant->id,
            'msg91_auth_key' => 'fake_auth_key',
        ]);

        // Create a keyword trigger matching the hidden button payload 'BTN_CMD_SUPPORT'
        BotTrigger::create([
            'tenant_id' => $tenant->id,
            'keyword' => 'BTN_CMD_SUPPORT',
            'match_type' => 'exact',
            'response_type' => 'text',
            'response_payload' => ['text' => 'Support is on the way!'],
            'priority' => 50,
            'is_active' => true
        ]);

        Http::fake([
            '*/api/v5/whatsapp/whatsapp-outbound-message/*' => Http::response(['message' => 'success', 'msgId' => ['fake-msg-id-2']], 200),
        ]);

        // Simulate Inbound Webhook payload from MSG91 for a button click
        $payload = [
            'integratedNumber' => '919876543210',
            'customerNumber' => '919999999999',
            'customerName' => 'Test User',
            // Notice: text is empty/button click name, but payload contains our command
            'text' => 'Support',
            'type' => 'button',
            'button' => [
                'payload' => 'BTN_CMD_SUPPORT',
                'text' => 'Support'
            ],
            'direction' => 0,
            'ts' => now()->timestamp,
            'uuid' => 'incoming-uuid-btn-1234',
        ];

        $job = new ProcessMsg91Webhook($payload);
        $job->handle();

        // 1. Assert Outbound HTTP Request was sent to MSG91 with correct response
        Http::assertSent(function ($request) {
            $data = $request->data();
            if (!isset($data['integrated_number'])) return false;
            return $data['payload']['text']['body'] === 'Support is on the way!';
        });
    }

    public function test_button_payload_takes_precedence_over_conflicting_text()
    {
        $tenant = Tenant::factory()->create(['features' => ['bot_auto_responder' => true]]);
        TenantNumber::create([
            'tenant_id' => $tenant->id,
            'integrated_number' => '919876543210',
            'status' => 'active'
        ]);
        \App\Models\TenantSetting::create([
            'tenant_id' => $tenant->id,
            'msg91_auth_key' => 'fake_auth_key',
        ]);

        // Trigger A: matches on button text 'Support'
        BotTrigger::create([
            'tenant_id' => $tenant->id,
            'keyword' => 'Support',
            'match_type' => 'exact',
            'response_type' => 'text',
            'response_payload' => ['text' => 'Matched Text Trigger A'],
            'priority' => 50,
            'is_active' => true
        ]);

        // Trigger B: matches on hidden payload 'BTN_CMD_SUPPORT'
        BotTrigger::create([
            'tenant_id' => $tenant->id,
            'keyword' => 'BTN_CMD_SUPPORT',
            'match_type' => 'exact',
            'response_type' => 'text',
            'response_payload' => ['text' => 'Matched Payload Trigger B'],
            'priority' => 50,
            'is_active' => true
        ]);

        Http::fake([
            '*/api/v5/whatsapp/whatsapp-outbound-message/bulk/' => Http::response(['message' => 'success'], 200),
        ]);

        // Payload has BOTH conflicting text ('Support') and payload ('BTN_CMD_SUPPORT')
        $payload = [
            'integratedNumber' => '919876543210',
            'customerNumber' => '919999999999',
            'customerName' => 'Test User',
            'text' => 'Support',
            'type' => 'button',
            'button' => [
                'payload' => 'BTN_CMD_SUPPORT',
                'text' => 'Support'
            ],
            'direction' => 0,
            'ts' => now()->timestamp,
            'uuid' => 'incoming-uuid-precedence-1',
        ];

        (new ProcessMsg91Webhook($payload))->handle();

        // Proves Precedence: Trigger B (Payload) MUST win over Trigger A (Text)
        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['payload']['text']['body']) && $data['payload']['text']['body'] === 'Matched Payload Trigger B';
        });
    }

    public function test_whitespace_or_null_button_payload_falls_back_to_text()
    {
        $tenant = Tenant::factory()->create(['features' => ['bot_auto_responder' => true]]);
        TenantNumber::create([
            'tenant_id' => $tenant->id,
            'integrated_number' => '919876543210',
            'status' => 'active'
        ]);
        \App\Models\TenantSetting::create([
            'tenant_id' => $tenant->id,
            'msg91_auth_key' => 'fake_auth_key',
        ]);

        BotTrigger::create([
            'tenant_id' => $tenant->id,
            'keyword' => 'Help',
            'match_type' => 'exact',
            'response_type' => 'text',
            'response_payload' => ['text' => 'Help Desk Response'],
            'priority' => 50,
            'is_active' => true
        ]);

        Http::fake([
            '*/api/v5/whatsapp/whatsapp-outbound-message/*' => Http::response(['message' => 'success'], 200),
        ]);

        // Inbound message with empty/whitespace payload
        $payload = [
            'integratedNumber' => '919876543210',
            'customerNumber' => '919999999999',
            'customerName' => 'Test User',
            'text' => 'Help',
            'type' => 'button',
            'button' => [
                'payload' => '   ',
                'text' => 'Help'
            ],
            'direction' => 0,
            'ts' => now()->timestamp,
            'uuid' => 'incoming-uuid-fallback-1',
        ];

        (new ProcessMsg91Webhook($payload))->handle();

        // Fallback Proof: Empty button payload falls back to user text ('Help')
        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['payload']['text']['body']) && $data['payload']['text']['body'] === 'Help Desk Response';
        });
    }
}

