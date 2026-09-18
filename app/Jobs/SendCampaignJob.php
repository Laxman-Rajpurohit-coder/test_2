<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\OutboundReplyService;
use App\Services\TenantResolverService;
// Note: assuming Msg91PayloadBuilder exists or we construct the payload directly for templates if it doesn't.
// We will check if Msg91PayloadBuilder exists in a bit, but for now we'll write the logic.
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $campaignId;
    public $batchSize = 50;

    /**
     * Create a new job instance.
     */
    public function __construct(string $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Execute the job.
     */
    public function handle(TenantResolverService $tenantResolver, OutboundReplyService $replyService)
    {
        // 1. Manually resolve the tenant_id for the campaign to avoid SECURITY ABORT
        $tenantId = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $this->campaignId)
            ->value('tenant_id');

        if (!$tenantId) {
            Log::error("SendCampaignJob: Campaign ID {$this->campaignId} not found or missing tenant_id.");
            return;
        }

        // 2. Bind the tenant context before using BelongsToTenant models
        $tenantResolver->setActiveTenantId((int) $tenantId);

        // 3. Now it is safe to use Eloquent models
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign || $campaign->status === 'completed' || $campaign->status === 'failed' || $campaign->status === 'cancelled') {
            return;
        }

        // Check tenant balance before sending
        if (!\App\Services\TenantBalanceService::hasBalance((int) $tenantId)) {
            Log::warning("SendCampaignJob: Tenant {$tenantId} has zero balance. Halting campaign {$this->campaignId}.");
            $campaign->update([
                'status' => 'failed',
                'failure_reason' => 'Tenant balance depleted. Account suspended. Please recharge to send campaigns.'
            ]);
            return;
        }

        $campaign->update(['status' => 'sending']);

        // Fetch pending recipients for this batch
        $recipients = CampaignRecipient::where('campaign_id', $this->campaignId)
            ->where('status', 'pending')
            ->with('contact')
            ->take($this->batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            $campaign->update(['status' => 'completed']);
            return;
        }
        

        try {
            $outboundNumber = $tenantResolver->getIntegratedNumber($campaign->tenant_id);
            if (empty($tenantResolver->getMsg91AuthKey($campaign->tenant_id))) {
                throw new \Exception('MSG91 Auth Key not configured for tenant');
            }
        } catch (\Exception $e) {
            Log::error("Campaign {$campaign->id} failed to resolve configuration: " . $e->getMessage());
            foreach ($recipients as $recipient) {
                $recipient->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage()
                ]);
                $campaign->increment('failed_count');
            }
            $campaign->update(['status' => 'failed']);
            return;
        }

        foreach ($recipients as $index => $recipient) {
            $contact = $recipient->contact;
            $phone = $contact->phone_number;

            $has24hSession = Cache::has('session:' . $phone);

            // Check if contact has opted out
            if (!$contact->is_subscribed) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign) {
                    $recipient->update(['status' => 'skipped', 'failure_reason' => 'Contact opted out (unsubscribed)']);
                    $campaign->increment('failed_count'); // Incrementing failed_count ensures the campaign finishes
                });
                continue;
            }

            // Fetch or create a conversation for this contact to associate the message
            $conversation = \App\Models\Conversation::firstOrCreate([
                'tenant_id' => $campaign->tenant_id,
                'customer_number' => $phone,
            ]);

            if ($campaign->message_type === 'text') {
                if (!$has24hSession) {
                    // Skip text message outside 24h window (Compliance constraint)
                    \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign) {
                        $recipient->update(['status' => 'skipped_24h', 'failure_reason' => 'Outside 24h window']);
                        $campaign->increment('failed_count');
                    });
                } else {
                    // Send free text within 24h window
                    try {
                        $contentStruct = ['body' => $campaign->text_content];
                        $msg91Payload = \App\Services\Msg91PayloadBuilder::build(
                            $phone,
                            'text',
                            ['text' => $campaign->text_content],
                            $outboundNumber
                        );

                        // Route through standard OutboundReplyService (handles queueing and db storage).
                        // $index is passed as a non-blocking dispatch delay (seconds) so this job
                        // paces sends at ~1/sec without sleep()-blocking the worker (see ISSUE-004).
                        \App\Services\OutboundReplyService::send($conversation->id, $campaign->tenant_id, $contentStruct, $msg91Payload, $index);

                        \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign) {
                            $recipient->update(['status' => 'sent']);
                            $campaign->increment('sent_count');
                        });
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign, $e) {
                            $recipient->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                            $campaign->increment('failed_count');
                        });
                    }
                }
            } elseif ($campaign->message_type === 'template') {
                // Template messages bypass the 24h window — always send.
                try {
                    $templateComponents = [];
                    $variableMap = $campaign->template_variable_map ?? [];

                    if (!empty($variableMap)) {
                        // variableMap is an ordered array of contact field names.
                        // Index 0 → {{1}}, index 1 → {{2}}, etc. — matching MSG91/Meta's
                        // positional convention exactly.
                        $parameters = [];
                        $missingFields = [];

                        foreach ($variableMap as $position => $fieldName) {
                            // Resolve: model property first, then custom_fields JSON bag
                            $value = null;

                            if (isset($contact->$fieldName) && $contact->$fieldName !== null && $contact->$fieldName !== '') {
                                $value = (string) $contact->$fieldName;
                            } elseif (
                                is_array($contact->custom_fields) &&
                                isset($contact->custom_fields[$fieldName]) &&
                                $contact->custom_fields[$fieldName] !== null &&
                                $contact->custom_fields[$fieldName] !== ''
                            ) {
                                $value = (string) $contact->custom_fields[$fieldName];
                            }

                            if ($value === null) {
                                // Hard fail: we know this send would produce a broken
                                // message (empty {{N}}). Record it and skip — do NOT send.
                                $missingFields[] = '{{' . ($position + 1) . '}} (' . $fieldName . ')';
                            } else {
                                $parameters[] = [
                                    'type' => 'text',
                                    'text' => $value,
                                ];
                            }
                        }

                        // If ANY positional variable could not be resolved, refuse to send.
                        // A partial send (some variables resolved, some empty) would produce
                        // a visibly malformed message with no indication of failure.
                        if (!empty($missingFields)) {
                            $reason = 'Missing required template variable(s): ' . implode(', ', $missingFields);
                            \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign, $reason) {
                                $recipient->update([
                                    'status'         => 'failed',
                                    'failure_reason' => $reason,
                                ]);
                                $campaign->increment('failed_count');
                            });
                            Log::warning("Campaign {$campaign->id}: skipped recipient {$recipient->id} — {$reason}");
                            continue; // Move to next recipient
                        }

                        if (!empty($parameters)) {
                            $templateComponents[] = [
                                'type'       => 'body',
                                'parameters' => $parameters,
                            ];
                        }
                    }

                    // Resolve the actual template body text with variable substitution
                    $resolvedBodyText = '📋 Template: ' . $campaign->template_name;
                    try {
                        $templateModel = \App\Models\WhatsappTemplate::where('tenant_id', $campaign->tenant_id)
                            ->where('name', $campaign->template_name)
                            ->first();
                        if ($templateModel && $templateModel->components) {
                            $comps = is_string($templateModel->components) ? json_decode($templateModel->components, true) : $templateModel->components;
                            if (is_array($comps)) {
                                $bodyComp = collect($comps)->first(fn($c) => ($c['type'] ?? '') === 'BODY' || ($c['type'] ?? '') === 'body');
                                if ($bodyComp && !empty($bodyComp['text'])) {
                                    $bodyText = $bodyComp['text'];
                                    // Substitute {{N}} variables with resolved parameter values
                                    if (!empty($parameters)) {
                                        foreach ($parameters as $idx => $param) {
                                            $placeholder = '{{' . ($idx + 1) . '}}';
                                            $bodyText = str_replace($placeholder, $param['text'] ?? '', $bodyText);
                                        }
                                    }
                                    $resolvedBodyText = $bodyText;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Campaign {$campaign->id}: Failed to resolve template body text: " . $e->getMessage());
                    }

                    $contentStruct = [
                        'type'          => 'template',
                        'text'          => $resolvedBodyText,
                        'body'          => $resolvedBodyText,
                        'template_name' => $campaign->template_name,
                    ];

                    $msg91Payload = \App\Services\Msg91PayloadBuilder::build(
                        $phone,
                        'template',
                        [
                            'template_name'       => $campaign->template_name,
                            'template_language'   => $campaign->template_language ?? 'en',
                            'template_components' => $templateComponents,
                        ],
                        $outboundNumber
                    );

                    // $index passed as non-blocking dispatch delay (seconds) — see ISSUE-004.
                    $messageId = \App\Services\OutboundReplyService::send(
                        $conversation->id,
                        $campaign->tenant_id,
                        $contentStruct,
                        $msg91Payload,
                        $index
                    );

                    \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign, $messageId) {
                        $recipient->update([
                            'status' => 'sent',
                            'whatsapp_message_id' => $messageId
                        ]);
                        $campaign->increment('sent_count');
                    });

                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($recipient, $campaign, $e) {
                        $recipient->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                        $campaign->increment('failed_count');
                    });
                }
            }
        }

        // Requeue for the next batch if there are still pending recipients.
        // The 1 msg/sec pacing is now enforced via non-blocking per-recipient dispatch
        // delays above (see ISSUE-004 fix) instead of sleep()-blocking this worker. The
        // next batch is delayed by batchSize seconds so its own 0..batchSize-1 delay
        // window starts only after this batch's sends have finished draining, keeping
        // the ~1 msg/sec ceiling intact across batch boundaries instead of both batches'
        // delay windows overlapping. NOTE: batchSize is currently fixed at 50 — if this is
        // ever raised substantially, revisit whether per-recipient delay() calls are still
        // the right mechanism vs. a queue-level rate limiter (see roadmap issue on
        // centralized/tenant-configurable throughput).
        $remaining = CampaignRecipient::where('campaign_id', $this->campaignId)
            ->where('status', 'pending')
            ->exists();

        if ($remaining) {
            self::dispatch($this->campaignId)->delay(now()->addSeconds($this->batchSize));
        } else {
            $campaign->update(['status' => 'completed']);
        }
    }
}
