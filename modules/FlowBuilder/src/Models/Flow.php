<?php

namespace Modules\FlowBuilder\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'bot_flows';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'trigger_keyword',
        'graph',
        'is_active',
    ];

    protected $casts = [
        'graph'     => 'array',
        'is_active' => 'boolean',
    ];

    public function sessions()
    {
        return $this->hasMany(FlowSession::class, 'flow_id');
    }
}
