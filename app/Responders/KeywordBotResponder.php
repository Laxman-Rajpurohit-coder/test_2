<?php

namespace App\Responders;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Services\BotTriggerService;

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

        // Prioritize button payload over user-typed text if a non-empty payload exists. Normalize by trimming.
        $trimmedPayload = !empty($context->buttonPayload) ? trim($context->buttonPayload) : '';
        $textToMatch = !empty($trimmedPayload) ? $trimmedPayload : trim($context->messageText);

        if (empty($textToMatch)) {
            return false;
        }

        // Evaluate keyword triggers against incoming message text or payload
        $response = $this->botService->matchAndBuildResponse(
            $textToMatch,
            $context->customerNumber,
            $integratedNumber,
            $context->customerName
        );

        if (!$response || empty($response['msg91_payload'])) {
            return false; // Keyword not matched, pass to next responder in chain
        }

        // Sanity Check: If response is text but text is empty, abort dispatch
        $respType = $response['content_struct']['type'] ?? '';
        if ($respType === 'text' && empty(trim($response['content_struct']['text'] ?? ''))) {
            \Illuminate\Support\Facades\Log::warning("KeywordBotResponder: Trigger matched for '{$textToMatch}' but response text is empty. Skipping dispatch.");
            return false;
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
