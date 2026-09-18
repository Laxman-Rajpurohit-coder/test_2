<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Tenant;
use App\Models\TenantBalanceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantBalanceService
{
    /**
     * Calculates the message cost for a given message type/category and tenant.
     */
    public static function calculateMessageCost(string $type = 'service', ?string $category = null, ?Tenant $tenant = null): float
    {
        $billing = BillingSetting::getForTenant($tenant?->id);
        $unitDivider = $billing->rate_unit > 0 ? (float) $billing->rate_unit : 1000.0;

        $categoryLower = strtolower($category ?? '');

        if ($type === 'template') {
            if (str_contains($categoryLower, 'market')) {
                $unitRate = $billing->marketing_template_rate;
            } elseif (str_contains($categoryLower, 'auth')) {
                $unitRate = $billing->authentication_template_rate;
            } else {
                $unitRate = $billing->utility_template_rate;
            }
        } elseif (in_array($type, ['image', 'video', 'audio', 'document'])) {
            $unitRate = $billing->base_message_rate;
        } else {
            $unitRate = $billing->service_message_rate;
        }

        return round((float) $unitRate / $unitDivider, 4);
    }

    /**
     * Checks whether the tenant has a positive balance.
     * If the balance is zero or depleted, the tenant is automatically suspended.
     */
    public static function hasBalance(Tenant|int $tenant): bool
    {
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant);

        if (!$tenantModel) {
            return false;
        }

        if ((float) $tenantModel->balance <= 0) {
            if ($tenantModel->status !== 'suspended') {
                $tenantModel->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);

                Log::warning("Tenant {$tenantModel->id} ({$tenantModel->name}) automatically suspended due to zero/negative balance ({$tenantModel->balance}).");
                AdminAuditLogService::log('tenant_auto_suspended_zero_balance', $tenantModel, [
                    'balance' => $tenantModel->balance,
                ]);
            }
            return false;
        }

        return true;
    }

    /**
     * Adds balance to a tenant account atomically and records the ledger transaction.
     * Optionally reactivates the tenant if they were suspended due to zero balance.
     */
    public static function addBalance(
        Tenant|int $tenant,
        float $amount,
        ?string $paymentReference = null,
        ?string $notes = null,
        ?int $adminId = null,
        bool $autoActivate = true
    ): Tenant {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Top-up amount must be greater than zero.');
        }

        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $resolvedAdminId = $adminId ?? auth()->guard('admin')->id();

        return DB::transaction(function () use ($tenantId, $amount, $paymentReference, $notes, $resolvedAdminId, $autoActivate) {
            /** @var Tenant $tenantModel */
            $tenantModel = Tenant::lockForUpdate()->findOrFail($tenantId);

            $newBalance = round((float) $tenantModel->balance + $amount, 4);
            $tenantModel->balance = $newBalance;

            $wasSuspended = $tenantModel->status === 'suspended';
            if ($autoActivate && $wasSuspended && $newBalance > 0) {
                $tenantModel->status = 'active';
                $tenantModel->suspended_at = null;
            }

            $tenantModel->save();

            TenantBalanceTransaction::create([
                'tenant_id' => $tenantModel->id,
                'admin_user_id' => $resolvedAdminId,
                'amount' => $amount,
                'type' => 'topup',
                'description' => $notes ?: 'Balance top-up upon payment receipt',
                'payment_reference' => $paymentReference,
                'balance_after' => $newBalance,
            ]);

            AdminAuditLogService::log('tenant_balance_topup', $tenantModel, [
                'amount' => $amount,
                'payment_reference' => $paymentReference,
                'notes' => $notes,
                'previous_balance' => (float) $tenantModel->getOriginal('balance'),
                'new_balance' => $newBalance,
                'auto_reactivated' => $wasSuspended && $tenantModel->status === 'active',
            ]);

            return $tenantModel;
        });
    }

    /**
     * Deducts the cost of an outbound message and automatically suspends
     * the tenant if the balance hits zero or below.
     */
    public static function deductForMessage(
        Tenant|int $tenant,
        float|string $costOrType = 'service',
        ?string $category = null,
        ?string $messageId = null
    ): float {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return DB::transaction(function () use ($tenantId, $costOrType, $category, $messageId) {
            /** @var Tenant $tenantModel */
            $tenantModel = Tenant::lockForUpdate()->findOrFail($tenantId);

            $cost = is_numeric($costOrType)
                ? (float) $costOrType
                : self::calculateMessageCost((string) $costOrType, $category, $tenantModel);

            $newBalance = round((float) $tenantModel->balance - $cost, 4);
            $tenantModel->balance = $newBalance;

            $nowSuspended = false;
            if ($newBalance <= 0) {
                $tenantModel->status = 'suspended';
                $tenantModel->suspended_at = now();
                $nowSuspended = true;
            }

            $tenantModel->save();

            TenantBalanceTransaction::create([
                'tenant_id' => $tenantModel->id,
                'admin_user_id' => null,
                'amount' => -$cost,
                'type' => 'charge',
                'description' => 'WhatsApp message charge' . ($messageId ? " (Msg #{$messageId})" : ''),
                'payment_reference' => null,
                'balance_after' => $newBalance,
            ]);

            if ($nowSuspended) {
                Log::warning("Tenant {$tenantModel->id} balance reached {$newBalance} after message deduction. Tenant suspended.");
                AdminAuditLogService::log('tenant_auto_suspended_zero_balance', $tenantModel, [
                    'balance' => $newBalance,
                    'trigger_message_id' => $messageId,
                ]);
            }

            return $cost;
        });
    }
}
