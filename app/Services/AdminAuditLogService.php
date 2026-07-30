<?php

namespace App\Services;

use App\Models\AdminAuditLog;

class AdminAuditLogService
{
    /**
     * Records an administrative action and its optional target.
     *
     * @param string $action The action performed.
     * @param object|null $target The model or object associated with the action.
     * @param array $metadata Additional metadata; values override the default request IP and user agent.
     * @return AdminAuditLog The created audit log record.
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
