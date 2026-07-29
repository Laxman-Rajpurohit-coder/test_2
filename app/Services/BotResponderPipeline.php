<?php

namespace App\Services;

use App\Contracts\BotResponderInterface;
use App\DTOs\InboundMessageContext;
use Illuminate\Support\Facades\Log;

class BotResponderPipeline
{
    /** @var array<int, array{responder: BotResponderInterface, priority: int}> */
    protected array $responders = [];

    /**
     * Register a responder implementation with explicit priority (Lower number = Higher precedence).
     * Priority 10: FlowBuilder Active Session & Triggers
     * Priority 50: Core Keyword Bot Triggers
     * Priority 90: AI Bot Fallback & Escalation
     */
    public function register(BotResponderInterface $responder, int $priority = 50): void
    {
        $this->responders[] = [
            'responder' => $responder,
            'priority'  => $priority,
        ];

        // Sort responders by priority ascending
        usort($this->responders, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Execute ordered chain of responsibility.
     * Stops immediately at the first responder that returns true.
     * 
     * NOTE: Exceptions are NOT caught here. Any exception in a responder propagates directly
     * up to ProcessBotTriggerJob::handle()'s try/catch block so that the Redis idempotency
     * lock is cleanly released (Cache::forget) and the queue job retries.
     */
    public function process(InboundMessageContext $context): bool
    {
        foreach ($this->responders as $entry) {
            $responder = $entry['responder'];
            
            if ($responder->attemptHandle($context)) {
                Log::info("BotResponderPipeline: Message {$context->messageId} (Tenant {$context->tenantId}) handled by " . get_class($responder) . " [Priority {$entry['priority']}]");
                return true; // Handled! Stop pipeline execution immediately.
            }
        }

        // Unhandled case: Explicitly logged with full context visibility
        Log::info("BotResponderPipeline: Message {$context->messageId} (Tenant {$context->tenantId}, Customer {$context->customerNumber}) was unhandled by all registered responders. No auto-reply generated.");
        return false;
    }
}
