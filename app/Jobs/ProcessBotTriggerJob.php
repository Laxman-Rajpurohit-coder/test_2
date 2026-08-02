<?php

namespace App\Jobs;

use App\DTOs\InboundMessageContext;
use App\Services\BotResponderPipeline;
use App\Services\TenantResolverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBotTriggerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    protected string $messageId;
    protected int $conversationId;
    protected string $customerNumber;
    protected string $messageText;
    protected ?string $customerName;
    protected ?string $buttonPayload;

    public function __construct(
        string $messageId,
        int $conversationId,
        string $customerNumber,
        string $messageText,
        ?string $customerName = null,
        ?string $buttonPayload = null
    ) {
        $this->messageId = $messageId;
        $this->conversationId = $conversationId;
        $this->customerNumber = $customerNumber;
        $this->messageText = $messageText;
        $this->customerName = $customerName;
        $this->buttonPayload = $buttonPayload;
    }

    public function handle(BotResponderPipeline $pipeline): void
    {
        // 1. Redis Idempotency Lock: Acquire 5-minute atomic lock on this specific message ID
        $lockKey = "bot_replied:{$this->messageId}";
        $acquired = Cache::add($lockKey, true, 300);

        if (!$acquired) {
            Log::info("ProcessBotTriggerJob: Duplicate webhook redelivery detected for message ID {$this->messageId}. Suppressing bot response.");
            return;
        }

        try {
            // Fail-closed check: Ensure conversation and tenant_id exist
            $tenantId = DB::table('conversations')->where('id', $this->conversationId)->value('tenant_id');

            if (!$tenantId) {
                Log::error("ProcessBotTriggerJob: Conversation ID {$this->conversationId} not found or missing tenant_id. Aborting bot trigger.");
                Cache::forget($lockKey); // Release lock on fail-closed exit
                return;
            }

            // Bind active tenant ID to TenantResolverService for worker context
            app(TenantResolverService::class)->setActiveTenantId((int) $tenantId);

            // Construct Inbound Message DTO Context
            $context = new InboundMessageContext(
                $this->messageId,
                $this->conversationId,
                $this->customerNumber,
                $this->messageText,
                $this->customerName,
                (int) $tenantId,
                $this->buttonPayload
            );

            // 2. Execute Chain of Responsibility Pipeline (Flows -> Keywords -> AI Fallback)
            $handled = $pipeline->process($context);

            if (!$handled) {
                Log::info("ProcessBotTriggerJob: Message {$this->messageId} was unhandled by all responders.");
            }

        } catch (\Throwable $e) {
            // RELEASE LOCK ON EXCEPTION: DB transaction was rolled back. Releasing Redis lock permits clean retry.
            Cache::forget($lockKey);
            Log::error("ProcessBotTriggerJob: Exception encountered during handle for message ID {$this->messageId}. Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessBotTriggerJob Failed for message ID {$this->messageId}: " . $exception->getMessage());
    }
}
