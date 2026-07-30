<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Jobs\SendMsg91Message;
use App\Models\Conversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OutboundReplyService
{
    /**
     * Send Outbound WhatsApp Message with Atomic DB::transaction and ->afterCommit() dispatching.
     */
    public static function send(int $conversationId, int $tenantId, array $contentStruct, array $msg91Payload): void
    {
        DB::transaction(function () use ($conversationId, $tenantId, $contentStruct, $msg91Payload) {
            $outboundMessageId = Str::uuid()->toString();

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
                Log::warning('WebSocket Broadcast Failed in OutboundReplyService: ' . $e->getMessage());
            }

            SendMsg91Message::dispatch($outboundMessageId, $msg91Payload, $conversationId)->afterCommit();
        });
    }
}
