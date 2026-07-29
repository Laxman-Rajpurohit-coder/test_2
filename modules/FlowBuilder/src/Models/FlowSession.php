<?php

namespace Modules\FlowBuilder\Models;

use App\Models\Conversation;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowSession extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'flow_sessions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'conversation_id',
        'customer_number',
        'flow_id',
        'current_node_id',
        'status',
        'variables',
        'expires_at',
    ];

    protected $casts = [
        'variables'  => 'array',
        'expires_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
