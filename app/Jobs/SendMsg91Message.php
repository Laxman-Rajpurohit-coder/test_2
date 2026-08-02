<?php

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Services\TenantResolverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMsg91Message implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [5, 15, 30];

    protected $messageId;
    protected $msg91Payload;
    protected $conversationId;

    public function __construct(string $messageId, array $msg91Payload, int $conversationId)
    {
        $this->messageId = $messageId;
        $this->msg91Payload = $msg91Payload;
        $this->conversationId = $conversationId;
    }

    public function handle(): void
    {
        // 1. Fail-closed check: Ensure conversation and tenant_id exist
        $tenantId = DB::table('conversations')->where('id', $this->conversationId)->value('tenant_id');

        if (!$tenantId) {
            Log::error("SendMsg91Message: Conversation ID {$this->conversationId} not found or missing tenant_id. Aborting dispatch.");
            DB::table('whatsapp_messages')
                ->where('id', $this->messageId)
                ->update(['status' => 'failed', 'failure_reason' => 'Conversation or tenant_id not found']);
            return;
        }

        // 2. Bind active tenant ID to TenantResolverService for worker context
        app(TenantResolverService::class)->setActiveTenantId((int) $tenantId);

        // 3. Resolve dynamic MSG91 auth key for target tenant
        $authKey = app(TenantResolverService::class)->getMsg91AuthKey((int) $tenantId);

        if (!$authKey) {
            Log::error("SendMsg91Message: No MSG91 Auth Key configured for Tenant ID {$tenantId}. Failing outbound send.");
            DB::table('whatsapp_messages')
                ->where('id', $this->messageId)
                ->update(['status' => 'failed', 'failure_reason' => 'MSG91 Auth Key not configured for tenant']);
            return;
        }

        $endpoint = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/';
        // MSG91 strictly requires the /bulk/ endpoint for template messages, but forbids it for standard text/media.
        if (isset($this->msg91Payload['type']) && $this->msg91Payload['type'] === 'template') {
            $endpoint = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';
        }

        $response = Http::withHeaders([
            'authkey'      => $authKey,
            'Content-Type' => 'application/json'
        ])->post($endpoint, $this->msg91Payload);

        if ($response->serverError()) {
            // Transient 5xx server error: throw exception to trigger Queue retry ($tries = 3)
            throw new \Exception("MSG91 Server Error ({$response->status()}): " . $response->body());
        }

        // SQL Rank Case Expression: received=0, queued=1, failed=1.5, sent=2, delivered=3, read=4
        $rankSql = "CASE status WHEN 'received' THEN 0 WHEN 'queued' THEN 1 WHEN 'failed' THEN 1.5 WHEN 'sent' THEN 2 WHEN 'delivered' THEN 3 WHEN 'read' THEN 4 ELSE 0 END";

        if ($response->clientError()) {
            // Permanent 4xx client error (bad number, bad auth key): Atomic SQL update only if rank < 1.5
            DB::table('whatsapp_messages')
                ->where('id', $this->messageId)
                ->whereRaw("{$rankSql} < 1.5")
                ->update([
                    'status'         => 'failed',
                    'failure_reason' => $response->body(),
                    'updated_at'     => now(),
                ]);
        } else {
            // 200 OK Success: Capture MSG91 request_id
            $responseData = $response->json() ?? [];
            $messageUuid = $responseData['data']['message_uuid'] ?? $responseData['request_id'] ?? null;

            // Atomic SQL update: set status = 'sent' ONLY if current rank < 2 (prevent stomping delivered/read)
            DB::table('whatsapp_messages')
                ->where('id', $this->messageId)
                ->whereRaw("{$rankSql} < 2")
                ->update([
                    'status'         => 'sent',
                    'failure_reason' => null,
                    'updated_at'     => now(),
                ]);

            if ($messageUuid) {
                DB::table('whatsapp_messages')
                    ->where('id', $this->messageId)
                    ->update(['request_id' => $messageUuid]);
            }
        }

        $updatedMessage = DB::table('whatsapp_messages')->find($this->messageId);
        
        try {
            broadcast(new MessageReceived($this->conversationId, $updatedMessage))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('WebSocket Broadcast Failed (Non-blocking): ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        $tenantId = DB::table('conversations')->where('id', $this->conversationId)->value('tenant_id');
        if ($tenantId !== null) {
            app(TenantResolverService::class)->setActiveTenantId((int) $tenantId);
        }

        $rankSql = "CASE status WHEN 'received' THEN 0 WHEN 'queued' THEN 1 WHEN 'failed' THEN 1.5 WHEN 'sent' THEN 2 WHEN 'delivered' THEN 3 WHEN 'read' THEN 4 ELSE 0 END";

        DB::table('whatsapp_messages')
            ->where('id', $this->messageId)
            ->whereRaw("{$rankSql} < 1.5")
            ->update([
                'status'         => 'failed',
                'failure_reason' => $exception->getMessage(),
                'updated_at'     => now(),
            ]);

        $updatedMessage = DB::table('whatsapp_messages')->find($this->messageId);

        try {
            broadcast(new MessageReceived($this->conversationId, $updatedMessage))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('WebSocket Broadcast Failed (Non-blocking): ' . $e->getMessage());
        }
    }
}
