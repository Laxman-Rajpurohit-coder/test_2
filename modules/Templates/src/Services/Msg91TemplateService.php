<?php

namespace Modules\Templates\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsappTemplate;

class Msg91TemplateService
{
    /**
     * Fetch all templates for a number from MSG91
     */
    public function list(string $authKey, string $number, array $filters = []): array
    {
        $response = Http::withHeaders([
            'authkey' => $authKey,
        ])->get("https://control.msg91.com/api/v5/whatsapp/get-template-client/{$number}", $filters);

        if ($response->failed()) {
            Log::error('MSG91 List Templates Failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Failed to fetch templates from MSG91: ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Create a new template via MSG91 API
     */
    public function create(string $authKey, string $number, array $payload): array
    {
        $payload['integrated_number'] = $number;
        // MSG91 Create Template API expects 'template_name' instead of 'name'
        if (isset($payload['name'])) {
            $payload['template_name'] = $payload['name'];
            unset($payload['name']);
        }

        // Sanitize components: MSG91 wrapper API rejects 'format' inside BODY and BUTTONS components
        if (isset($payload['components']) && is_array($payload['components'])) {
            foreach ($payload['components'] as &$component) {
                if (in_array(($component['type'] ?? ''), ['BODY', 'BUTTONS']) && isset($component['format'])) {
                    unset($component['format']);
                }
            }
        }
        
        $response = Http::withHeaders([
            'authkey' => $authKey,
            'Content-Type' => 'application/json',
        ])->post("https://api.msg91.com/api/v5/whatsapp/client-panel-template/", $payload);

        if ($response->failed()) {
            Log::error('MSG91 Create Template Failed', ['status' => $response->status(), 'body' => $response->body(), 'payload' => $payload]);
            throw new \Exception('Failed to create template on MSG91: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Delete template via MSG91 API.
     *
     * Returns true if the template was deleted successfully or was already absent on MSG91.
     * Returns false if MSG91 returned a known soft-fail (e.g. integration not found),
     * allowing the caller to decide whether to still delete locally.
     *
     * @throws \Exception Only for genuine unexpected failures (network errors, auth failures, etc.).
     */
    public function delete(string $authKey, string $number, string $templateName): bool
    {
        $url = "https://api.msg91.com/api/v5/whatsapp/client-panel-template/?integrated_number={$number}&template_name={$templateName}";

        try {
            $response = Http::timeout(15)->withHeaders([
                'authkey' => $authKey,
            ])->delete($url);
        } catch (\Exception $e) {
            // Network-level failure (connection refused, timeout, DNS error, etc.)
            Log::warning('MSG91 Delete Template — Network Error (template will be deleted locally anyway)', [
                'template_name' => $templateName,
                'error'         => $e->getMessage(),
            ]);
            // Treat as soft-fail: allow local deletion to proceed
            return false;
        }

        if ($response->successful()) {
            return true;
        }

        // Parse the MSG91 response body to distinguish soft-fail from hard-fail
        $responseBody = $response->json() ?? [];
        $errors       = $responseBody['errors'] ?? ($responseBody['error'] ?? '');
        $status       = $responseBody['status'] ?? '';

        // Known soft-fail: template is already absent on MSG91's side.
        // This commonly happens when the WhatsApp integration was removed or
        // the template was deleted directly from the Meta Business Manager.
        // In this case we still want to clean up locally — do NOT throw.
        $softFailPhrases = [
            'whatsapp integration not found',
            'template not found',
            'no template found',
        ];

        $lowerErrors = strtolower(is_array($errors) ? implode(' ', $errors) : (string) $errors);

        foreach ($softFailPhrases as $phrase) {
            if (str_contains($lowerErrors, $phrase)) {
                Log::warning('MSG91 Delete Template — Soft Fail (template absent on MSG91, deleting locally)', [
                    'template_name' => $templateName,
                    'msg91_status'  => $status,
                    'msg91_errors'  => $errors,
                ]);
                return false; // Signal soft-fail to caller
            }
        }

        // Genuine hard failure — authentication error, rate-limit, unexpected 5xx, etc.
        Log::error('MSG91 Delete Template Failed', [
            'http_status'   => $response->status(),
            'template_name' => $templateName,
            'body'          => $response->body(),
        ]);

        throw new \Exception(
            'Failed to delete template on MSG91 (HTTP ' . $response->status() . '): '
            . ($lowerErrors ?: $response->body())
        );
    }

    /**
     * Sync MSG91 response into local whatsapp_templates table
     * Performs full reconciliation: upserts existing, deletes orphaned local templates
     */
    public function syncToLocal(int $tenantId, array $msg91Templates): void
    {
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenantId);
        $remoteTemplateNames = [];

        foreach ($msg91Templates as $remoteTemplateGroup) {
            $name = $remoteTemplateGroup['name'] ?? null;
            $category = $remoteTemplateGroup['category'] ?? 'MARKETING';
            
            if (!$name || empty($remoteTemplateGroup['languages'])) continue;

            foreach ($remoteTemplateGroup['languages'] as $remoteTemplate) {
                $language = $remoteTemplate['language'] ?? 'en';
                
                $remoteTemplateNames[] = $name . '_' . $language;

                WhatsappTemplate::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $name,
                        'language' => $language,
                    ],
                    [
                        'category' => strtoupper($category),
                        'status' => strtolower($remoteTemplate['status'] ?? 'pending'),
                        'components' => $remoteTemplate['code'] ?? [], // MSG91 uses 'code' for components
                        'rejection_reason' => $remoteTemplate['rejection_reason'] ?? null,
                        'synced_at' => now(),
                    ]
                );
            }
        }

        // Reconcile Deletions: Delete local templates that were NOT returned by MSG91
        // We identify templates by 'name_language'
        $localTemplates = WhatsappTemplate::where('tenant_id', $tenantId)->get();
        
        foreach ($localTemplates as $localTemplate) {
            $identifier = $localTemplate->name . '_' . $localTemplate->language;
            if (!in_array($identifier, $remoteTemplateNames)) {
                // Orphaned locally, delete it
                $localTemplate->delete();
            }
        }
    }
}
