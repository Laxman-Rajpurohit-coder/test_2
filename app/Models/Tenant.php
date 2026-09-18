<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'status',
        'suspension_reason',
        'balance',
        'billing_enabled',
        'billing_status',
        'suspended_at',
        'features'
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'billing_enabled' => 'boolean',
            'features' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Check if this tenant has a specific feature enabled.
     * Fails closed: returns false if features is null/empty or the key is missing.
     */
    public function hasFeature(string $featureKey): bool
    {
        $features = $this->features;

        if (empty($features) || !is_array($features)) {
            return false;
        }

        return !empty($features[$featureKey]);
    }

    public function settings()
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function numbers()
    {
        return $this->hasMany(TenantNumber::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(TenantBalanceTransaction::class);
    }

    public function hasSufficientBalance(string $cost = '0.0000'): bool
    {
        if (!$this->billing_enabled) {
            return true;
        }
        return bccomp((string) $this->balance, (string) $cost, 4) >= 0;
    }

    public function isBillingExhausted(): bool
    {
        return (bool) $this->billing_enabled && $this->billing_status === 'exhausted';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
