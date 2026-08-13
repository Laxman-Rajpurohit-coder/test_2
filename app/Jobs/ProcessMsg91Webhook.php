<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Events\MessageReceived;
use App\Services\TenantResolverService;
use Illuminate\Support\Facades\DB;

class ProcessMsg91Webhook implements ShouldQueue
{
    use Queueable;

    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Processes the MSG91 webhook payload, synchronizing the conversation and message records
     * and triggering inbound message handling when applicable.
     */
    public function handle(): void
    {
        try {
            // 1. Handle Template Webhooks (Status & Category Updates)
            $type = $this->payload['type'] ?? '';
            if ($type === 'message_template_status_update' || $type === 'template_category_update') {
                $dataStr = $this->payload['data'] ?? '{}';
                $data = json_decode($dataStr, true);
                if (is_array($data)) {
                    $templateName = $data['message_template_name'] ?? $this->payload['value'] ?? null;
                    $language = $data['message_template_language'] ?? null;
                    
                    if ($templateName && $language) {
                        $updateData = [];
                        if ($type === 'message_template_status_update' && isset($data['event'])) {
                            $updateData['status'] = strtolower($data['event']);
                            if (isset($data['reason']) && $data['reason'] !== 'NONE') {
                                $updateData['rejection_reason'] = $data['reason'];
                            }
                        }
                        if ($type === 'template_category_update' && isset($data['new_category'])) {
                            $updateData['category'] = strtoupper($data['new_category']);
                        }
                        
                        if (!empty($updateData)) {
                            \App\Models\WhatsappTemplate::withoutGlobalScopes()
                                ->where('name', $templateName)
                                ->where('language', $language)
                                ->update($updateData);
                            
                            \Illuminate\Support\Facades\Log::info("MSG91 Webhook: Updated Template '{$templateName}'", $updateData);
                        }

                    }
                }
                return; // Stop processing further for template webhooks
            }

            // 2. Handle Message Webhooks (Inbound/Outbound)
            $rawDirection = $this->payload['direction'] ?? null;
            $direction = 0;
            if ($rawDirection !== null) {
                $direction = (int) $rawDirection;
            } elseif (!isset($this->payload['direction']) && (isset($this->payload['text']) || isset($this->payload['content']) || isset($this->payload['url']))) {
                $direction = 0;
            }
            
            $customerNumber = $this->payload['customerNumber'] ?? $this->payload['mobile'] ?? null;
            if (!$customerNumber) {
                return; // Invalid payload without customer number
            }

            // Normalize customer number (e.g. 10 digits -> 91xxxxxxxxxx)
            $customerNumber = \App\Support\PhoneNumber::normalize($customerNumber);

            $customerName = $this->payload['customerName'] ?? null;

            // Resolve Tenant ID and Tenant Number ID by integratedNumber in webhook payload
            $integratedNumber = $this->payload['integratedNumber'] ?? null;
            if (!$integratedNumber) {
                \Illuminate\Support\Facades\Log::warning("ProcessMsg91Webhook: No integratedNumber in payload. Cannot resolve tenant.");
                return;
            }
            
            $tenantNumberRecord = app(TenantResolverService::class)->getTenantNumberRecord($integratedNumber);
            if (!$tenantNumberRecord) {
                throw new \Exception("SECURITY ABORT: Webhook received for unmapped integrated number {$integratedNumber}. Failing closed.");
            }
            
            $tenantId = (int) $tenantNumberRecord->tenant_id;
            $tenantNumberId = $tenantNumberRecord->id;
            
            app(TenantResolverService::class)->setActiveTenantId($tenantId);

            // Auto-create contact if it doesn't exist for both inbound and outbound messages
            try {
                \App\Models\Contact::firstOrCreate(
                    ['tenant_id' => $tenantId, 'phone_number' => $customerNumber],
                    ['name' => $customerName ?: 'Unknown']
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("ProcessMsg91Webhook: Failed to auto-create contact: " . $e->getMessage());
            }

            // Identify if we need to increment unread count for inbound message
            $incrementUnread = $direction === 0 ? 1 : 0;
            $convSql = "
                INSERT INTO conversations (tenant_id, tenant_number_id, customer_number, last_message_at, created_at, updated_at, unread_count)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (tenant_id, customer_number)
                DO UPDATE SET 
                    tenant_number_id = EXCLUDED.tenant_number_id,
                    last_message_at = EXCLUDED.last_message_at,
                    updated_at = EXCLUDED.updated_at
                RETURNING id
            ";
            $convResult = DB::select($convSql, [$tenantId, $tenantNumberId, $customerNumber, now(), now(), now(), 0]);
            $conversationId = $convResult[0]->id;

            // Explicitly increment unread count to avoid SQLite ON CONFLICT edge cases
            if ($incrementUnread > 0) {
                DB::table('conversations')->where('id', $conversationId)->increment('unread_count');
            }

            // Direct parser for MSG91 Webhook Schema (URL, ContentType, Text, Caption, Button/List Payloads)
            $contentData = null;
            $url = $this->payload['url'] ?? $this->payload['media']['url'] ?? $this->payload['media'] ?? null;
            $rawType = $this->payload['contentType'] ?? $this->payload['messageType'] ?? $this->payload['type'] ?? 'image';
            $caption = $this->payload['caption'] ?? '';
            $text = $this->payload['text'] ?? $this->payload['content']['text'] ?? $this->payload['content'] ?? null;

            // Handle Opt-out (STOP / UNSUBSCRIBE)
            if ($direction === 0 && is_string($text)) {
                $normalizedText = strtoupper(trim($text));
                if ($normalizedText === 'STOP' || $normalizedText === 'UNSUBSCRIBE') {
                    \Illuminate\Support\Facades\DB::table('contacts')
                        ->where('tenant_id', $tenantId)
                        ->where('phone_number', $customerNumber)
                        ->update(['is_subscribed' => false]);
                }
            }

            // Extract WhatsApp Interactive Button or List Reply ID / Payload
            $buttonPayload = $this->payload['button']['payload']
                ?? $this->payload['button_reply']['id']
                ?? $this->payload['interactive']['button_reply']['id']
                ?? $this->payload['list_reply']['id']
                ?? null;

            // 1. If MSG91 delivered a Media URL directly
            if (!empty($url) && is_string($url) && str_starts_with($url, 'http')) {
                $type = 'image';
                if (str_contains(strtolower($rawType), 'audio') || str_contains(strtolower($rawType), 'voice') || str_contains(strtolower($url), '.mp3') || str_contains(strtolower($url), '.ogg') || str_contains(strtolower($url), '.webm')) {
                    $type = 'audio';
                } elseif (str_contains(strtolower($rawType), 'image') || str_contains(strtolower($rawType), 'photo') || str_contains(strtolower($url), '.jpg') || str_contains(strtolower($url), '.png') || str_contains(strtolower($url), '.jpeg')) {
                    $type = 'image';
                }

                $contentData = [
                    'type' => $type,
                    'url' => $url,
                    'caption' => is_string($caption) ? $caption : '',
                ];
            } 
            // 2. If MSG91 delivered Text or Button content
            elseif (!empty($text) && is_string($text) && strlen(trim($text)) > 0 && $text !== '{{text}}') {
                $contentData = ['type' => 'text', 'text' => trim($text)];
            }
            // 3. Fallback for nested content structure
            else {
                $rawContent = $this->payload['content'] ?? [];
                if (isset($rawContent['image']) || isset($this->payload['image'])) {
                    $node = $rawContent['image'] ?? $this->payload['image'];
                    $contentData = [
                        'type' => 'image',
                        'url' => is_array($node) ? ($node['link'] ?? $node['url'] ?? '') : (string)$node,
                        'caption' => is_array($node) ? ($node['caption'] ?? '') : '',
                    ];
                } elseif (isset($rawContent['audio']) || isset($this->payload['audio'])) {
                    $node = $rawContent['audio'] ?? $this->payload['audio'];
                    $contentData = [
                        'type' => 'audio',
                        'url' => is_array($node) ? ($node['link'] ?? $node['url'] ?? '') : (string)$node,
                    ];
                } else {
                    $contentData = [
                        'type' => 'text',
                        'text' => '📷 Received Media / Voice Note'
                    ];
                }
            }

            $requestId = $this->payload['requestId'] ?? null;
            $wamid = $this->payload['uuid'] ?? null;
            $status = $direction === 0 ? 'received' : strtolower($this->payload['eventName'] ?? 'sent');

            // Explicit Status Monotonicity Rank Table
            $statusWeight = [
                'received'  => 0,
                'queued'    => 1,
                'failed'    => 1.5,
                'sent'      => 2,
                'delivered' => 3,
                'read'      => 4,
            ];

            $existingMessage = null;
            if ($wamid) {
                $existingMessage = DB::table('whatsapp_messages')->where('meta_uuid', $wamid)->first();
            }
            if (!$existingMessage && $requestId) {
                $existingMessage = DB::table('whatsapp_messages')->where('request_id', $requestId)->first();
            }

            $targetMessageId = null;

            if ($existingMessage) {
                $targetMessageId = $existingMessage->id;
                $updateFields = ['updated_at' => now()];

                $incomingVendorTs = isset($this->payload['ts']) ? date('Y-m-d H:i:s', strtotime($this->payload['ts'])) : now();
                $isNewerTimestamp = empty($existingMessage->vendor_timestamp) || ($incomingVendorTs >= $existingMessage->vendor_timestamp);

                if ($isNewerTimestamp) {
                    $updateFields['vendor_timestamp'] = $incomingVendorTs;
                    if ($status === 'failed' && !empty($this->payload['reason'])) {
                        $updateFields['failure_reason'] = $this->payload['reason'];
                    }
                }

                $currentRank = $statusWeight[$existingMessage->status] ?? 0;
                $newRank = $statusWeight[$status] ?? 0;

                if ($newRank > $currentRank) {
                    $updateFields['status'] = $status;
                }

                if ($wamid && empty($existingMessage->meta_uuid)) {
                    $updateFields['meta_uuid'] = $wamid;
                }
                if ($requestId && empty($existingMessage->request_id)) {
                    $updateFields['request_id'] = $requestId;
                }
                if (!empty($contentData) && ($existingMessage->content === '[]' || empty($existingMessage->content) || $existingMessage->content === '{"text":"","type":"text"}' || str_contains($existingMessage->content, 'Received Media'))) {
                    $updateFields['content'] = json_encode($contentData);
                }

                DB::table('whatsapp_messages')->where('id', $existingMessage->id)->update($updateFields);
                
                // Sync status to campaign_recipients if applicable
                if (isset($updateFields['status'])) {
                    DB::table('campaign_recipients')
                        ->where('whatsapp_message_id', $existingMessage->id)
                        ->update([
                            'status' => $updateFields['status'],
                            'failure_reason' => $updateFields['failure_reason'] ?? null,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                try {
                    $targetMessageId = Str::uuid()->toString();
                    DB::table('whatsapp_messages')->insert([
                        'id'               => $targetMessageId,
                        'tenant_id'        => $tenantId,
                        'conversation_id'  => $conversationId,
                        'request_id'       => $requestId,
                        'meta_uuid'        => $wamid,
                        'direction'        => $direction === 0 ? 'inbound' : 'outbound',
                        'status'           => $status,
                        'content'          => json_encode($contentData),
                        'failure_reason'   => ($status === 'failed' && !empty($this->payload['reason'])) ? $this->payload['reason'] : null,
                        'vendor_timestamp' => isset($this->payload['ts']) ? date('Y-m-d H:i:s', strtotime($this->payload['ts'])) : now(),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $existingMessage = null;
                    if ($wamid) {
                        $existingMessage = DB::table('whatsapp_messages')->where('meta_uuid', $wamid)->first();
                    }
                    if (!$existingMessage && $requestId) {
                        $existingMessage = DB::table('whatsapp_messages')->where('request_id', $requestId)->first();
                    }

                    if ($existingMessage) {
                        $targetMessageId = $existingMessage->id;
                        $updateFields = ['updated_at' => now()];

                        $incomingVendorTs = isset($this->payload['ts']) ? date('Y-m-d H:i:s', strtotime($this->payload['ts'])) : now();
                        $isNewerTimestamp = empty($existingMessage->vendor_timestamp) || ($incomingVendorTs >= $existingMessage->vendor_timestamp);

                        if ($isNewerTimestamp) {
                            $updateFields['vendor_timestamp'] = $incomingVendorTs;
                            if ($status === 'failed' && !empty($this->payload['reason'])) {
                                $updateFields['failure_reason'] = $this->payload['reason'];
                            }
                        }

                        $currentRank = $statusWeight[$existingMessage->status] ?? 0;
                        $newRank = $statusWeight[$status] ?? 0;

                        if ($newRank > $currentRank) {
                            $updateFields['status'] = $status;
                        }

                        if ($wamid && empty($existingMessage->meta_uuid)) {
                            $updateFields['meta_uuid'] = $wamid;
                        }
                        if ($requestId && empty($existingMessage->request_id)) {
                            $updateFields['request_id'] = $requestId;
                        }
                        if (!empty($contentData) && ($existingMessage->content === '[]' || empty($existingMessage->content) || $existingMessage->content === '{"text":"","type":"text"}' || str_contains($existingMessage->content, 'Received Media'))) {
                            $updateFields['content'] = json_encode($contentData);
                        }
                        DB::table('whatsapp_messages')->where('id', $existingMessage->id)->update($updateFields);
                        
                        // Sync status to campaign_recipients if applicable
                        if (isset($updateFields['status'])) {
                            DB::table('campaign_recipients')
                                ->where('whatsapp_message_id', $existingMessage->id)
                                ->update([
                                    'status' => $updateFields['status'],
                                    'failure_reason' => $updateFields['failure_reason'] ?? null,
                                    'updated_at' => now(),
                                ]);
                        }
                    } else {
                        throw $e;
                    }
                }
            }

            // Set 24-hour Redis Session TTL (only on inbound from customer)
            if ($direction === 0) {
                Cache::put('session:' . $customerNumber, true, now()->addHours(24));
            }

            // Broadcast real-time websocket payload (Non-blocking guard)
            if ($targetMessageId) {
                $broadcastMessage = DB::table('whatsapp_messages')->find($targetMessageId);
                try {
                    broadcast(new MessageReceived($conversationId, $broadcastMessage))->toOthers();
                } catch (\Throwable $e) {
                    Log::warning('WebSocket Broadcast Failed (Non-blocking): ' . $e->getMessage());
                }

                // DISPATCH AUTOMATED BOT TRIGGER JOB FOR INBOUND TEXT / BUTTON MESSAGES
                if ($direction === 0 && (!empty($text) || !empty($buttonPayload))) {
                    ProcessBotTriggerJob::dispatch(
                        $targetMessageId,
                        $conversationId,
                        $customerNumber,
                        trim($text ?? ''),
                        $customerName,
                        $buttonPayload
                    );
                }
            }

        } catch (\Exception $e) {
            Log::error('MSG91 Webhook Processing Failed: ' . $e->getMessage());
            // Rethrow the exception so the queue worker marks the job as failed and sends it to the DLQ (failed_jobs)
            throw $e;
        }
    }
}
