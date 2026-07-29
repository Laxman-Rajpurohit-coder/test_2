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

    public function handle(): void
    {
        try {
            // Identify direction (0 = Inbound, 1 = Outbound)
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

            $customerName = $this->payload['customerName'] ?? null;

            // Resolve Tenant ID by integratedNumber in webhook payload AND bind to TenantResolverService
            $integratedNumber = $this->payload['integratedNumber'] ?? config('services.msg91.integrated_number') ?? '917425889008';
            $tenantId = app(TenantResolverService::class)->getTenantIdByIntegratedNumber($integratedNumber);
            app(TenantResolverService::class)->setActiveTenantId($tenantId);

            // 1. PostgreSQL atomic upsert for conversations (with tenant_id)
            $convSql = "
                INSERT INTO conversations (tenant_id, customer_number, last_message_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (customer_number)
                DO UPDATE SET 
                    last_message_at = EXCLUDED.last_message_at,
                    updated_at = EXCLUDED.updated_at
                RETURNING id
            ";
            $convResult = DB::select($convSql, [$tenantId, $customerNumber, now(), now(), now()]);
            $conversationId = $convResult[0]->id;

            // Direct parser for MSG91 Webhook Schema (URL, ContentType, Text, Caption, Button/List Payloads)
            $contentData = null;
            $url = $this->payload['url'] ?? $this->payload['media']['url'] ?? $this->payload['media'] ?? null;
            $rawType = $this->payload['contentType'] ?? $this->payload['messageType'] ?? $this->payload['type'] ?? 'image';
            $caption = $this->payload['caption'] ?? '';
            $text = $this->payload['text'] ?? $this->payload['content']['text'] ?? $this->payload['content'] ?? null;

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
        }
    }
}
