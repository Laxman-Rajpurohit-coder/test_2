<?php

namespace App\Responders;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Models\BotTrigger;
use App\Services\Msg91PayloadBuilder;
use App\Services\OutboundReplyService;
use App\Services\TenantResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FirstMessageResponder implements BotResponderInterface
{
    /**
     * Handles the very first inbound message from a customer by checking for a 'first_message' trigger.
     */
    public function attemptHandle(InboundMessageContext $context): bool
    {
        // Check prior inbound message count in this conversation
        $priorInboundCount = DB::table('messages')
            ->where('conversation_id', $context->conversationId)
            ->where('direction', 'inbound')
            ->count();

        // If there is more than 1 inbound message, this is not the first message
        if ($priorInboundCount > 1) {
            return false;
        }

        // Look for an active first_message trigger for this tenant
        $trigger = BotTrigger::query()
            ->where('tenant_id', $context->tenantId)
            ->where('trigger_type', 'first_message')
            ->where('is_active', true)
            ->first();

        if (!$trigger) {
            return false; // No welcome trigger configured, let subsequent responders handle it
        }

        $rawPayload = is_string($trigger->response_payload)
            ? json_decode($trigger->response_payload, true)
            : (array) $trigger->response_payload;

        if (empty($rawPayload)) {
            return false;
        }

        $integratedNumber = app(TenantResolverService::class)->getIntegratedNumber($context->tenantId);
        $resolvedName = !empty($context->customerName) ? $context->customerName : 'Customer';

        $substitutions = [
            '{customer_name}' => $resolvedName,
            '{phone_number}'  => $context->customerNumber,
        ];

        $data = [];
        if (!empty($rawPayload['text'])) {
            $data['text'] = strtr($rawPayload['text'], $substitutions);
        }
        if (!empty($rawPayload['url'])) {
            $data['url'] = $rawPayload['url'];
        }
        if (!empty($rawPayload['caption'])) {
            $data['caption'] = strtr($rawPayload['caption'], $substitutions);
        }
        if ($trigger->response_type === 'interactive' && !empty($rawPayload['buttons'])) {
            $data['interactive_type'] = 'button';
            $data['buttons'] = $rawPayload['buttons'];
        }

        $msg91Payload = Msg91PayloadBuilder::build(
            $context->customerNumber,
            $trigger->response_type,
            $data,
            $integratedNumber
        );

        $contentStruct = [
            'type' => $trigger->response_type,
            'text' => $data['text'] ?? ($data['caption'] ?? ''),
        ];
        if (!empty($data['url'])) {
            $contentStruct['url'] = $data['url'];
        }
        if (!empty($rawPayload['buttons'])) {
            $contentStruct['buttons'] = $rawPayload['buttons'];
        }

        OutboundReplyService::send(
            $context->conversationId,
            $context->tenantId,
            $contentStruct,
            $msg91Payload
        );

        Log::info("FirstMessageResponder: Handled first message in conversation {$context->conversationId} for customer {$context->customerNumber}.");
        return true;
    }
}
