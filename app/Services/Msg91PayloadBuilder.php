<?php

namespace App\Services;

class Msg91PayloadBuilder
{
    /**
     * Builds a unified payload for a MSG91 WhatsApp outbound request.
     *
     * @param string $contentType The message content type, such as `text`, `image`, or `document`.
     * @param array $data Content-specific values used to populate the payload.
     * @return array The completed MSG91 WhatsApp outbound payload.
     * @throws \InvalidArgumentException If the integrated number is empty.
     */
    public static function build(string $recipientNumber, string $contentType, array $data, string $integratedNumber): array
    {
        if (empty($integratedNumber)) {
            throw new \InvalidArgumentException('Msg91PayloadBuilder: Integrated number is required and cannot be empty.');
        }

        $base = [
            'integrated_number' => $integratedNumber,
            'recipient_number'  => $recipientNumber,
            'content_type'      => $contentType,
        ];

        // The nested payload object MSG91 expects
        $payload = [
            'to'   => $recipientNumber,
            'type' => $contentType,
        ];

        switch ($contentType) {
            case 'image':
                $url = $data['url'] ?? '';
                $payload['image'] = [
                    'link' => $url,
                ];
                if (!empty($data['caption'])) {
                    $payload['image']['caption'] = $data['caption'];
                }
                $base['attachment_url'] = $url;
                $payload['attachment_url'] = $url;
                break;

            case 'audio':
                $url = $data['url'] ?? '';
                $payload['audio'] = [
                    'link' => $url,
                ];
                $base['attachment_url'] = $url;
                $payload['attachment_url'] = $url;
                break;

            case 'document':
                $url = $data['url'] ?? '';
                $payload['document'] = [
                    'link' => $url,
                    'filename' => $data['filename'] ?? 'document.pdf',
                ];
                $base['attachment_url'] = $url;
                $payload['attachment_url'] = $url;
                break;

            case 'template':
                $payload['template'] = [
                    'name' => $data['template_name'] ?? '',
                    'language' => [
                        'code' => $data['template_language'] ?? 'en',
                        'policy' => 'deterministic'
                    ]
                ];
                if (isset($data['template_components']) && is_array($data['template_components'])) {
                    $payload['template']['components'] = $data['template_components'];
                }
                break;

            case 'text':
            default:
                $payload['text'] = [
                    'body' => $data['text'] ?? ''
                ];
                $base['text'] = $data['text'] ?? '';
                break;
        }

        $base['payload'] = $payload;

        return $base;
    }
}
