<?php

namespace Tests\Feature;

use App\DTOs\InboundMessageContext;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantNumber;
use App\Models\TenantSetting;
use App\Models\WhatsappMessage;
use App\Services\BotResponderPipeline;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\AiBot\Responders\AiBotResponder;
use Modules\AiBot\Services\AiBotService;
use Tests\TestCase;

class AiBotPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_bot_responder_handles_unmatched_message_and_queues_reply()
    {
        // 1. Setup Tenant with bot_auto_responder feature and bind it
        $tenant = Tenant::create([
            'name' => 'AI Test Tenant',
            'slug' => 'ai-test',
            'features' => ['bot_auto_responder' => true],
        ]);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        // 2. Configure AI Bot Settings via TenantSetting (the real model used by AiBotResponder/AiBotService)
        TenantSetting::create([
            'tenant_id' => $tenant->id,
            'ai_provider' => 'openai',
            'openai_api_key' => 'fake-api-key',
            'ai_model' => 'gpt-4o-mini',
            'ai_system_prompt' => 'You are a test bot',
            'ai_is_active' => true,
            'ai_human_escalation_enabled' => true,
            'ai_confidence_threshold' => 0.70,
        ]);

        // 3. Create a tenant number (required by OutboundReplyService via TenantResolverService::getIntegratedNumber)
        TenantNumber::create([
            'tenant_id' => $tenant->id,
            'integrated_number' => '919999999999',
        ]);

        // 4. Create a conversation
        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_number' => '1234567890',
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        // 5. Mock the AiBotService to prevent real LLM calls
        $mockAiService = \Mockery::mock(AiBotService::class);
        $mockAiService->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Hello from Mocked AI!');

        $this->app->instance(AiBotService::class, $mockAiService);

        // 6. Re-resolve pipeline (since we swapped the dependency)
        $pipeline = new BotResponderPipeline();
        $responder = $this->app->make(AiBotResponder::class);
        $pipeline->register($responder, $responder->priority());

        // Fake the queue so we don't actually dispatch jobs
        Queue::fake();

        // 7. Simulate an inbound message
        $context = new InboundMessageContext(
            messageId: 'msg_123',
            conversationId: $conversation->id,
            customerNumber: '1234567890',
            messageText: 'Hello AI, how are you?',
            customerName: null,
            tenantId: $tenant->id
        );

        $handled = $pipeline->process($context);

        // 8. Assertions
        $this->assertTrue($handled, "Pipeline should have been handled by AiBotResponder.");

        $outboundMessage = WhatsappMessage::where('direction', 'outbound')->first();
        $this->assertNotNull($outboundMessage, "An outbound WhatsApp message should be created.");

        $content = json_decode($outboundMessage->content, true);
        $this->assertEquals('Hello from Mocked AI!', $content['text']);

        Queue::assertPushed(\App\Jobs\SendMsg91Message::class);
    }
}
