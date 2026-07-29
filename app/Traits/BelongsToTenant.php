<?php

namespace App\Traits;

use App\Services\TenantResolverService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for Eloquent models.
     * Applies Eloquent Global Scope (WHERE tenant_id = current_tenant_id)
     * and automatically sets tenant_id on model creation.
     */
    protected static function bootBelongsToTenant(): void
    {
        // 1. Eloquent Global Scope: Automatically inject WHERE tenant_id = X on ALL queries
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            $tenantId = app(TenantResolverService::class)->getActiveTenantId();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // 2. Auto-assignment on model creation
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $tenantId = app(TenantResolverService::class)->getActiveTenantId();
                $model->tenant_id = $tenantId ?? 1; // Fallback to Default Tenant 1
            }
        });
    }

    /**
     * Relationship to Tenant.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
