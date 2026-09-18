<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantBalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'admin_user_id',
        'created_by_type',
        'created_by_id',
        'amount',
        'currency',
        'type',
        'description',
        'payment_reference',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'metadata',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
