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

        try {
            // Process webhook synchronously for immediate DB ingestion and real-time bot response
            ProcessMsg91Webhook::dispatchSync($payload);
            return response()->json(['status' => 'success', 'message' => 'Processed'], 200);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'SECURITY ABORT')) {
                Log::warning('WebhookController: ' . $e->getMessage() . ' Acknowledging 200 to prevent retry storm.');
                return response()->json(['status' => 'rejected', 'message' => 'Unmapped integrated number'], 200);
            }

            Log::error('WebhookController: Unexpected error processing webhook: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
