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

        // Sanitize components: MSG91 wrapper API rejects 'format' inside BODY components
        if (isset($payload['components']) && is_array($payload['components'])) {
            foreach ($payload['components'] as &$component) {
                if (($component['type'] ?? '') === 'BODY' && isset($component['format'])) {
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
     * Delete template via MSG91 API
     */
    public function delete(string $authKey, string $number, string $templateName): bool
    {
        $url = "https://api.msg91.com/api/v5/whatsapp/client-panel-template/?integrated_number={$number}&template_name={$templateName}";
        
        $response = Http::withHeaders([
            'authkey' => $authKey,
        ])->delete($url);

        if ($response->failed()) {
            Log::error('MSG91 Delete Template Failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Failed to delete template on MSG91: ' . $response->body());
        }

        return true;
    }

    /**
     * Sync MSG91 response into local whatsapp_templates table
     * Performs full reconciliation: upserts existing, deletes orphaned local templates
     */
    public function syncToLocal(int $tenantId, array $msg91Templates): void
    {
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
