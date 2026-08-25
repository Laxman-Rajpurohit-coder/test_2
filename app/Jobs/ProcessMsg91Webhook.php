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

            // Helper to safely parse JSON strings or arrays from MSG91 payloads
            $parseJsonNode = function ($node) {
                if (is_array($node)) return $node;
                if (is_string($node)) {
                    $trimmed = trim($node);
                    if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                        $decoded = json_decode($trimmed, true);
                        return is_array($decoded) ? $decoded : [];
                    }
                }
                return [];
            };

            $buttonNode = $parseJsonNode($this->payload['button'] ?? []);
            $interactiveNode = $parseJsonNode($this->payload['interactive'] ?? []);
            $messagesNode = $parseJsonNode($this->payload['messages'] ?? []);
            $firstMessage = !empty($messagesNode) && isset($messagesNode[0]) && is_array($messagesNode[0]) ? $messagesNode[0] : [];
            $firstMessageButton = isset($firstMessage['button']) ? $parseJsonNode($firstMessage['button']) : [];

            // Extract WhatsApp Interactive Button or List Reply ID / Payload & Title
            $buttonPayload = $buttonNode['payload']
                ?? $firstMessageButton['payload']
                ?? ($interactiveNode['button_reply']['id'] ?? null)
                ?? ($this->payload['button_reply']['id'] ?? null)
                ?? ($this->payload['list_reply']['id'] ?? null)
                ?? ($this->payload['quick_reply']['payload'] ?? null)
                ?? (is_string($this->payload['button'] ?? null) && !str_starts_with(trim($this->payload['button']), '{') ? $this->payload['button'] : null);

            $buttonTitle = $buttonNode['text']
                ?? $buttonNode['title']
                ?? $firstMessageButton['text']
                ?? $firstMessageButton['title']
                ?? ($interactiveNode['button_reply']['title'] ?? null)
                ?? ($this->payload['button_reply']['title'] ?? null)
                ?? ($this->payload['quick_reply']['text'] ?? null)
                ?? ($this->payload['title'] ?? null)
                ?? (is_string($this->payload['button'] ?? null) && !str_starts_with(trim($this->payload['button']), '{') ? $this->payload['button'] : null);

            $isButtonReply = !empty($buttonPayload) || !empty($buttonTitle) || in_array(strtolower($rawType), ['button', 'button_reply', 'interactive', 'quick_reply']);

            // 1. If customer clicked an Interactive/Template Button or List item
            if ($isButtonReply && (!empty($buttonTitle) || !empty($buttonPayload))) {
                $effectiveBtnText = !empty($buttonTitle) ? trim($buttonTitle) : trim($buttonPayload);
                $contentData = [
                    'type'        => 'button_reply',
                    'text'        => $effectiveBtnText,
                    'button_text' => $effectiveBtnText,
                    'payload'     => $buttonPayload,
                ];
                if (empty($text)) {
                    $text = $effectiveBtnText;
                }
            }
            // 2. Try comprehensive media extraction from all possible MSG91 payload locations
            elseif ($extractedMedia = $this->extractMediaFromPayload($this->payload)) {
                $contentData = $extractedMedia;
            } 
            // 3. If MSG91 delivered Text content
            elseif (!empty($text) && is_string($text) && strlen(trim($text)) > 0 && $text !== '{{text}}') {
                $contentData = ['type' => 'text', 'text' => trim($text)];
            }
            // 4. Fallback text content if no text or media URL could be extracted
            else {
                $contentData = [
                    'type' => 'text',
                    'text' => is_string($text) && !empty(trim($text)) ? trim($text) : 'Media message'
                ];
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
                $existingMessage = DB::table('messages')->where('meta_uuid', $wamid)->first();
            }
            // Inbound messages (direction === 0) have their own unique wamid and should NEVER match an existing outbound message by request_id!
            if (!$existingMessage && $requestId && $direction !== 0) {
                $existingMessage = DB::table('messages')->where('request_id', $requestId)->first();
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

                DB::table('messages')->where('id', $existingMessage->id)->update($updateFields);
                
                // Sync status to campaign_recipients if applicable (Non-blocking guard)
                if (isset($updateFields['status'])) {
                    try {
                        DB::table('campaign_recipients')
                            ->where('whatsapp_message_id', $existingMessage->id)
                            ->update([
                                'status' => $updateFields['status'],
                                'failure_reason' => $updateFields['failure_reason'] ?? null,
                                'updated_at' => now(),
                            ]);
                    } catch (\Throwable $e) {
                        Log::warning('ProcessMsg91Webhook: campaign_recipients status sync failed (non-blocking): ' . $e->getMessage());
                    }
                }
            } else {
                // Outbound status event callbacks (direction !== 0 or status eventName) MUST NOT create new chat message records if no existing message matches!
                $eventNameLower = strtolower($this->payload['eventName'] ?? '');
                $isStatusCallback = $direction !== 0 || in_array($eventNameLower, ['failed', 'delivered', 'read', 'submitted']);

                if ($isStatusCallback) {
                    Log::warning("ProcessMsg91Webhook: Received status callback '{$eventNameLower}' for unmatched outbound message (wamid: {$wamid}, requestId: {$requestId}). Skipping insert.");
                    return;
                }

                try {
                    $targetMessageId = Str::uuid()->toString();
                    DB::table('messages')->insert([
                        'id'               => $targetMessageId,
                        'tenant_id'        => $tenantId,
                        'conversation_id'  => $conversationId,
                        'request_id'       => $direction === 0 ? null : $requestId,
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
                        $existingMessage = DB::table('messages')->where('meta_uuid', $wamid)->first();
                    }
                    if (!$existingMessage && $requestId && $direction !== 0) {
                        $existingMessage = DB::table('messages')->where('request_id', $requestId)->first();
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
                        DB::table('messages')->where('id', $existingMessage->id)->update($updateFields);
                        
                        // Sync status to campaign_recipients if applicable (Non-blocking guard)
                        if (isset($updateFields['status'])) {
                            try {
                                DB::table('campaign_recipients')
                                    ->where('whatsapp_message_id', $existingMessage->id)
                                    ->update([
                                        'status' => $updateFields['status'],
                                        'failure_reason' => $updateFields['failure_reason'] ?? null,
                                        'updated_at' => now(),
                                    ]);
                            } catch (\Throwable $e) {
                                Log::warning('ProcessMsg91Webhook: campaign_recipients status sync failed (non-blocking): ' . $e->getMessage());
                            }
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
                $broadcastMessage = DB::table('messages')->find($targetMessageId);

                // Enrich broadcast with customer_name/customer_number from conversation.
                // DB::table()->find() only returns message-table columns; the frontend needs
                // these fields to display the conversation without falling back to 'New Contact'.
                if ($broadcastMessage) {
                    $conv = DB::table('conversations')
                        ->where('id', $conversationId)
                        ->select('customer_name', 'customer_number', 'channel', 'tenant_number_id')
                        ->first();
                    if ($conv) {
                        $broadcastMessage->customer_name    = $conv->customer_name ?? $customerName;
                        $broadcastMessage->customer_number  = $conv->customer_number ?? $customerNumber;
                        $broadcastMessage->channel          = $conv->channel;
                        $broadcastMessage->tenant_number_id = $conv->tenant_number_id;
                    }
                }

                try {
                    broadcast(new MessageReceived($conversationId, $broadcastMessage))->toOthers();
                } catch (\Throwable $e) {
                    Log::warning('WebSocket Broadcast Failed (Non-blocking): ' . $e->getMessage());
                }

                // DISPATCH AUTOMATED BOT TRIGGER JOB FOR INBOUND TEXT / BUTTON MESSAGES
                if ($direction === 0 && (!empty($text) || !empty($buttonPayload) || !empty($buttonTitle))) {
                    ProcessBotTriggerJob::dispatchSync(
                        $targetMessageId,
                        $conversationId,
                        $customerNumber,
                        trim($text ?? ($buttonTitle ?? '')),
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

    /**
     * Comprehensive Media Extraction Helper for MSG91 Inbound & Outbound Webhook Payloads
     */
    protected function extractMediaFromPayload(array $payload): ?array
    {
        if (isset($payload['content']) && is_string($payload['content']) && str_starts_with(trim($payload['content']), '{')) {
            $decoded = json_decode(trim($payload['content']), true);
            if (is_array($decoded)) {
                $payload['content'] = $decoded;
            }
        }

        $possibleNodes = [
            $payload['image'] ?? null,
            $payload['audio'] ?? null,
            $payload['video'] ?? null,
            $payload['document'] ?? null,
            $payload['voice'] ?? null,
            $payload['sticker'] ?? null,
            $payload['media'] ?? null,
            $payload['content']['image'] ?? null,
            $payload['content']['audio'] ?? null,
            $payload['content']['video'] ?? null,
            $payload['content']['document'] ?? null,
            $payload['content']['voice'] ?? null,
            $payload['content']['sticker'] ?? null,
            $payload['content']['media'] ?? null,
        ];

        foreach ($possibleNodes as $node) {
            if (is_string($node) && str_starts_with(trim($node), 'http')) {
                return $this->buildMediaContentData(trim($node), $payload['caption'] ?? '', $payload['contentType'] ?? $payload['type'] ?? '');
            }
            if (is_array($node)) {
                $foundUrl = $node['link'] ?? $node['url'] ?? $node['file_url'] ?? $node['attachment_url'] ?? $node['media_url'] ?? null;
                if (!empty($foundUrl) && is_string($foundUrl) && str_starts_with(trim($foundUrl), 'http')) {
                    $caption = $node['caption'] ?? $payload['caption'] ?? '';
                    $type = $node['type'] ?? $payload['contentType'] ?? $payload['type'] ?? '';
                    return $this->buildMediaContentData(trim($foundUrl), $caption, $type);
                }
            }
        }

        // Direct URL keys
        $directKeys = ['url', 'file', 'file_url', 'media_url', 'mediaUrl', 'attachment_url', 'link'];
        foreach ($directKeys as $key) {
            $val = $payload[$key] ?? $payload['content'][$key] ?? null;
            if (!empty($val) && is_string($val) && str_starts_with(trim($val), 'http')) {
                return $this->buildMediaContentData(trim($val), $payload['caption'] ?? '', $payload['contentType'] ?? $payload['type'] ?? '');
            }
        }

        // Check if text itself is a media URL
        $text = $payload['text'] ?? $payload['content']['text'] ?? null;
        if (is_string($text) && str_starts_with(trim($text), 'http')) {
            return $this->buildMediaContentData(trim($text), $payload['caption'] ?? '', $payload['contentType'] ?? $payload['type'] ?? '');
        }

        return null;
    }

    protected function buildMediaContentData(string $url, string $caption = '', string $rawType = ''): array
    {
        $urlLower = strtolower($url);
        $rawTypeLower = strtolower($rawType);

        $type = 'image';
        if (str_contains($rawTypeLower, 'audio') || str_contains($rawTypeLower, 'voice') || preg_match('/\.(mp3|ogg|wav|webm|m4a)$/i', $urlLower)) {
            $type = 'audio';
        } elseif (str_contains($rawTypeLower, 'video') || preg_match('/\.(mp4|mov|avi|mkv)$/i', $urlLower)) {
            $type = 'video';
        } elseif (str_contains($rawTypeLower, 'document') || str_contains($rawTypeLower, 'pdf') || preg_match('/\.(pdf|doc|docx|xls|xlsx|zip)$/i', $urlLower)) {
            $type = 'document';
        }

        return [
            'type' => $type,
            'url' => $url,
            'caption' => is_string($caption) ? $caption : '',
        ];
    }
}
