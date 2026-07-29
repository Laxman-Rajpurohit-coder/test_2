<?php

namespace App\Responders;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Events\MessageReceived;
use App\Jobs\SendMsg91Message;
use App\Models\Conversation;
use App\Models\WhatsappMessage;
use App\Services\BotTriggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KeywordBotResponder implements BotResponderInterface
{
    protected BotTriggerService $botService;

    public function __construct(BotTriggerService $botService)
    {
        $this->botService = $botService;
    }

    public function attemptHandle(InboundMessageContext $context): bool
    {
        // Evaluate keyword triggers against incoming message text
        $response = $this->botService->matchAndBuildResponse(
            $context->messageText,
            $context->customerNumber,
            $context->customerName
        );

        if (!$response) {
            return false; // Keyword not matched, pass to next responder in chain
        }

        // Atomic DB transaction & dispatch
        DB::transaction(function () use ($response, $context) {
            $outboundMessageId = Str::uuid()->toString();

            // Eloquent insertion (Tenant ID explicitly set + scoped)
            $outboundMessage = WhatsappMessage::create([
                'id'               => $outboundMessageId,
                'tenant_id'        => $context->tenantId,
                'conversation_id'  => $context->conversationId,
                'request_id'       => null,
                'meta_uuid'        => null,
                'direction'        => 'outbound',
                'status'           => 'queued',
                'content'          => json_encode($response['content_struct']),
                'failure_reason'   => null,
                'vendor_timestamp' => now(),
            ]);

            // Eloquent model update for conversation timestamp
            Conversation::where('id', $context->conversationId)
                ->update(['last_message_at' => now(), 'updated_at' => now()]);

            // WebSocket broadcast directly using created Eloquent model instance
            try {
                broadcast(new MessageReceived($context->conversationId, $outboundMessage))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('WebSocket Broadcast Failed in KeywordBotResponder: ' . $e->getMessage());
            }

            // Dispatch MSG91 delivery job AFTER DB transaction commits to Redis queue
            SendMsg91Message::dispatch($outboundMessageId, $response['msg91_payload'], $context->conversationId)->afterCommit();
        });

        return true; // Handled!
    }
}
