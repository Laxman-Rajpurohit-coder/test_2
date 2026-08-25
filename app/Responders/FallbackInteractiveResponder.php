<?php

namespace App\Responders;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use App\Models\BotTrigger;
use App\Services\Msg91PayloadBuilder;
use App\Services\OutboundReplyService;
use App\Services\TenantResolverService;
use Illuminate\Support\Facades\Log;

class FallbackInteractiveResponder implements BotResponderInterface
{
    /**
     * Handles unhandled messages by sending a fallback interactive menu.
     */
    public function attemptHandle(InboundMessageContext $context): bool
    {
        $integratedNumber = app(TenantResolverService::class)->getIntegratedNumber($context->tenantId);
        $resolvedName = !empty($context->customerName) ? $context->customerName : 'Customer';

        // Check if tenant has a configured custom fallback trigger
        $trigger = BotTrigger::query()
            ->where('tenant_id', $context->tenantId)
            ->where('trigger_type', 'fallback')
            ->where('is_active', true)
            ->first();

        if ($trigger) {
            $rawPayload = is_string($trigger->response_payload)
                ? json_decode($trigger->response_payload, true)
                : (array) $trigger->response_payload;

            if (!empty($rawPayload)) {
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

                Log::info("FallbackInteractiveResponder: Sent configured fallback trigger response in conversation {$context->conversationId}.");
                return true;
            }
        }

        // If no custom fallback trigger is configured, remain silent and fail gracefully
        Log::info("FallbackInteractiveResponder: No custom fallback trigger set for tenant {$context->tenantId}. Remaining silent.");
        return false;
    }
}
