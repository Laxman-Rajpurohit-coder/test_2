<?php

namespace App\Services;

class Msg91PayloadBuilder
{
    /**
     * Build unified MSG91 WhatsApp Outbound API payload structure.
     */
    public static function build(string $recipientNumber, string $contentType, array $data, ?string $integratedNumber = null): array
    {
        $integratedNumber = $integratedNumber
            ?: config('services.msg91.integrated_number')
            ?: '917425889008';

        $payload = [
            'integrated_number' => $integratedNumber,
            'content_type'      => $contentType,
            'recipient_number'  => $recipientNumber,
        ];

        switch ($contentType) {
            case 'image':
                $payload['url'] = $data['url'] ?? '';
                $payload['caption'] = $data['caption'] ?? ($data['text'] ?? '');
                break;

            case 'document':
                $payload['url'] = $data['url'] ?? '';
                $payload['filename'] = $data['filename'] ?? 'document.pdf';
                break;

            case 'text':
            default:
                $payload['text'] = $data['text'] ?? '';
                break;
        }

        return $payload;
    }
}
