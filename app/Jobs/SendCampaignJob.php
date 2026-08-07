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
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign || $campaign->status === 'completed' || $campaign->status === 'failed' || $campaign->status === 'cancelled') {
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

        // Must bind the tenant context for BelongsToTenant and Resolver
        $tenantResolver->setActiveTenantId($campaign->tenant_id);
        
        try {
            $outboundNumber = $tenantResolver->getIntegratedNumber($campaign->tenant_id);
        } catch (\Exception $e) {
            Log::error("Campaign {$campaign->id} failed to resolve outbound number: " . $e->getMessage());
            $campaign->update(['status' => 'failed']);
            return;
        }

        foreach ($recipients as $recipient) {
            $contact = $recipient->contact;
            $phone = $contact->phone_number;

            $has24hSession = Cache::has('session:' . $phone);

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

                        // Route through standard OutboundReplyService (handles queueing and db storage)
                        \App\Services\OutboundReplyService::send($conversation->id, $campaign->tenant_id, $contentStruct, $msg91Payload);

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

                    $contentStruct = [
                        'body'          => 'Template: ' . $campaign->template_name,
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

                    \App\Services\OutboundReplyService::send(
                        $conversation->id,
                        $campaign->tenant_id,
                        $contentStruct,
                        $msg91Payload
                    );

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


            // Enforce 1 msg/sec rate limit per tenant
            sleep(1);
        }

        // Requeue for the next batch if there are still pending recipients
        $remaining = CampaignRecipient::where('campaign_id', $this->campaignId)
            ->where('status', 'pending')
            ->exists();

        if ($remaining) {
            self::dispatch($this->campaignId);
        } else {
            $campaign->update(['status' => 'completed']);
        }
    }
}
