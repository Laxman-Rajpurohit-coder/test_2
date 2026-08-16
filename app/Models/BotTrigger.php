<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotTrigger extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'trigger_type',
        'keyword',
        'match_type',
        'response_type',
        'response_payload',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'is_active'        => 'boolean',
        'priority'         => 'integer',
    ];
}
