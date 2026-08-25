<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'rate_unit',
        'currency',
        'base_message_rate',
        'utility_template_rate',
        'marketing_template_rate',
        'authentication_template_rate',
        'service_message_rate',
    ];

    protected $casts = [
        'rate_unit' => 'integer',
        'base_message_rate' => 'float',
        'utility_template_rate' => 'float',
        'marketing_template_rate' => 'float',
        'authentication_template_rate' => 'float',
        'service_message_rate' => 'float',
    ];

    /**
     * Get or create default billing settings for a tenant.
     * Safe for Super Admin & production deployments (includes fallback if table does not exist).
     */
    public static function getForTenant(?int $tenantId = null): self
    {
        try {
            if ($tenantId) {
                $setting = self::where('tenant_id', $tenantId)->first();
                if ($setting) return $setting;
            }

            $query = self::query();
            if (method_exists(static::class, 'bootBelongsToTenant')) {
                $query->withoutGlobalScopes();
            }

            $setting = $query->where('tenant_id', $tenantId)->first();
            if ($setting) return $setting;

            return self::create([
                'tenant_id' => $tenantId,
                'rate_unit' => 1000,
                'currency' => 'INR',
                'base_message_rate' => 0.25,
                'utility_template_rate' => 0.35,
                'marketing_template_rate' => 0.78,
                'authentication_template_rate' => 0.30,
                'service_message_rate' => 0.25,
            ]);
        } catch (\Throwable $e) {
            // Fail-safe in-memory object if migration has not been executed yet on production
            $fallback = new self();
            $fallback->rate_unit = 1000;
            $fallback->currency = 'INR';
            $fallback->base_message_rate = 0.25;
            $fallback->utility_template_rate = 0.35;
            $fallback->marketing_template_rate = 0.78;
            $fallback->authentication_template_rate = 0.30;
            $fallback->service_message_rate = 0.25;
            return $fallback;
        }
    }
}
