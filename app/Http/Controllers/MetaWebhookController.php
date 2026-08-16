<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantSetting;
use App\Services\MetaMessengerService;
use App\Services\TenantResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaWebhookController extends Controller
{
    protected MetaMessengerService $messengerService;

    public function __construct(MetaMessengerService $messengerService)
    {
        $this->messengerService = $messengerService;
    }

    /**
     * Handles Meta Webhook Verification Challenge (GET /webhooks/meta).
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode !== 'subscribe' || empty($token)) {
            return response('Forbidden', 403);
        }

        // Check global fallback verify token
        $globalToken = config('services.meta.webhook_verify_token') ?: env('META_WEBHOOK_VERIFY_TOKEN');
        if ($globalToken && hash_equals((string) $globalToken, (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        // Check against all tenant settings
        $tenantSettings = TenantSetting::whereNotNull('meta_webhook_verify_token')->get();
        foreach ($tenantSettings as $setting) {
            $tenantToken = $setting->meta_webhook_verify_token;
            if ($tenantToken && hash_equals((string) $tenantToken, (string) $token)) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Handles Inbound Meta Webhooks for Facebook Messenger & Instagram (POST /webhooks/meta).
     */
    public function handle(Request $request)
    {
        // 1. Signature Verification: uses raw unparsed request body
        $signatureHeader = $request->header('X-Hub-Signature-256', '');
        $rawContent = $request->getContent();

        if (!$this->verifySignature($rawContent, $signatureHeader)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 403);
        }

        $payload = json_decode($rawContent, true);
        if (!is_array($payload)) {
            return response()->json(['error' => 'Invalid JSON payload.'], 400);
        }

        $objectType = $payload['object'] ?? '';
        if ($objectType !== 'page' && $objectType !== 'instagram') {
            // Meta also sends other object types; acknowledge with 200
            return response()->json(['status' => 'IGNORED_OBJECT_TYPE'], 200);
        }

        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            $pageOrAccountId = $entry['id'] ?? null;

            // Resolve Tenant from Facebook Page ID or Instagram Account ID
            $setting = null;
            if ($pageOrAccountId) {
                $setting = TenantSetting::where('facebook_page_id', $pageOrAccountId)
                    ->orWhere('instagram_account_id', $pageOrAccountId)
                    ->first();
            }

            $tenantId = $setting ? (int) $setting->tenant_id : ((int) TenantSetting::value('tenant_id') ?: 1);

            // Determine channel
            $channel = 'facebook';
            if ($objectType === 'instagram' || ($setting && $setting->instagram_account_id === $pageOrAccountId)) {
                $channel = 'instagram';
            }

            // Extract messaging array (supports standard entry.messaging and instagram entry.changes)
            $messagingEvents = $entry['messaging'] ?? [];
            if (empty($messagingEvents) && isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if (isset($change['value']['messages'])) {
                        $messagingEvents = array_merge($messagingEvents, $change['value']['messages']);
                    }
                }
            }

            foreach ($messagingEvents as $event) {
                // Ignore delivery/read receipts or echo messages from the page itself
                if (isset($event['delivery']) || isset($event['read'])) {
                    continue;
                }
                if (isset($event['message']['is_echo']) && $event['message']['is_echo'] === true) {
                    continue;
                }

                $senderId = $event['sender']['id'] ?? ($event['from']['id'] ?? null);
                if (!$senderId) {
                    continue;
                }

                $messageObj = $event['message'] ?? $event;
                $mid = $messageObj['mid'] ?? ($messageObj['id'] ?? null);
                $text = $messageObj['text'] ?? '';
                $attachments = $messageObj['attachments'] ?? [];

                // 2. Find or Create Conversation
                $conversation = Conversation::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('channel_psid', $senderId)
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'tenant_id'                => $tenantId,
                        'customer_number'          => $senderId,
                        'customer_name'            => ucfirst($channel) . ' User (' . substr($senderId, -4) . ')',
                        'channel'                  => $channel,
                        'channel_psid'             => $senderId,
                        'last_message_at'          => now(),
                        'last_customer_message_at' => now(),
                        'unread_count'             => 1,
                    ]);
                } else {
                    $conversation->update([
                        'channel'                  => $channel,
                        'last_message_at'          => now(),
                        'last_customer_message_at' => now(),
                    ]);
                    $conversation->increment('unread_count');
                }

                // 3. Set 24-Hour Cache Window (channel-scoped key)
                Cache::put("meta_session:{$channel}:{$senderId}", true, now()->addHours(24));

                // 4. Extract Postback / Quick Reply Button Clicks
                $postback = $event['postback'] ?? null;
                $quickReply = $messageObj['quick_reply'] ?? null;
                $buttonText = null;
                if ($postback) {
                    $buttonText = $postback['title'] ?? ($postback['payload'] ?? null);
                } elseif ($quickReply) {
                    $buttonText = $quickReply['payload'] ?? ($messageObj['text'] ?? null);
                }

                if ($buttonText) {
                    $text = $buttonText;
                    $dbContent = [
                        'type'        => 'button_reply',
                        'text'        => $buttonText,
                        'button_text' => $buttonText,
                    ];
                } else {
                    $dbContent = ['text' => $text];
                }

                if (!empty($attachments)) {
                    $dbContent['attachments'] = $attachments;
                }

                $messageRecord = null;
                if ($mid) {
                    $messageRecord = Message::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where(function ($q) use ($mid) {
                            $q->where('meta_uuid', $mid)->orWhere('request_id', $mid);
                        })
                        ->first();
                }

                if (!$messageRecord) {
                    $messageId = Str::uuid()->toString();
                    $messageRecord = Message::create([
                        'id'               => $messageId,
                        'tenant_id'        => $tenantId,
                        'conversation_id'  => $conversation->id,
                        'channel'          => $channel,
                        'direction'        => 'inbound',
                        'status'           => 'received',
                        'content'          => json_encode($dbContent),
                        'meta_uuid'        => $mid,
                        'vendor_timestamp' => now(),
                    ]);
                }

                // 5. Broadcast real-time message received event
                try {
                    broadcast(new MessageReceived($conversation->id, $messageRecord))->toOthers();
                } catch (\Throwable $e) {
                    Log::warning('WebSocket Broadcast Failed for Meta Inbound: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['status' => 'EVENT_RECEIVED'], 200);
    }

    /**
     * Outbound Send for Facebook & Instagram (POST /api/conversations/{id}/meta-message).
     */
    public function send(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $conversation = Conversation::find($id);
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        if (!in_array($conversation->channel, ['facebook', 'instagram'])) {
            return response()->json(['error' => 'Conversation is not a Facebook or Instagram thread'], 422);
        }

        // Channel-neutral Member Role Authorization Check
        if (auth()->check() && method_exists(auth()->user(), 'isMember') && auth()->user()->isMember()) {
            if ($conversation->assigned_user_id !== auth()->id()) {
                abort(403, 'You are not assigned to this conversation.');
            }
        }

        // 24-Hour Messaging Window Enforcement via Cache Key
        $sessionKey = "meta_session:{$conversation->channel}:{$conversation->channel_psid}";
        if (!Cache::has($sessionKey)) {
            return response()->json([
                'error'         => 'The 24-hour customer messaging window has expired. You cannot send free-form messages outside this window.',
                'window_closed' => true,
            ], 422);
        }

        $tenantId = $conversation->tenant_id;
        $setting = TenantSetting::where('tenant_id', $tenantId)->first();
        $pageAccessToken = $setting->meta_access_token ?? null;

        if (empty($pageAccessToken)) {
            return response()->json([
                'error'   => 'Configuration Error',
                'message' => 'Meta Page Access Token is not configured for this tenant.',
            ], 422);
        }

        $messageId = Str::uuid()->toString();
        $dbContent = ['text' => $request->input('content')];

        // 1. Create Queued Outbound Message
        $newMessage = Message::create([
            'id'               => $messageId,
            'tenant_id'        => $tenantId,
            'conversation_id'  => $conversation->id,
            'channel'          => $conversation->channel,
            'direction'        => 'outbound',
            'status'           => 'queued',
            'content'          => json_encode($dbContent),
            'vendor_timestamp' => now(),
        ]);

        // 2. Transmit Outbound via Graph API
        $result = $this->messengerService->sendText(
            $pageAccessToken,
            $conversation->channel_psid,
            $request->input('content')
        );

        if ($result['success']) {
            $newMessage->update([
                'status'     => 'sent',
                'request_id' => $result['message_id'],
            ]);
            $conversation->update([
                'last_message_at' => now(),
            ]);
        } else {
            $newMessage->update([
                'status'         => 'failed',
                'failure_reason' => $result['error'],
            ]);
        }

        // 3. Broadcast Outbound Message
        try {
            broadcast(new MessageReceived($conversation->id, $newMessage))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('WebSocket Broadcast Failed for Meta Outbound: ' . $e->getMessage());
        }

        if (!$result['success']) {
            return response()->json([
                'error'   => 'Meta Delivery Failed',
                'message' => $result['error'],
                'record'  => $newMessage,
            ], 502);
        }

        return response()->json([
            'status'  => 'sent',
            'message' => $newMessage,
        ]);
    }

    /**
     * Verifies the X-Hub-Signature-256 header using raw unparsed request body.
     */
    protected function verifySignature(string $rawBody, string $signatureHeader): bool
    {
        if (empty($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        // Collect all potential app secrets (global env + all tenant settings)
        $secrets = [];
        $globalSecret = config('services.meta.app_secret') ?: env('META_APP_SECRET');
        if ($globalSecret) {
            $secrets[] = $globalSecret;
        }

        $tenantSecrets = TenantSetting::whereNotNull('meta_app_secret')->get();
        foreach ($tenantSecrets as $setting) {
            $secret = $setting->meta_app_secret;
            if ($secret && !in_array($secret, $secrets, true)) {
                $secrets[] = $secret;
            }
        }

        if (empty($secrets)) {
            Log::warning('MetaWebhookController: No Meta App Secrets configured to verify signature.');
            return false;
        }

        foreach ($secrets as $secret) {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
            if (hash_equals($expectedSignature, $signatureHeader)) {
                return true;
            }
        }

        return false;
    }
}
