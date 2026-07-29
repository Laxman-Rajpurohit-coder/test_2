<?php

namespace Tests\Feature;

use App\DTOs\InboundMessageContext;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Services\BotResponderPipeline;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\AiBot\Models\AiBotSetting;
use Modules\AiBot\Responders\AiBotResponder;
use Modules\AiBot\Services\AiBotService;
use Tests\TestCase;

class AiBotPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_bot_responder_handles_unmatched_message_and_queues_reply()
    {
        // 1. Setup Tenant and bind it
        $tenant = Tenant::create(['name' => 'AI Test Tenant', 'slug' => 'ai-test']);
        app(TenantResolverService::class)->setActiveTenantId($tenant->id);

        // 2. Configure AI Bot Settings for this tenant
        AiBotSetting::create([
            'tenant_id' => $tenant->id,
            'provider' => 'openai',
            'api_key' => 'fake-api-key',
            'model_or_chatflow_id' => 'gpt-4o-mini',
            'system_prompt' => 'You are a test bot',
            'is_active' => true,
            'human_escalation_enabled' => true,
        ]);

        // 3. Create a conversation
        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'customer_number' => '1234567890',
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        // 4. Mock the AiBotService to prevent real LLM calls
        $mockAiService = \Mockery::mock(AiBotService::class);
        $mockAiService->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Hello from Mocked AI!');
        
        $this->app->instance(AiBotService::class, $mockAiService);

        // 5. Re-resolve pipeline (since we swapped the dependency)
        $pipeline = new BotResponderPipeline();
        $responder = $this->app->make(AiBotResponder::class);
        $pipeline->register($responder, $responder->priority());

        // Fake the queue so we don't actually dispatch jobs
        Queue::fake();

        // 6. Simulate an inbound message
        $context = new InboundMessageContext(
            messageId: 'msg_123',
            conversationId: $conversation->id,
            customerNumber: '1234567890',
            messageText: 'Hello AI, how are you?',
            customerName: null,
            tenantId: $tenant->id
        );

        $handled = $pipeline->process($context);

        // 7. Assertions
        $this->assertTrue($handled, "Pipeline should have been handled by AiBotResponder.");
        
        $outboundMessage = WhatsappMessage::where('direction', 'outbound')->first();
        $this->assertNotNull($outboundMessage, "An outbound WhatsApp message should be created.");
        
        $content = json_decode($outboundMessage->content, true);
        $this->assertEquals('Hello from Mocked AI!', $content['text']);

        // Assert job was pushed to queue (afterCommit won't run normally in RefreshDatabase without DB::commit/transaction wrapper, 
        // but Laravel 11 Queue::fake() captures afterCommit dispatches natively in tests)
        Queue::assertPushed(\App\Jobs\SendMsg91Message::class);
    }
}
