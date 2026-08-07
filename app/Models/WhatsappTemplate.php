<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToTenant;

class WhatsappTemplate extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'language',
        'category',
        'status',
        'components',
        'rejection_reason',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
