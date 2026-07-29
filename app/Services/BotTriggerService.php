<?php

namespace App\Services;

use App\Models\BotTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotTriggerService
{
    /**
     * Match incoming message text against active bot triggers respecting match_type and priority.
     */
    public function matchTrigger(string $messageText): ?BotTrigger
    {
        $cleanText = trim(mb_strtolower($messageText));
        if (empty($cleanText)) {
            return null;
        }

        // Fetch active rules ordered by highest priority first (BelongsToTenant scoped)
        $triggers = BotTrigger::query()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($triggers as $trigger) {
            $keyword = trim(mb_strtolower($trigger->keyword));
            if (empty($keyword)) {
                continue;
            }

            $isMatch = false;
            switch ($trigger->match_type) {
                case 'exact':
                    $isMatch = ($cleanText === $keyword);
                    break;
                case 'starts_with':
                    $isMatch = str_starts_with($cleanText, $keyword);
                    break;
                case 'contains':
                default:
                    $isMatch = str_contains($cleanText, $keyword);
                    break;
            }

            if ($isMatch) {
                return $trigger; // Highest-priority winning match
            }
        }

        return null;
    }

    /**
     * Evaluate incoming message text against active bot triggers.
     */
    public function matchAndBuildResponse(string $messageText, string $customerNumber, ?string $customerName = null): ?array
    {
        $matchedTrigger = $this->matchTrigger($messageText);

        if (!$matchedTrigger) {
            return null;
        }

        // Parse response payload safely
        $rawPayload = is_string($matchedTrigger->response_payload)
            ? json_decode($matchedTrigger->response_payload, true)
            : (array) $matchedTrigger->response_payload;

        if (!$rawPayload) {
            Log::warning("BotTriggerService: Trigger ID {$matchedTrigger->id} has invalid JSON payload.");
            return null;
        }

        // Perform safe variable substitutions with fallbacks
        $resolvedName = !empty($customerName) ? $customerName : 'Customer';
        
        $substitutions = [
            '{customer_name}' => $resolvedName,
            '{phone_number}'  => $customerNumber,
        ];

        $data = [];
        if (!empty($rawPayload['text'])) {
            $data['text'] = strtr($rawPayload['text'], $substitutions);
        }
        if (!empty($rawPayload['url'])) {
            $data['url'] = $rawPayload['url'];
        }
        if (!empty($rawPayload['caption'])) {
            $data['caption'] = strtr($rawPayload['caption'], $substitutions);
        }
        if (!empty($rawPayload['filename'])) {
            $data['filename'] = $rawPayload['filename'];
        }

        // Build MSG91 Outbound Payload using canonical Msg91PayloadBuilder
        $msg91Payload = Msg91PayloadBuilder::build(
            $customerNumber,
            $matchedTrigger->response_type,
            $data
        );

        return [
            'trigger_id'     => $matchedTrigger->id,
            'keyword'        => $matchedTrigger->keyword,
            'msg91_payload'  => $msg91Payload,
            'content_struct' => [
                'type' => $matchedTrigger->response_type,
                'text' => $msg91Payload['text'] ?? $msg91Payload['caption'] ?? '🤖 Automated Response',
                'url'  => $msg91Payload['url'] ?? null,
            ],
        ];
    }
}
