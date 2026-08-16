<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaMessengerService
{
    /**
     * Sends an outbound text message to a Facebook Messenger or Instagram direct message recipient.
     *
     * @param string $pageAccessToken The Page Access Token or Instagram Access Token.
     * @param string $recipientPsid The recipient's Page-Scoped User ID (PSID) or IGSID.
     * @param string $text The message text content.
     * @return array Result containing success flag, meta message_id, and any error details.
     */
    public function sendText(string $pageAccessToken, string $recipientPsid, string $text): array
    {
        try {
            $endpoint = 'https://graph.facebook.com/v19.0/me/messages';

            $payload = [
                'recipient' => [
                    'id' => $recipientPsid,
                ],
                'message' => [
                    'text' => $text,
                ],
                'messaging_type' => 'RESPONSE',
            ];

            $response = Http::timeout(10)
                ->withToken($pageAccessToken)
                ->post($endpoint, $payload);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                return [
                    'success'      => true,
                    'message_id'   => $data['message_id'] ?? null,
                    'recipient_id' => $data['recipient_id'] ?? $recipientPsid,
                    'status_code'  => $response->status(),
                    'error'        => null,
                ];
            }

            $errorMessage = $data['error']['message'] ?? $response->body() ?: 'Unknown Graph API error';
            Log::warning('MetaMessengerService: Graph API send failed', [
                'status'    => $response->status(),
                'error'     => $errorMessage,
                'recipient' => $recipientPsid,
            ]);

            return [
                'success'     => false,
                'message_id'  => null,
                'status_code' => $response->status(),
                'error'       => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaMessengerService: Exception occurred while sending message', [
                'message'   => $e->getMessage(),
                'recipient' => $recipientPsid,
            ]);

            return [
                'success'     => false,
                'message_id'  => null,
                'status_code' => 500,
                'error'       => $e->getMessage(),
            ];
        }
    }
}
