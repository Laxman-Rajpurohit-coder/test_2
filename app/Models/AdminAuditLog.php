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

    /**
     * Defines attribute casting rules for the model.
     *
     * @return array The model's attribute casting configuration.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Associates the audit log entry with its administrative user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The administrative user relationship.
     */
    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class);
    }
}
