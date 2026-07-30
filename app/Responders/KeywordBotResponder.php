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

    /**
     * Attempts to handle an inbound message using configured keyword triggers.
     *
     * @param InboundMessageContext $context The inbound message and conversation details.
     * @return bool `true` if a keyword response was sent, `false` if no keyword matched.
     */
    public function attemptHandle(InboundMessageContext $context): bool
    {
        $integratedNumber = app(\App\Services\TenantResolverService::class)->getIntegratedNumber($context->tenantId);

        // Evaluate keyword triggers against incoming message text
        $response = $this->botService->matchAndBuildResponse(
            $context->messageText,
            $context->customerNumber,
            $integratedNumber,
            $context->customerName
        );

        if (!$response) {
            return false; // Keyword not matched, pass to next responder in chain
        }

        \App\Services\OutboundReplyService::send(
            $context->conversationId,
            $context->tenantId,
            $response['content_struct'],
            $response['msg91_payload']
        );

        return true; // Handled!
    }
}
