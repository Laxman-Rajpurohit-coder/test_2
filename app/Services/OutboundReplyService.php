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
     * @param int|null $delaySeconds Optional non-blocking dispatch delay, used by callers
     *   (e.g. SendCampaignJob) that need to pace outbound sends without blocking the
     *   dispatching worker with sleep(). The delay is applied to the queued job, not
     *   to this method's execution — this method still returns immediately.
     */
    public static function send(int $conversationId, int $tenantId, array $contentStruct, array $msg91Payload, ?int $delaySeconds = null): string
    {
        if (!TenantBalanceService::hasBalance($tenantId)) {
            throw new \RuntimeException("Tenant {$tenantId} has depleted balance and is suspended.");
        }

        return DB::transaction(function () use ($conversationId, $tenantId, $contentStruct, $msg91Payload, $delaySeconds) {
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

            $type = $contentStruct['type'] ?? 'service';
            $category = $contentStruct['category'] ?? ($contentStruct['template_category'] ?? null);
            TenantBalanceService::deductForMessage($tenantId, $type, $category, $outboundMessageId);

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
