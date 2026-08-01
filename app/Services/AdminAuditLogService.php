<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\Log;

class AdminAuditLogService
{
    /**
     * Records an administrative action and its optional target.
     *
     * @param string $action The action performed (e.g., 'impersonate_start', 'tenant_suspend').
     * @param object|null $target The target object. Non-Eloquent targets are logged with a null ID.
     * @param array $metadata Additional metadata; values are merged with the default IP and user agent.
     * @return AdminAuditLog The created audit log record.
     */
    public static function log(string $action, $target = null, array $metadata = [])
    {
        $adminId = auth()->guard('admin')->id();

        $targetType = null;
        $targetId = null;

        if ($target !== null) {
            $targetType = get_class($target);

            // Only call getKey() if the target actually supports it (Eloquent
            // models do). Calling it on a plain object/array/string would
            // crash — this was CodeRabbit's flagged risk. Log a warning
            // instead of crashing, so a bad call site is visible but doesn't
            // take down whatever admin action triggered it.
            if (method_exists($target, 'getKey')) {
                $targetId = $target->getKey();
            } else {
                Log::warning('AdminAuditLogService: target does not support getKey(), logging with null target_id', [
                    'action' => $action,
                    'target_type' => $targetType,
                ]);
            }
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
