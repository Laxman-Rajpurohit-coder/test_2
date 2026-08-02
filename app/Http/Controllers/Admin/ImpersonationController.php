<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AdminAuditLogService;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    /**
     * Begins impersonation of the specified tenant.
     *
     * @param Tenant $tenant The tenant to impersonate.
     * @return \Illuminate\Http\RedirectResponse A redirect response to the chat route.
     */
    public function start(Tenant $tenant)
    {
        session()->put('impersonating_tenant_id', $tenant->id);

        AdminAuditLogService::log('impersonate_start', $tenant);

        return redirect()->route('chat');
    }

    /**
     * Stops tenant impersonation and redirects to the tenant administration page.
     *
     * @return \Illuminate\Http\RedirectResponse The redirect response to the tenant index.
     */
    public function stop()
    {
        $tenantId = session('impersonating_tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        session()->forget('impersonating_tenant_id');

        AdminAuditLogService::log('impersonate_stop', $tenant);

        return redirect()->route('admin.tenants.index');
    }
}
