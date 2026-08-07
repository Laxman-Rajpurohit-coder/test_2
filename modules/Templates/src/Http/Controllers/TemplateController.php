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
            'name' => 'required|string|max:255',
            'language' => 'required|string',
            'category' => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'components' => 'required|array',
        ]);

        $tenantId = $this->tenantResolver->getActiveTenantId();
        
        // These methods will fail loudly if the tenant has not configured their MSG91 credentials
        $authKey = $this->tenantResolver->getMsg91AuthKey($tenantId);
        if (!$authKey) {
            throw new \Exception("MSG91 Auth Key is not configured for this tenant.");
        }
        $number = $this->tenantResolver->getIntegratedNumber($tenantId);

        // 1. Create on MSG91 API
        $this->msg91Service->create($authKey, $number, [
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'components' => $validated['components'],
        ]);

        // 2. Save locally (using updateOrCreate to avoid unique constraint crashes if the name already existed but was orphaned/rejected)
        WhatsappTemplate::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'language' => $validated['language'],
            ],
            [
                'category' => $validated['category'],
                'components' => $validated['components'],
                'status' => 'pending', // MSG91 creates as pending
                'synced_at' => now(),
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
        $authKey = $this->tenantResolver->getMsg91AuthKey($tenantId);
        if (!$authKey) {
            throw new \Exception("MSG91 Auth Key is not configured for this tenant.");
        }
        $number = $this->tenantResolver->getIntegratedNumber($tenantId);

        // Delete from MSG91
        $this->msg91Service->delete($authKey, $number, $template->name);
        
        // Delete locally
        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
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
