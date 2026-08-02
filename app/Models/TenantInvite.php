<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantInvite extends Model
{
    protected $fillable = [
        'tenant_id',
        'email',
        'token',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
