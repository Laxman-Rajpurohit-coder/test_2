<?php

namespace App\Services;

use App\Models\AdminAuditLog;

class AdminAuditLogService
{
    /**
     * Log an admin action.
     *
     * @param string $action The action performed (e.g., 'impersonate_start', 'tenant_suspend')
     * @param object|null $target The target model (e.g., Tenant model)
     * @param array $metadata Additional metadata
     * @return AdminAuditLog
     */
    public static function log(string $action, $target = null, array $metadata = [])
    {
        $adminId = auth()->guard('admin')->id();

        $targetType = null;
        $targetId = null;

        if ($target) {
            $targetType = get_class($target);
            $targetId = $target->getKey();
        }

        return AdminAuditLog::create([
            'admin_user_id' => $adminId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => array_merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ], $metadata)
        ]);
    }
}
