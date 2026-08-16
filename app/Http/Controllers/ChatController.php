<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\Message;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ChatController extends Controller
{
    /**
     * Renders the chat interface with the active tenant's phone numbers.
     *
     * @return \Inertia\Response The rendered chat page.
     */
    /**
     * Renders the chat interface for a specific social channel (whatsapp, facebook, instagram, all).
     *
     * @param string $channel The requested channel inbox.
     * @return \Inertia\Response The rendered chat page.
     */
    public function view(Request $request, string $channel = 'whatsapp')
    {
        $validChannels = ['whatsapp', 'facebook', 'instagram', 'all'];
        if (!in_array($channel, $validChannels, true)) {
            $channel = 'whatsapp';
        }

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        $tenantNumbers = \Illuminate\Support\Facades\DB::table('tenant_numbers')
            ->where('tenant_id', $tenantId)
            ->get();

        $setting = \App\Models\TenantSetting::where('tenant_id', $tenantId)->first();

        $approvedTemplates = \App\Models\WhatsappTemplate::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->get();

        // Calculate channel stats
        $counts = [
            'all'       => Conversation::count(),
            'whatsapp'  => Conversation::where('channel', 'whatsapp')->orWhereNull('channel')->count(),
            'facebook'  => Conversation::where('channel', 'facebook')->count(),
            'instagram' => Conversation::where('channel', 'instagram')->count(),
            'whatsapp_unread'  => (int) Conversation::where(fn($q) => $q->where('channel', 'whatsapp')->orWhereNull('channel'))->sum('unread_count'),
            'facebook_unread'  => (int) Conversation::where('channel', 'facebook')->sum('unread_count'),
            'instagram_unread' => (int) Conversation::where('channel', 'instagram')->sum('unread_count'),
        ];

        return Inertia::render('Chat/Index', [
            'currentChannel'    => $channel,
            'channelCounts'     => $counts,
            'tenantNumbers'     => $tenantNumbers,
            'approvedTemplates' => $approvedTemplates,
            'socialSettings'    => [
                'has_facebook'  => !empty($setting?->facebook_page_id),
                'has_instagram' => !empty($setting?->instagram_account_id),
                'facebook_page_id' => $setting?->facebook_page_id,
                'instagram_account_id' => $setting?->instagram_account_id,
            ],
        ]);
    }

    /**
     * Retrieves conversations ordered by the most recent message.
     *
     * @param Request $request Request data that may include a tenant number filter.
     * @return \Illuminate\Http\JsonResponse The matching conversations as JSON.
     */
    public function index(Request $request)
    {
        $query = Conversation::orderBy('last_message_at', 'desc')
            ->with(['messages' => function ($q) {
                $q->orderBy('vendor_timestamp', 'desc')->limit(1);
            }]);

        if ($request->filled('tenant_number_id')) {
            $query->where('tenant_number_id', $request->input('tenant_number_id'));
        }

        if ($request->filled('channel') && in_array($request->input('channel'), ['whatsapp', 'facebook', 'instagram'])) {
            $query->where('channel', $request->input('channel'));
        }

        if (auth()->check() && method_exists(auth()->user(), 'isMember') && auth()->user()->isMember()) {
            $query->where(function ($q) {
                $q->where('conversations.assigned_user_id', auth()->id())
                  ->orWhereExists(function ($sub) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('contacts')
                          ->whereColumn('contacts.phone_number', 'conversations.customer_number')
                          ->whereColumn('contacts.tenant_id', 'conversations.tenant_id')
                          ->where('contacts.assigned_user_id', auth()->id());
                  });
            });
        }

        $conversations = $query->get();
            
        return response()->json($conversations);
    }

    /**
     * Marks a conversation as read by resetting the unread_count to 0.
     */
    public function markAsRead($id)
    {
        $conversation = Conversation::find($id);
        if ($conversation && $conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();
        }
        return response()->json(['status' => 'success']);
    }

    // API: Fetch messages for a conversation (Cursor Pagination, Scoped via BelongsToTenant)
    public function show($id, Request $request)
    {
        $limit = $request->query('limit', 50);
        $cursor = $request->query('cursor');

        $query = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($cursor) {
            $decoded = explode('|', base64_decode($cursor));
            if (count($decoded) === 2) {
                [$cursorTimestamp, $cursorId] = $decoded;
                $query->whereRaw('(created_at, id) < (?::timestamp, ?::uuid)', [$cursorTimestamp, $cursorId]);
            }
        }

        $messages = $query->limit($limit + 1)->get();

        $hasNextPage = $messages->count() > $limit;
        $returnData = $hasNextPage ? $messages->slice(0, $limit) : $messages;

        $nextCursor = null;
        if ($hasNextPage) {
            $lastRecord = $returnData->last();
            $nextCursor = base64_encode($lastRecord->created_at . '|' . $lastRecord->id);
        }

        return response()->json([
            'data'        => $returnData->reverse()->values(),
            'next_cursor' => $nextCursor,
            'has_more'    => $hasNextPage
        ]);
    }

    /**
     * Queues an outbound text or template message for a conversation.
     *
     * @param Request $request Validated message content and type.
     * @param mixed $id The conversation identifier.
     * @return \Illuminate\Http\JsonResponse The queued message or an error response.
     */
    public function store(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
            'type'    => 'required|string|in:text,template,interactive',
            'template_name' => 'required_if:type,template|string',
            'template_language' => 'nullable|string',
            'template_components' => 'nullable|array',
            'interactive_type' => 'required_if:type,interactive|in:button,list',
            'buttons' => 'required_if:interactive_type,button|array|max:3',
            'sections' => 'required_if:interactive_type,list|array|max:10',
            'list_button_text' => 'required_if:interactive_type,list|string',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
        ]);

        $conversation = Conversation::find($id);
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Member role check: channel-neutral — uses conversations.assigned_user_id
        if (method_exists(auth()->user(), 'isMember') && auth()->user()->isMember()) {
            if ($conversation->assigned_user_id !== auth()->id()) {
                abort(403, 'You are not assigned to this conversation.');
            }
        }


        // Enforce 24-Hour Session Guard Rail for free-text messages
        if ($request->input('type') === 'text' && !Cache::has('session:' . $conversation->customer_number)) {
            return response()->json([
                'error' => 'The 24-hour customer session window has expired. You must receive an inbound message or send a WhatsApp Template message.'
            ], 422);
        }

        $messageId = Str::uuid()->toString();
        
        $dbContent = ['text' => $request->input('content')];
        if ($request->input('type') === 'template') {
            $dbContent = [
                'type' => 'template',
                'template_name' => $request->input('template_name'),
                'template_language' => $request->input('template_language', 'en'),
                'text' => $request->input('content'),
            ];
        } elseif ($request->input('type') === 'interactive') {
            $dbContent = [
                'type' => 'interactive',
                'interactive_type' => $request->input('interactive_type'),
                'text' => $request->input('content'),
                'header_text' => $request->input('header_text'),
                'footer_text' => $request->input('footer_text'),
            ];
            if ($request->input('interactive_type') === 'button') {
                $dbContent['buttons'] = $request->input('buttons');
            } else {
                $dbContent['list_button_text'] = $request->input('list_button_text');
                $dbContent['sections'] = $request->input('sections');
            }
        }

        // 1. Save to database as "queued" via Eloquent (Auto-injects tenant_id)
        $newMessage = Message::create([
            'id'               => $messageId,
            'conversation_id'  => $id,
            'direction'        => 'outbound',
            'status'           => 'queued',
            'content'          => json_encode($dbContent),
            'vendor_timestamp' => now(),
        ]);

        // 2. Dispatch Background Job to MSG91 using real tenant number
        try {
            $integratedNumber = app(\App\Services\TenantResolverService::class)->getIntegratedNumber($conversation->tenant_id);
            if (empty(app(\App\Services\TenantResolverService::class)->getMsg91AuthKey($conversation->tenant_id))) {
                $newMessage->update(['status' => 'failed', 'failure_reason' => 'MSG91 Auth Key not configured for tenant']);
                return response()->json([
                    'error' => 'Configuration Error',
                    'message' => 'WhatsApp API key not configured — set it in Tenant API Settings before sending messages'
                ], 422);
            }
        } catch (\Exception $e) {
            $newMessage->update(['status' => 'failed', 'failure_reason' => 'No integrated WhatsApp number found for this tenant.']);
            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'No integrated WhatsApp number found for this tenant. Cannot send messages.'
            ], 422);
        }
        
        $msg91Data = $request->all();
        if (!isset($msg91Data['text'])) {
            $msg91Data['text'] = $request->input('content');
        }

        $msg91Payload = \App\Services\Msg91PayloadBuilder::build(
            $conversation->customer_number,
            $request->input('type'),
            $msg91Data,
            $integratedNumber
        );

        \App\Jobs\SendMsg91Message::dispatch($messageId, $msg91Payload, (int)$id);
        
        try {
            broadcast(new MessageReceived($id, $newMessage))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('WebSocket Broadcast Failed (Non-blocking): ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'queued', 
            'message' => $newMessage
        ]);
    }

    /**
     * Uploads and queues an outbound image or audio message for a conversation.
     *
     * @param Request $request The request containing the media file, media type, and optional caption.
     * @param mixed $id The conversation identifier.
     * @return \Illuminate\Http\JsonResponse The queued message or an error response.
     */
    public function storeMedia(Request $request, $id)
    {
        $maxKB = 16384; // Default max 16MB
        if ($request->input('type') === 'image') $maxKB = 5120; // 5MB limit for images
        if ($request->input('type') === 'video') $maxKB = 16384; // 16MB limit for videos

        $mimes = 'jpeg,jpg,png,gif,webp';
        if ($request->input('type') === 'audio') {
            $mimes = 'mp3,wav,ogg,m4a,webm';
        } elseif ($request->input('type') === 'document') {
            $mimes = 'pdf,doc,docx,txt,csv,xls,xlsx';
        } elseif ($request->input('type') === 'video') {
            $mimes = 'mp4,mov,avi';
        }

        $request->validate([
            'file'    => "required|file|mimes:{$mimes}|max:{$maxKB}",
            'type'    => 'required|string|in:image,audio,document,video',
            'caption' => 'nullable|string',
        ]);

        $conversation = Conversation::find($id);
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Member role check: channel-neutral — uses conversations.assigned_user_id
        if (method_exists(auth()->user(), 'isMember') && auth()->user()->isMember()) {
            if ($conversation->assigned_user_id !== auth()->id()) {
                abort(403, 'You are not assigned to this conversation.');
            }
        }


        // Enforce 24-Hour Session Guard Rail
        if (!Cache::has('session:' . $conversation->customer_number)) {
            return response()->json([
                'error' => 'The 24-hour customer session window has expired. You must receive an inbound message first.'
            ], 422);
        }

        $file = $request->file('file');
        
        if ($request->input('type') === 'audio') {
            $rawFilename = Str::uuid()->toString() . '_raw.' . ($file->getClientOriginalExtension() ?: 'webm');
            $rawPath = $file->storeAs('media', $rawFilename, 'public');
            $fullRawPath = storage_path('app/public/' . $rawPath);

            $oggFilename = Str::uuid()->toString() . '.ogg';
            $fullOggPath = storage_path('app/public/media/' . $oggFilename);

            // FFmpeg transcode to native WhatsApp Opus OGG Voice Note format (Mono 16kHz Opus)
            $cmd = "ffmpeg -y -i " . escapeshellarg($fullRawPath) . " -c:a libopus -b:a 32k -ac 1 -ar 16000 " . escapeshellarg($fullOggPath) . " 2>&1";
            exec($cmd, $ffmpegOutput, $returnCode);

            if ($returnCode === 0 && file_exists($fullOggPath)) {
                @unlink($fullRawPath);
                $path = 'media/' . $oggFilename;
            } else {
                Log::warning('FFmpeg voice note transcode failed', [
                    'output' => implode("\n", (array)$ffmpegOutput),
                    'code'   => $returnCode
                ]);
                $path = $rawPath;
            }
        } else {
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::uuid()->toString() . '.' . $extension;
            $path = $file->storeAs('media', $filename, 'public');
        }

        $baseUrl = config('app.url');
        $mediaUrl = rtrim($baseUrl, '/') . '/storage/' . $path;

        $messageId = Str::uuid()->toString();
        $mediaType = $request->input('type');
        
        $contentPayload = [
            'type'    => $mediaType,
            'url'     => $mediaUrl,
            'caption' => $request->input('caption', ''),
        ];

        if ($mediaType === 'document' || $mediaType === 'video') {
            $contentPayload['filename'] = $file->getClientOriginalName();
        }

        $newMessage = Message::create([
            'id'               => $messageId,
            'conversation_id'  => $id,
            'direction'        => 'outbound',
            'status'           => 'queued',
            'content'          => json_encode($contentPayload),
            'vendor_timestamp' => now(),
        ]);

        try {
            $integratedNumber = app(\App\Services\TenantResolverService::class)->getIntegratedNumber($conversation->tenant_id);
            if (empty(app(\App\Services\TenantResolverService::class)->getMsg91AuthKey($conversation->tenant_id))) {
                // We created the message above, but we must fail it immediately.
                $newMessage->update(['status' => 'failed', 'failure_reason' => 'MSG91 Auth Key not configured for tenant']);
                return response()->json([
                    'error' => 'Configuration Error',
                    'message' => 'WhatsApp API key not configured — set it in Tenant API Settings before sending messages'
                ], 422);
            }
        } catch (\Exception $e) {
            $newMessage->update(['status' => 'failed', 'failure_reason' => 'No integrated WhatsApp number found for this tenant.']);
            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'No integrated WhatsApp number found for this tenant. Cannot send messages.'
            ], 422);
        }

        $msg91Payload = [
            'integrated_number' => $integratedNumber,
            'recipient_number'  => $conversation->customer_number,
            'content_type'      => $mediaType,
            'attachment_url'    => $mediaUrl,
            'payload'           => [
                'to'             => $conversation->customer_number,
                'type'           => $mediaType,
                'attachment_url' => $mediaUrl,
                $mediaType       => [
                    'link'           => $mediaUrl,
                    'attachment_url' => $mediaUrl,
                ]
            ]
        ];

        if ($mediaType === 'image' && $request->filled('caption')) {
            $msg91Payload['payload']['image']['caption'] = $request->input('caption');
        }

        \App\Jobs\SendMsg91Message::dispatch($messageId, $msg91Payload, (int)$id);

        try {
            broadcast(new MessageReceived($id, $newMessage))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('WebSocket Broadcast Failed (Non-blocking): ' . $e->getMessage());
        }

        return response()->json(['status' => 'queued', 'message' => $newMessage]);
    }

    /**
     * Delete multiple conversations and their associated messages.
     *
     * @param Request $request Validated conversation_ids.
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'conversation_ids' => 'required|array',
            'conversation_ids.*' => 'required|exists:conversations,id'
        ]);

        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        $conversations = Conversation::where('tenant_id', $tenantId)
            ->whereIn('id', $request->input('conversation_ids'))
            ->get();

        foreach ($conversations as $conversation) {
            // Delete child messages
            \App\Models\Message::where('conversation_id', $conversation->id)->delete();
            // Delete tasks/reminders
            \App\Models\CustomerTask::where('conversation_id', $conversation->id)->delete();
            // Delete conversation itself
            $conversation->delete();
        }

        return response()->json(['success' => true, 'message' => 'Conversations deleted successfully.']);
    }
}
