<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Jobs\SendMsg91Message;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OutboundReplyService
{
    /**
     * Creates and queues an outbound WhatsApp message while atomically updating its conversation.
     *
     * @param int $conversationId The conversation receiving the message.
     * @param int $tenantId The tenant associated with the conversation.
     * @param array $contentStruct The message content to store.
     * @param array $msg91Payload The payload passed to the Msg91 delivery job.
     * @param int|null $delaySeconds Optional non-blocking dispatch delay for pacing sends.
     * @param string $referenceType Reference type for the billing ledger ('message', 'campaign_recipient').
     * @param string|null $referenceId Specific ID of the reference (e.g. recipient ID).
     * @param string|null $idempotencyKey Custom idempotency key to prevent double charging across retries.
     */
    public static function send(
        int $conversationId,
        int $tenantId,
        array $contentStruct,
        array $msg91Payload,
        ?int $delaySeconds = null,
        string $referenceType = 'message',
        ?string $referenceId = null,
        ?string $idempotencyKey = null
    ): string {
        $type = $contentStruct['type'] ?? 'service';
        $category = $contentStruct['category'] ?? ($contentStruct['template_category'] ?? null);

        // Pre-flight balance check
        $canSend = MessageBillingService::canSend($tenantId, $type, $category);
        if (!$canSend['allowed']) {
            throw new \RuntimeException($canSend['reason'] ?? "Tenant {$tenantId} wallet balance is insufficient.");
        }

        return DB::transaction(function () use ($conversationId, $tenantId, $contentStruct, $msg91Payload, $delaySeconds, $type, $category, $referenceType, $referenceId, $idempotencyKey) {
            $outboundMessageId = Str::uuid()->toString();

            $outboundMessage = Message::create([
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

            // Single layer idempotent charge
            $chargeRefId = $referenceId ?: $outboundMessageId;
            $chargeKey = $idempotencyKey ?: "msg_{$outboundMessageId}_charge";

            MessageBillingService::chargeForMessage(
                $tenantId,
                $type,
                $category,
                $chargeRefId,
                $referenceType,
                $chargeKey,
                ['conversation_id' => $conversationId]
            );

            Conversation::where('id', $conversationId)
                ->update(['last_message_at' => now(), 'updated_at' => now()]);

            try {
                broadcast(new MessageReceived($conversationId, $outboundMessage))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('WebSocket Broadcast Failed in OutboundReplyService: ' . $e->getMessage());
            }

            $pendingDispatch = SendMsg91Message::dispatch($outboundMessageId, $msg91Payload, $conversationId)->afterCommit();

            if ($delaySeconds !== null && $delaySeconds > 0) {
                $pendingDispatch->delay(now()->addSeconds($delaySeconds));
            }

            return $outboundMessageId;
        });
    }
}
