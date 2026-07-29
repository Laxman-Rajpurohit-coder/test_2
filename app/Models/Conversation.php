<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_number',
        'last_message_at',
        'is_human_escalated',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_human_escalated' => 'boolean',
    ];

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
