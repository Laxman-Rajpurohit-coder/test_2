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

        // Default safety-net interactive fallback menu
        $defaultButtons = [
            ['type' => 'reply', 'reply' => ['id' => 'talk_to_agent', 'title' => 'Talk to Agent']],
            ['type' => 'reply', 'reply' => ['id' => 'check_balance', 'title' => 'Check Balance']],
            ['type' => 'reply', 'reply' => ['id' => 'support', 'title' => 'Support / Help']],
        ];

        $defaultText = "नमस्ते {$resolvedName}! हमें आपका संदेश प्राप्त हुआ। कृपया नीचे दिए गए विकल्पों में से चुनें या अपना प्रश्न टाइप करें:";

        $data = [
            'interactive_type' => 'button',
            'text'             => $defaultText,
            'buttons'          => $defaultButtons,
        ];

        $msg91Payload = Msg91PayloadBuilder::build(
            $context->customerNumber,
            'interactive',
            $data,
            $integratedNumber
        );

        $contentStruct = [
            'type'    => 'interactive',
            'text'    => $defaultText,
            'buttons' => $defaultButtons,
        ];

        OutboundReplyService::send(
            $context->conversationId,
            $context->tenantId,
            $contentStruct,
            $msg91Payload
        );

        Log::info("FallbackInteractiveResponder: Sent default fallback interactive menu in conversation {$context->conversationId}.");
        return true;
    }
}
