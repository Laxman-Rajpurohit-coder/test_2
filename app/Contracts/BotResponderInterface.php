<?php

namespace App\Contracts;

use App\DTOs\InboundMessageContext;

interface BotResponderInterface
{
    /**
     * Attempt to handle an incoming customer message.
     * Returns true if handled (stops pipeline execution), or false if unhandled (cascades to next responder).
     */
    public function attemptHandle(InboundMessageContext $context): bool;
}
