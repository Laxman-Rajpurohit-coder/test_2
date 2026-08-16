<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'messages';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'conversation_id',
        'channel',
        'request_id',
        'meta_uuid',
        'direction',
        'status',
        'content',
        'failure_reason',
        'vendor_timestamp',
        'is_internal',
    ];

    protected $casts = [
        'vendor_timestamp' => 'datetime',
        'is_internal'      => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
