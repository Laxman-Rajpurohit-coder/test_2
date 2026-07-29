<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ChatController extends Controller
{
    // Render the main React Chat Interface
    public function view()
    {
        return Inertia::render('Chat/Index');
    }

    // API: Fetch list of conversations (Scoped via BelongsToTenant)
    public function index()
    {
        $conversations = Conversation::orderBy('last_message_at', 'desc')->get();
            
        return response()->json($conversations);
    }

    // API: Fetch messages for a conversation (Cursor Pagination, Scoped via BelongsToTenant)
    public function show($id, Request $request)
    {
        $limit = $request->query('limit', 50);
        $cursor = $request->query('cursor');

        $query = WhatsappMessage::where('conversation_id', $id)
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

    // API: Send an outbound message
    public function store(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
            'type'    => 'required|string|in:text,template',
        ]);

        $conversation = Conversation::find($id);
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Enforce 24-Hour Session Guard Rail for free-text messages
        if ($request->input('type') === 'text' && !Cache::has('session:' . $conversation->customer_number)) {
            return response()->json([
                'error' => 'The 24-hour customer session window has expired. You must receive an inbound message or send a WhatsApp Template message.'
            ], 422);
        }

        $messageId = Str::uuid()->toString();
        $payload = ['text' => $request->input('content')];

        // 1. Save to database as "queued" via Eloquent (Auto-injects tenant_id)
        $newMessage = WhatsappMessage::create([
            'id'               => $messageId,
            'conversation_id'  => $id,
            'direction'        => 'outbound',
            'status'           => 'queued',
            'content'          => json_encode($payload),
            'vendor_timestamp' => now(),
        ]);

        // 2. Dispatch Background Job to MSG91 using config()
        $integratedNumber = config('services.msg91.integrated_number');
        
        $msg91Payload = [
            'integrated_number' => $integratedNumber,
            'recipient_number'  => $conversation->customer_number,
            'text'              => $payload['text'],
            'content_type'      => 'text',
            'payload'           => [
                'to'   => $conversation->customer_number,
                'type' => 'text',
                'text' => [
                    'body' => $payload['text']
                ]
            ]
        ];

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

    // API: Upload & send outbound media (Photos/Images & Voice Notes/Audio)
    public function storeMedia(Request $request, $id)
    {
        $maxKB = $request->input('type') === 'image' ? 5120 : 16384;
        $mimes = $request->input('type') === 'image' 
            ? 'jpeg,jpg,png,gif,webp' 
            : 'mp3,wav,ogg,m4a,webm';

        $request->validate([
            'file'    => "required|file|mimes:{$mimes}|max:{$maxKB}",
            'type'    => 'required|string|in:image,audio',
            'caption' => 'nullable|string',
        ]);

        $conversation = Conversation::find($id);
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
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

        $newMessage = WhatsappMessage::create([
            'id'               => $messageId,
            'conversation_id'  => $id,
            'direction'        => 'outbound',
            'status'           => 'queued',
            'content'          => json_encode($contentPayload),
            'vendor_timestamp' => now(),
        ]);

        $integratedNumber = config('services.msg91.integrated_number');

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
}
