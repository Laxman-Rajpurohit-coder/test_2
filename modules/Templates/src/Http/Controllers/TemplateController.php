<?php

namespace Modules\Templates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\TenantResolverService;
use Modules\Templates\Services\Msg91TemplateService;

class TemplateController extends Controller
{
    protected Msg91TemplateService $msg91Service;
    protected TenantResolverService $tenantResolver;

    public function __construct(Msg91TemplateService $msg91Service, TenantResolverService $tenantResolver)
    {
        $this->msg91Service = $msg91Service;
        $this->tenantResolver = $tenantResolver;
    }

    public function index()
    {
        $templates = WhatsappTemplate::orderBy('created_at', 'desc')->get();
        return Inertia::render('Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return Inertia::render('Templates/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'language'   => 'required|string',
            'category'   => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'components' => 'required|array',
        ]);

        $tenantId = $this->tenantResolver->getActiveTenantId();

        // Guard: auth key configured?
        $authKey = $this->tenantResolver->getMsg91AuthKey($tenantId);
        if (!$authKey) {
            return back()->withErrors([
                'submit' => 'MSG91 Auth Key is not configured. Go to Settings → API Keys and add it before submitting templates.',
            ]);
        }

        // Guard: integrated number registered?
        try {
            $number = $this->tenantResolver->getIntegratedNumber($tenantId);
        } catch (\Exception $e) {
            return back()->withErrors([
                'submit' => 'No WhatsApp number is configured for your account. Contact your administrator.',
            ]);
        }

        // Submit to MSG91 — catch API errors and surface them cleanly
        try {
            $this->msg91Service->create($authKey, $number, [
                'name'       => $validated['name'],
                'language'   => $validated['language'],
                'category'   => $validated['category'],
                'components' => $validated['components'],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Template store failed for tenant ' . $tenantId, [
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'submit' => 'MSG91 rejected the template: ' . $e->getMessage(),
            ])->withInput();
        }

        // Save locally (updateOrCreate avoids unique constraint crashes on retries)
        WhatsappTemplate::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'name'      => $validated['name'],
                'language'  => $validated['language'],
            ],
            [
                'category'   => $validated['category'],
                'components' => $validated['components'],
                'status'     => 'pending',
                'synced_at'  => now(),
            ]
        );

        return redirect()->route('templates.index')->with('success', 'Template submitted for approval.');
    }


    public function show($id)
    {
        $tenantId = $this->tenantResolver->getActiveTenantId();
        $template = WhatsappTemplate::where('tenant_id', $tenantId)->findOrFail($id);
        return Inertia::render('Templates/Show', [
            'template' => $template,
        ]);
    }

    public function edit($id)
    {
        $tenantId = $this->tenantResolver->getActiveTenantId();
        $template = WhatsappTemplate::where('tenant_id', $tenantId)->findOrFail($id);
        
        return Inertia::render('Templates/Edit', [
            'template' => $template,
        ]);
    }

    public function destroy($id)
    {
        $template = WhatsappTemplate::findOrFail($id);

        $tenantId = $this->tenantResolver->getActiveTenantId();
        $authKey  = $this->tenantResolver->getMsg91AuthKey($tenantId);

        if (!$authKey) {
            return redirect()->back()->with(
                'error',
                'MSG91 Auth Key is not configured. Please add it in Tenant API Settings before managing templates.'
            );
        }

        $number       = $this->tenantResolver->getIntegratedNumber($tenantId);
        $softFail     = false;
        $softFailNote = '';

        try {
            // Returns true on success, false on known soft-fail (template already gone on MSG91's side)
            $deleted = $this->msg91Service->delete($authKey, $number, $template->name);

            if (!$deleted) {
                // Soft-fail: MSG91 says integration/template not found — it's already absent remotely
                $softFail     = true;
                $softFailNote = 'The template was not found on MSG91 (it may have already been removed from WhatsApp Business Manager). ';
            }
        } catch (\Exception $e) {
            // Hard failure from MSG91 (auth error, rate-limit, unexpected server error)
            // Log it but do NOT crash — still offer to remove locally
            \Illuminate\Support\Facades\Log::error('TemplateController::destroy — MSG91 hard failure', [
                'template_id'   => $id,
                'template_name' => $template->name,
                'error'         => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                'Could not delete this template from MSG91: ' . $e->getMessage()
                . ' The local record has been kept. Please try again or delete it manually from WhatsApp Business Manager.'
            );
        }

        // Always remove the local record once MSG91 deletion succeeded or soft-failed
        $template->delete();

        $message = $softFail
            ? $softFailNote . 'The local record has been removed.'
            : 'Template deleted successfully.';

        $flashKey = $softFail ? 'warning' : 'success';

        return redirect()->route('templates.index')->with($flashKey, $message);
    }

    public function sync(Request $request)
    {
        $tenantId = $this->tenantResolver->getActiveTenantId();
        $authKey = $this->tenantResolver->getMsg91AuthKey($tenantId);
        if (!$authKey) {
            throw new \Exception("MSG91 Auth Key is not configured for this tenant.");
        }
        $number = $this->tenantResolver->getIntegratedNumber($tenantId);

        $msg91Templates = $this->msg91Service->list($authKey, $number);
        $this->msg91Service->syncToLocal($tenantId, $msg91Templates);

        // We can just redirect back, or return JSON since we call this async
        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        
        return redirect()->back()->with('success', 'Templates synchronized.');
    }
}
