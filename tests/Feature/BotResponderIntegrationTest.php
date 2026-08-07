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
}
