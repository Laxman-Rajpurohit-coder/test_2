<?php

namespace App\DTOs;

class InboundMessageContext
{
    public string $messageId;
    public int $conversationId;
    public string $customerNumber;
    public string $messageText;
    public ?string $customerName;
    public int $tenantId;
    public ?string $buttonPayload;

    public function __construct(
        string $messageId,
        int $conversationId,
        string $customerNumber,
        string $messageText,
        ?string $customerName,
        int $tenantId,
        ?string $buttonPayload = null
    ) {
        $this->messageId = $messageId;
        $this->conversationId = $conversationId;
        $this->customerNumber = $customerNumber;
        $this->messageText = $messageText;
        $this->customerName = $customerName;
        $this->tenantId = $tenantId;
        $this->buttonPayload = $buttonPayload;
    }
}
