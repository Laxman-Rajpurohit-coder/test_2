<?php

namespace Modules\FlowBuilder\Responders;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Services\BotTriggerService;
use Illuminate\Support\Facades\Log;
use Modules\FlowBuilder\Models\FlowSession;
use Modules\FlowBuilder\Services\FlowExecutionService;

class FlowResponder implements BotResponderInterface
{
    protected FlowExecutionService $flowService;
    protected BotTriggerService $triggerService;

    public function __construct(FlowExecutionService $flowService, BotTriggerService $triggerService)
    {
        $this->flowService = $flowService;
        $this->triggerService = $triggerService;
    }

    public function attemptHandle(InboundMessageContext $context): bool
    {
        // -------------------------------------------------------------------
        // PATH A: Active Session Guard (Check existing active FlowSession)
        // -------------------------------------------------------------------
        $activeSession = FlowSession::where('customer_number', $context->customerNumber)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($activeSession) {
            Log::info("FlowResponder: Advancing active FlowSession {$activeSession->id} for customer {$context->customerNumber}");
            $inputPayload = $context->buttonPayload ?? $context->messageText;
            return $this->flowService->advanceSession($activeSession, $inputPayload);
        }

        // -------------------------------------------------------------------
        // PATH B1: Interactive WhatsApp Button/List Reply Payload
        // -------------------------------------------------------------------
        if (!empty($context->buttonPayload) && str_starts_with($context->buttonPayload, 'flow:')) {
            $flowId = str_replace('flow:', '', $context->buttonPayload);
            Log::info("FlowResponder: WhatsApp Button Reply launched Flow ID {$flowId} for customer {$context->customerNumber}");
            return $this->flowService->startFlow($context->conversationId, $context->customerNumber, $flowId, $context->tenantId);
        }

        // -------------------------------------------------------------------
        // PATH B2: Unified Keyword Trigger (Reusing BotTriggerService matching)
        // -------------------------------------------------------------------
        $matchedTrigger = $this->triggerService->matchTrigger($context->messageText);

        if ($matchedTrigger && $matchedTrigger->response_type === 'flow') {
            $rawPayload = is_string($matchedTrigger->response_payload)
                ? json_decode($matchedTrigger->response_payload, true)
                : (array) $matchedTrigger->response_payload;

            $flowId = $rawPayload['flow_id'] ?? null;

            if ($flowId) {
                Log::info("FlowResponder: Keyword Trigger ID {$matchedTrigger->id} launched Flow ID {$flowId} for customer {$context->customerNumber}");
                return $this->flowService->startFlow($context->conversationId, $context->customerNumber, $flowId, $context->tenantId);
            }
        }

        return false; // Unhandled by FlowResponder, cascade down pipeline to KeywordBotResponder (Priority 50)
    }
}
