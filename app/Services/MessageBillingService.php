<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\TenantBalanceTransaction;
use App\Jobs\SendCampaignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageBillingService
{
    /**
     * Calculates the unit cost for a message as a 4-decimal scale string.
     */
    public static function calculateCost(string $type, ?string $category, Tenant|int $tenant): string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : (int) $tenant;
        $billing = BillingSetting::getForTenant($tenantId);
        $divider = (string) ($billing->rate_unit > 0 ? $billing->rate_unit : 1000);

        $unitRate = (string) $billing->service_message_rate;

        if ($type === 'template') {
            $cat = strtolower((string) $category);
            if (str_contains($cat, 'market')) {
                $unitRate = (string) $billing->marketing_template_rate;
            } elseif (str_contains($cat, 'auth')) {
                $unitRate = (string) $billing->authentication_template_rate;
            } else {
                $unitRate = (string) $billing->utility_template_rate;
            }
        } elseif (in_array($type, ['image', 'audio', 'video', 'document'], true)) {
            $unitRate = (string) $billing->base_message_rate;
        }

        // bcdiv with 4 decimals precision
        return bcdiv($unitRate, $divider, 4);
    }

    /**
     * Checks whether the tenant has sufficient balance to cover the given cost.
     */
    public static function hasSufficientBalance(Tenant|int $tenant, string $cost = '0.0000'): bool
    {
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant);
        if (!$tenantModel) {
            return false;
        }

        if (!$tenantModel->billing_enabled) {
            return true;
        }

        return bccomp((string) $tenantModel->balance, (string) $cost, 4) >= 0;
    }

    /**
     * Evaluates whether an outbound send is permitted for a tenant.
     */
    public static function canSend(Tenant|int $tenant, string $type = 'service', ?string $category = null): array
    {
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant);
        if (!$tenantModel) {
            return [
                'allowed' => false,
                'cost' => '0.0000',
                'balance' => '0.0000',
                'reason' => 'Tenant not found.'
            ];
        }

        // 1. If administrative status is suspended, always block
        if ($tenantModel->status === 'suspended') {
            return [
                'allowed' => false,
                'cost' => '0.0000',
                'balance' => (string) $tenantModel->balance,
                'reason' => 'Account is administratively suspended.'
            ];
        }

        // 2. If prepaid billing is disabled, allow sending
        if (!$tenantModel->billing_enabled) {
            return [
                'allowed' => true,
                'cost' => '0.0000',
                'balance' => (string) $tenantModel->balance,
                'reason' => null
            ];
        }

        $cost = self::calculateCost($type, $category, $tenantModel);

        if (bccomp((string) $tenantModel->balance, $cost, 4) < 0) {
            return [
                'allowed' => false,
                'cost' => $cost,
                'balance' => (string) $tenantModel->balance,
                'reason' => 'Wallet balance is insufficient (₹' . number_format((float)$tenantModel->balance, 2) . ' available, ₹' . $cost . ' required). Outbound sending is paused.'
            ];
        }

        return [
            'allowed' => true,
            'cost' => $cost,
            'balance' => (string) $tenantModel->balance,
            'reason' => null
        ];
    }

    /**
     * Atomically and idempotently charges the tenant for an outbound message.
     * Guarantees non-negative balance: if balance < cost, send is rejected and balance is NOT debited.
     */
    public static function chargeForMessage(
        Tenant|int $tenant,
        string $type,
        ?string $category,
        string $referenceId,
        string $referenceType = 'message',
        ?string $idempotencyKey = null,
        ?array $metadata = null,
        ?int $userId = null
    ): ?TenantBalanceTransaction {
        // Idempotency check: if already charged, return existing record
        if (!empty($idempotencyKey)) {
            $existing = TenantBalanceTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $tenantId = $tenant instanceof Tenant ? $tenant->id : (int) $tenant;
        $cost = self::calculateCost($type, $category, $tenantId);

        return DB::transaction(function () use ($tenantId, $cost, $type, $category, $referenceId, $referenceType, $idempotencyKey, $metadata, $userId) {
            /** @var Tenant $lockedTenant */
            $lockedTenant = Tenant::where('id', $tenantId)->lockForUpdate()->first();
            if (!$lockedTenant) {
                return null;
            }

            // If prepaid billing is disabled for this tenant, do not charge
            if (!$lockedTenant->billing_enabled) {
                return null;
            }

            // Check if existing transaction was inserted during lock acquisition
            if (!empty($idempotencyKey)) {
                $existing = TenantBalanceTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // Prepaid Rule: If balance < cost, reject without debiting
            if (bccomp((string) $lockedTenant->balance, $cost, 4) < 0) {
                $lockedTenant->update([
                    'billing_status' => 'exhausted',
                ]);
                Log::warning("MessageBillingService: Tenant {$tenantId} balance ({$lockedTenant->balance}) insufficient for cost {$cost}. Marked exhausted without debiting.");
                return null;
            }

            // Sufficient balance: debit funds
            $newBalance = bcsub((string) $lockedTenant->balance, $cost, 4);
            $newBillingStatus = bccomp($newBalance, '0.0000', 4) <= 0 ? 'exhausted' : (bccomp($newBalance, '5.0000', 4) <= 0 ? 'low_balance' : 'active');

            $lockedTenant->update([
                'balance' => $newBalance,
                'billing_status' => $newBillingStatus,
            ]);

            $txType = ($referenceType === 'campaign_recipient') ? 'campaign_charge' : 'message_charge';

            return TenantBalanceTransaction::create([
                'tenant_id' => $tenantId,
                'admin_user_id' => null,
                'created_by_type' => $userId ? 'user' : 'system',
                'created_by_id' => $userId,
                'amount' => $cost,
                'currency' => 'INR',
                'type' => $txType,
                'description' => 'Outbound ' . ucfirst($type) . ' message charge',
                'payment_reference' => null,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_merge($metadata ?? [], [
                    'cost' => $cost,
                    'type' => $type,
                    'category' => $category,
                ]),
                'balance_after' => $newBalance,
            ]);
        });
    }

    /**
     * Refunds an outbound message charge if transmission permanently fails.
     */
    public static function refundMessage(
        Tenant|int $tenant,
        string $amount,
        string $reason,
        string $referenceId,
        string $referenceType = 'message',
        ?string $idempotencyKey = null,
        ?array $metadata = null
    ): ?TenantBalanceTransaction {
        if (!empty($idempotencyKey)) {
            $existing = TenantBalanceTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $tenantId = $tenant instanceof Tenant ? $tenant->id : (int) $tenant;
        $amountStr = number_format((float) $amount, 4, '.', '');

        return DB::transaction(function () use ($tenantId, $amountStr, $reason, $referenceId, $referenceType, $idempotencyKey, $metadata) {
            /** @var Tenant $lockedTenant */
            $lockedTenant = Tenant::where('id', $tenantId)->lockForUpdate()->first();
            if (!$lockedTenant) {
                return null;
            }

            $newBalance = bcadd((string) $lockedTenant->balance, $amountStr, 4);
            $newBillingStatus = bccomp($newBalance, '0.0000', 4) > 0 ? 'active' : 'exhausted';

            $lockedTenant->update([
                'balance' => $newBalance,
                'billing_status' => $newBillingStatus,
            ]);

            return TenantBalanceTransaction::create([
                'tenant_id' => $tenantId,
                'admin_user_id' => null,
                'created_by_type' => 'system',
                'created_by_id' => null,
                'amount' => $amountStr,
                'currency' => 'INR',
                'type' => 'refund',
                'description' => 'Refund: ' . $reason,
                'payment_reference' => null,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_merge($metadata ?? [], [
                    'reason' => $reason,
                    'refunded_amount' => $amountStr,
                ]),
                'balance_after' => $newBalance,
            ]);
        });
    }

    /**
     * Adds balance to a tenant (topup, promotional_credit, or adjustment).
     * Reactivates tenant only if suspension_reason was 'balance_exhausted'.
     */
    public static function addBalance(
        Tenant|int $tenant,
        string|float $amount,
        ?string $paymentRef = null,
        ?string $notes = null,
        ?int $adminId = null,
        bool $autoReactivate = true,
        string $type = 'topup',
        ?bool $enableBilling = null
    ): TenantBalanceTransaction {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : (int) $tenant;
        $amountStr = number_format((float) $amount, 4, '.', '');

        $tx = DB::transaction(function () use ($tenantId, $amountStr, $paymentRef, $notes, $adminId, $autoReactivate, $type, $enableBilling) {
            /** @var Tenant $lockedTenant */
            $lockedTenant = Tenant::where('id', $tenantId)->lockForUpdate()->first();

            $newBalance = bcadd((string) $lockedTenant->balance, $amountStr, 4);

            $updateData = [
                'balance' => $newBalance,
                'billing_status' => 'active',
            ];

            if ($enableBilling !== null) {
                $updateData['billing_enabled'] = (bool) $enableBilling;
            }

            // Only auto-reactivate if the suspension was solely due to balance exhaustion
            if ($autoReactivate && $lockedTenant->status === 'suspended' && $lockedTenant->suspension_reason === 'balance_exhausted') {
                $updateData['status'] = 'active';
                $updateData['suspension_reason'] = null;
                $updateData['suspended_at'] = null;
            }

            $lockedTenant->update($updateData);

            $idempotencyKey = $paymentRef ? ("topup_" . $paymentRef . "_" . $tenantId) : null;
            $desc = $notes ?: ('Balance added via ' . ucfirst(str_replace('_', ' ', $type)));

            $tx = TenantBalanceTransaction::create([
                'tenant_id' => $tenantId,
                'admin_user_id' => $adminId,
                'created_by_type' => $adminId ? 'admin' : 'system',
                'created_by_id' => $adminId,
                'amount' => $amountStr,
                'currency' => 'INR',
                'type' => $type,
                'description' => $desc,
                'payment_reference' => $paymentRef,
                'reference_type' => 'topup',
                'reference_id' => $paymentRef,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'admin_id' => $adminId,
                    'notes' => $notes,
                    'type' => $type,
                ],
                'balance_after' => $newBalance,
            ]);

            return $tx;
        });

        // After transaction commits successfully, auto-resume any paused campaigns
        try {
            $pausedCampaigns = Campaign::withoutGlobalScope('tenant_isolation')
                ->where('tenant_id', $tenantId)
                ->where('status', 'paused_insufficient_balance')
                ->get();

            foreach ($pausedCampaigns as $camp) {
                $camp->update([
                    'status' => 'sending',
                ]);
                SendCampaignJob::dispatch($camp->id);
                Log::info("MessageBillingService: Resumed campaign {$camp->id} for tenant {$tenantId} following balance top-up.");
            }
        } catch (\Throwable $e) {
            Log::error("Failed to auto-resume paused campaigns for tenant {$tenantId}: " . $e->getMessage());
        }

        return $tx;
    }
}
