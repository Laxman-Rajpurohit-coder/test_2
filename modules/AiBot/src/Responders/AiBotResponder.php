<?php

namespace Modules\AiBot\Responders;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Events\MessageReceived;
use App\Events\ConversationEscalated;
use App\Jobs\SendMsg91Message;
use App\Models\Conversation;
use App\Models\TenantSetting;
use App\Models\WhatsappMessage;
use App\Services\Msg91PayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AiBot\Services\AiBotService;

class AiBotResponder implements BotResponderInterface
{
    protected AiBotService $aiBotService;

    public function __construct(AiBotService $aiBotService)
    {
        $this->aiBotService = $aiBotService;
    }

    public function attemptHandle(InboundMessageContext $context): bool
    {
        $messageText = trim($context->messageText);
        if (empty($messageText)) {
            return false;
        }

        // Fetch Tenant-Scoped Settings
        $setting = TenantSetting::where('tenant_id', $context->tenantId)->first();
        if (!$setting || !$setting->ai_is_active) {
            return false;
        }

        $conversation = Conversation::find($context->conversationId);
        if (!$conversation) {
            return false;
        }

        // 1. Skip AI generation if conversation is already escalated to a human agent
        if ($conversation->is_human_escalated) {
            Log::info("AiBotResponder: Conversation ID {$context->conversationId} is currently human-escalated. Skipping AI responder.");
            return false;
        }

        // 2. Check Human Escalation Keyword Trigger
        if ($setting->ai_human_escalation_enabled && $this->isHumanEscalationRequested($messageText)) {
            $this->escalateToHuman($conversation, $context, "I am connecting you to a human support agent. Please wait a moment.");
            return true; // Successfully handled via escalation
        }

        // 3. Generate Response via LLM Provider
        $aiReplyText = $this->aiBotService->generateResponse($messageText, $setting);
        
        // 4. Handle Low Confidence (Fail-Closed return null) -> Escalate
        if (empty($aiReplyText)) {
            if ($setting->ai_human_escalation_enabled) {
                $this->escalateToHuman($conversation, $context, "I'm not completely sure about that. I am connecting you to a human support agent who can help.");
                return true;
            }
            return false; // Could not generate response and escalation is disabled
        }

        // 5. Dispatch Outbound WhatsApp Reply
        $this->sendWhatsAppReply($context->conversationId, $context->tenantId, $context->customerNumber, $aiReplyText);

        return true;
    }

    /**
     * Escalate conversation to human agent (DB persistence + WebSocket broadcast)
     */
    protected function escalateToHuman(Conversation $conversation, InboundMessageContext $context, string $replyText): void
    {
        Log::info("AiBotResponder: Escalating conversation {$context->conversationId} to human agent.");

        $conversation->update(['is_human_escalated' => true]);
        
        try {
            broadcast(new ConversationEscalated($conversation))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('WebSocket Broadcast Failed for ConversationEscalated: ' . $e->getMessage());
        }

        $this->sendWhatsAppReply($context->conversationId, $context->tenantId, $context->customerNumber, $replyText);
    }

    /**
     * Check if message text requests human agent escalation.
     */
    protected function isHumanEscalationRequested(string $text): bool
    {
        $clean = mb_strtolower($text);
        $triggers = ['agent', 'human', 'support', 'representative', 'real person', 'talk to human'];

        foreach ($triggers as $trigger) {
            if (str_contains($clean, $trigger)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send Outbound WhatsApp Message with Atomic DB::transaction and ->afterCommit() dispatching.
     */
    protected function sendWhatsAppReply(int $conversationId, int $tenantId, string $customerNumber, string $text): void
    {
        DB::transaction(function () use ($conversationId, $tenantId, $customerNumber, $text) {
            $outboundMessageId = Str::uuid()->toString();

            $contentStruct = [
                'type' => 'text',
                'text' => $text,
            ];

            $msg91Payload = Msg91PayloadBuilder::build(
                $customerNumber,
                'text',
                ['text' => $text]
            );

            $outboundMessage = WhatsappMessage::create([
                'id'               => $outboundMessageId,
                'tenant_id'        => $tenantId,
                'conversation_id'  => $conversationId,
                'request_id'       => null,
                'meta_uuid'        => null,
                'direction'        => 'outbound',
                'status'           => 'queued',
                'content'          => json_encode($contentStruct),
                'failure_reason'   => null,
                'vendor_timestamp' => now(),
            ]);

            Conversation::where('id', $conversationId)
                ->update(['last_message_at' => now(), 'updated_at' => now()]);

            try {
                broadcast(new MessageReceived($conversationId, $outboundMessage))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('WebSocket Broadcast Failed in AiBotResponder: ' . $e->getMessage());
            }

            SendMsg91Message::dispatch($outboundMessageId, $msg91Payload, $conversationId)->afterCommit();
        });
    }

    public function priority(): int
    {
        return 90; // Lowest priority in pipeline
    }
}
