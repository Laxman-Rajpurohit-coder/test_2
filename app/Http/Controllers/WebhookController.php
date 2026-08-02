<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessMsg91Webhook;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        // Gated Debug Logging to inspect real MSG91 payload shapes safely
        if (config('app.debug')) {
            Log::info('MSG91 Webhook Incoming Payload:', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'payload' => $payload
            ]);
        }

        // Immediately dispatch to Redis Queue to avoid MSG91 8-second timeout penalty
        ProcessMsg91Webhook::dispatch($payload);

        return response()->json(['status' => 'success', 'message' => 'Queued'], 200);
    }
}
