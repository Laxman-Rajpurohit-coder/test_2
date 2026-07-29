<?php

namespace Modules\AiBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AiBot\Models\AiBotSetting;

class AiBotService
{
    /**
     * Generate AI response via configured provider (OpenAI / Flowise).
     */
    public function generateResponse(string $userPrompt, AiBotSetting $setting): ?string
    {
        if (!$setting->is_active || empty($setting->api_key)) {
            return null;
        }

        if ($setting->provider === 'flowise') {
            return $this->generateFlowiseResponse($userPrompt, $setting);
        }

        return $this->generateOpenAiResponse($userPrompt, $setting);
    }

    /**
     * Generate response via OpenAI Chat Completions API.
     */
    protected function generateOpenAiResponse(string $userPrompt, AiBotSetting $setting): ?string
    {
        $model = $setting->model_or_chatflow_id ?: 'gpt-4o-mini';
        $systemPrompt = $setting->system_prompt ?: 'You are a helpful customer support assistant for WhatsApp.';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $setting->api_key,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 500,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return trim($data['choices'][0]['message']['content'] ?? '');
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
    protected function generateFlowiseResponse(string $userPrompt, AiBotSetting $setting): ?string
    {
        $chatflowId = $setting->model_or_chatflow_id;
        $baseUrl = config('services.flowise.url', 'https://flowise.example.com');
        $endpoint = "{$baseUrl}/api/v1/prediction/{$chatflowId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $setting->api_key,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->post($endpoint, [
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
