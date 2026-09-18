<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantBalanceTransaction;

class TenantBalanceService
{
    /**
     * Calculates the message cost for a given message type/category and tenant.
     */
    public static function calculateMessageCost(string $type = 'service', ?string $category = null, ?Tenant $tenant = null): float
    {
        $costStr = MessageBillingService::calculateCost($type, $category, $tenant ?? 0);
        return (float) $costStr;
    }

    /**
     * Checks whether the tenant has a sufficient balance.
     */
    public static function hasBalance(Tenant|int $tenant): bool
    {
        $result = MessageBillingService::canSend($tenant);
        return $result['allowed'];
    }

    /**
     * Adds balance to a tenant account atomically and records the ledger transaction.
     */
    public static function addBalance(
        Tenant|int $tenant,
        float|string $amount,
        ?string $paymentReference = null,
        ?string $notes = null,
        ?int $adminId = null,
        bool $autoActivate = true,
        string $type = 'topup',
        ?bool $enableBilling = null
    ): Tenant {
        $tx = MessageBillingService::addBalance(
            $tenant,
            $amount,
            $paymentReference,
            $notes,
            $adminId,
            $autoActivate,
            $type,
            $enableBilling
        );

        return $tenant instanceof Tenant ? $tenant->fresh() : Tenant::findOrFail($tenant);
    }

    /**
     * Deducts charge for outbound message via MessageBillingService.
     */
    public static function deductForMessage(
        Tenant|int $tenant,
        string|float $costOrType = 'service',
        ?string $category = null,
        ?string $messageId = null,
        ?string $referenceType = 'message',
        ?string $idempotencyKey = null
    ): float {
        $type = is_string($costOrType) && !is_numeric($costOrType) ? $costOrType : 'service';
        $key = $idempotencyKey ?: ($messageId ? "msg_{$messageId}_charge" : null);

        $tx = MessageBillingService::chargeForMessage(
            $tenant,
            $type,
            $category,
            $messageId ?? uniqid('msg_'),
            $referenceType ?? 'message',
            $key
        );

        return $tx ? (float) $tx->amount : 0.0;
    }
}
