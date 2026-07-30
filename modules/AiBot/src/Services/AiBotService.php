<?php

namespace Modules\AiBot\Services;

use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiBotService
{
    /**
     * Generate AI response via configured provider (OpenAI / Flowise).
     */
    public function generateResponse(string $userPrompt, TenantSetting $setting): ?string
    {
        if (!$setting->ai_is_active) {
            return null;
        }

        if ($setting->ai_provider === 'flowise') {
            if (empty($setting->flowise_endpoint)) {
                return null; // Fail-closed
            }
            return $this->generateFlowiseResponse($userPrompt, $setting);
        }

        if (empty($setting->openai_api_key)) {
            return null; // Fail-closed
        }
        return $this->generateOpenAiResponse($userPrompt, $setting);
    }

    /**
     * Generate response via OpenAI Chat Completions API with confidence threshold.
     */
    protected function generateOpenAiResponse(string $userPrompt, TenantSetting $setting): ?string
    {
        $model = $setting->ai_model ?: 'gpt-4o-mini';
        $systemPrompt = $setting->ai_system_prompt ?: 'You are a helpful customer support assistant for WhatsApp.';

        // Instruct the model to return JSON with reply and confidence.
        $jsonPrompt = $systemPrompt . "\n\nYou MUST respond in raw JSON format with two keys: 'reply' (string) and 'confidence' (number between 0.0 and 1.0 indicating how certain you are).";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $setting->openai_api_key,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $jsonPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 500,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '{}';
                
                $parsed = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
                    Log::warning("AiBotService: OpenAI returned malformed JSON. Failing closed.", ['content' => $content]);
                    return null;
                }

                $reply = $parsed['reply'] ?? '';
                // If confidence is omitted, default to 0.0 (fail closed) instead of 1.0
                $confidence = isset($parsed['confidence']) ? (float) $parsed['confidence'] : 0.0;

                if ($confidence < $setting->ai_confidence_threshold) {
                    Log::info("AiBotService: OpenAI response confidence ({$confidence}) was below threshold ({$setting->ai_confidence_threshold}). Escalating.");
                    return null;
                }

                if (empty(trim($reply))) {
                    Log::info("AiBotService: OpenAI returned empty reply despite passing confidence. Escalating.");
                    return null;
                }

                return trim($reply);
            }

            Log::error("AiBotService: OpenAI API error ({$response->status()}): " . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error("AiBotService: OpenAI HTTP exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate response via Flowise Prediction API.
     */
    protected function generateFlowiseResponse(string $userPrompt, TenantSetting $setting): ?string
    {
        try {
            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->post($setting->flowise_endpoint, [
                'question' => $userPrompt,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return trim($data['text'] ?? $data['response'] ?? '');
            }

            Log::error("AiBotService: Flowise API error ({$response->status()}): " . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error("AiBotService: Flowise HTTP exception: " . $e->getMessage());
            return null;
        }
    }
}
