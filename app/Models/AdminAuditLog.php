<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_user_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class);
    }
}
