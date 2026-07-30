<?php

namespace App\Services;

use App\Models\TenantSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantResolverService
{
    protected ?int $activeTenantId = null;

    /**
     * Set the active tenant context for the current request / job lifetime.
     */
    public function setActiveTenantId(?int $tenantId): void
    {
        $this->activeTenantId = $tenantId;
    }

    /**
     * Resolves the active tenant for the current context.
     *
     * @return int The explicitly assigned tenant ID, the authenticated user's tenant ID, or `1` as the default.
     */
    public function getActiveTenantId(): int
    {
        if ($this->activeTenantId !== null) {
            return $this->activeTenantId;
        }

        if (auth()->check() && !empty(auth()->user()->tenant_id)) {
            return (int) auth()->user()->tenant_id;
        }

        return 1; // Default Tenant 1
    }

    /**
     * Finds the tenant number record associated with an integrated WhatsApp number.
     *
     * @param string $integratedNumber The integrated WhatsApp number to resolve.
     * @return object|null The matching tenant number record, or null if no mapping exists.
     */
    public function getTenantNumberRecord(string $integratedNumber): ?object
    {
        $cleanNumber = trim($integratedNumber);
        $record = DB::table('tenant_numbers')
            ->where('integrated_number', $cleanNumber)
            ->first();

        if ($record) {
            return $record;
        }

        Log::warning("TenantResolverService: Number {$integratedNumber} not mapped to any tenant. Falling back to null.");
        return null;
    }

    /**
     * Resolves an integrated WhatsApp number to its tenant ID.
     *
     * @param string $integratedNumber The integrated number to resolve.
     * @return int The ID of the tenant associated with the number.
     * @throws \Exception If the integrated number is not mapped to a tenant.
     */
    public function getTenantIdByIntegratedNumber(string $integratedNumber): int
    {
        $record = $this->getTenantNumberRecord($integratedNumber);
        if ($record) {
            return (int) $record->tenant_id;
        }
        
        throw new \Exception("SECURITY ABORT: Unmapped integrated number {$integratedNumber} cannot be resolved to a tenant. Failing closed.");
    }

    /**
     * Resolves the integrated WhatsApp number configured for a tenant.
     *
     * @param int $tenantId The tenant identifier.
     * @return string The tenant's integrated WhatsApp number.
     * @throws \Exception If no integrated WhatsApp number is configured for the tenant.
     */
    public function getIntegratedNumber(int $tenantId): string
    {
        $number = DB::table('tenant_numbers')
            ->where('tenant_id', $tenantId)
            ->value('integrated_number');

        if (empty($number)) {
            throw new \Exception("No integrated WhatsApp number found for Tenant ID {$tenantId}.");
        }

        return $number;
    }

    /**
     * Resolve MSG91 Auth Key for tenant.
     * Tenant 1: falls back to .env.
     * Tenant 2+: returns null if unconfigured (Feature Disabled Cost Guard).
     */
    public function getMsg91AuthKey(?int $tenantId = null): ?string
    {
        $targetTenantId = $tenantId ?? $this->getActiveTenantId();
        $setting = TenantSetting::where('tenant_id', $targetTenantId)->first();

        if ($setting && !empty($setting->msg91_auth_key)) {
            return $setting->msg91_auth_key; // Automatically decrypted by Encrypted Cast
        }

        if ($targetTenantId === 1) {
            return config('services.msg91.auth_key');
        }

        Log::warning("TenantResolverService: MSG91 Auth Key missing for Tenant {$targetTenantId}. Access disabled.");
        return null;
    }

    /**
     * Resolve OpenAI API Key for tenant.
     * Tenant 1: falls back to .env.
     * Tenant 2+: returns null if unconfigured (Feature Disabled Cost Guard).
     */
    public function getOpenAiApiKey(?int $tenantId = null): ?string
    {
        $targetTenantId = $tenantId ?? $this->getActiveTenantId();
        $setting = TenantSetting::where('tenant_id', $targetTenantId)->first();

        if ($setting && !empty($setting->openai_api_key)) {
            return $setting->openai_api_key; // Automatically decrypted by Encrypted Cast
        }

        if ($targetTenantId === 1) {
            return config('services.openai.api_key') ?? env('OPENAI_API_KEY');
        }

        Log::warning("TenantResolverService: OpenAI API Key missing for Tenant {$targetTenantId}. AI Assistant disabled.");
        return null;
    }

    /**
     * Resolve Flowise Endpoint for tenant.
     */
    public function getFlowiseEndpoint(?int $tenantId = null): ?string
    {
        $targetTenantId = $tenantId ?? $this->getActiveTenantId();
        $setting = TenantSetting::where('tenant_id', $targetTenantId)->first();

        if ($setting && !empty($setting->flowise_endpoint)) {
            return $setting->flowise_endpoint;
        }

        if ($targetTenantId === 1) {
            return config('services.flowise.endpoint') ?? env('FLOWISE_ENDPOINT');
        }

        return null;
    }
}
