<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AdminAuditLogService;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Tenant $tenant)
    {
        session()->put('impersonating_tenant_id', $tenant->id);

        AdminAuditLogService::log('impersonate_start', $tenant);

        return redirect()->route('chat');
    }

    public function stop()
    {
        $tenantId = session('impersonating_tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        session()->forget('impersonating_tenant_id');

        AdminAuditLogService::log('impersonate_stop', $tenant);

        return redirect()->route('admin.tenants.index');
    }
}
