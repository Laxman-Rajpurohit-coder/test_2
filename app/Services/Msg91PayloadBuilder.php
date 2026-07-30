<?php

namespace App\Services;

class Msg91PayloadBuilder
{
    /**
     * Build unified MSG91 WhatsApp Outbound API payload structure.
     */
    public static function build(string $recipientNumber, string $contentType, array $data, string $integratedNumber): array
    {
        if (empty($integratedNumber)) {
            throw new \InvalidArgumentException('Msg91PayloadBuilder: Integrated number is required and cannot be empty.');
        }

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
