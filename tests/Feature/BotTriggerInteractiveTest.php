<?php

namespace Tests\Feature;

use App\Models\BotTrigger;
use App\Models\Tenant;
use App\Models\TenantNumber;
use App\Models\User;
use App\Services\BotTriggerService;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotTriggerInteractiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_interactive_bot_trigger_with_buttons()
    {
        $tenant = Tenant::factory()->create(['features' => ['bot_auto_responder' => true]]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'keyword' => 'hello',
            'match_type' => 'exact',
            'response_type' => 'interactive',
            'response_payload' => [
                'text' => 'Hello there!',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Yes']],
                    ['type' => 'reply', 'reply' => ['id' => 'btn_2', 'title' => 'No']]
                ]
            ],
            'priority' => 10,
            'is_active' => true
        ];

        $response = $this->actingAs($user)->post(route('bot-triggers.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $trigger = BotTrigger::where('tenant_id', $tenant->id)->first();
        $this->assertEquals('interactive', $trigger->response_type);
        $this->assertCount(2, $trigger->response_payload['buttons']);
        $this->assertEquals('Yes', $trigger->response_payload['buttons'][0]['reply']['title']);
    }

    public function test_bot_trigger_service_builds_interactive_payload_correctly()
    {
        $tenant = Tenant::factory()->create();
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        BotTrigger::create([
            'tenant_id' => $tenant->id,
            'keyword' => 'menu',
            'match_type' => 'exact',
            'response_type' => 'interactive',
            'response_payload' => [
                'text' => 'Here is the menu {customer_name}',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Pizza']],
                    ['type' => 'reply', 'reply' => ['id' => 'btn_2', 'title' => 'Burger']]
                ]
            ],
            'priority' => 10,
            'is_active' => true
        ]);

        $service = new BotTriggerService();
        $response = $service->matchAndBuildResponse('menu', '15551234567', '919876543210', 'Alice');

        $this->assertNotNull($response);
        $this->assertEquals('interactive', $response['content_struct']['type']);
        $this->assertEquals('Here is the menu Alice', $response['content_struct']['text']);

        // Verify Msg91 Payload Structure
        $msg91Payload = $response['msg91_payload'];
        $this->assertEquals('interactive', $msg91Payload['content_type']);
        $this->assertEquals('interactive', $msg91Payload['payload']['type']);
        $this->assertEquals('button', $msg91Payload['payload']['interactive']['type']);
        $this->assertEquals('Here is the menu Alice', $msg91Payload['payload']['interactive']['body']['text']);
        
        $buttons = $msg91Payload['payload']['interactive']['action']['buttons'];
        $this->assertCount(2, $buttons);
        $this->assertEquals('Pizza', $buttons[0]['reply']['title']);
        $this->assertEquals('Burger', $buttons[1]['reply']['title']);
    }
}
