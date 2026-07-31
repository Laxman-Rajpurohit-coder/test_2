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
        'customer_name',
        'last_message_at',
        'is_human_escalated',
        'ai_fallback_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_human_escalated' => 'boolean',
        'ai_fallback_count' => 'integer',
    ];

    /**
     * Defines the messages associated with the conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Related WhatsApp messages.
     */
    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
