<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrbanGoodzAIService
{
    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key', env('OPENAI_API_KEY', ''));
        $this->model = config('urban_goodz.ai_model', 'gpt-4o');
        $this->temperature = (float) config('urban_goodz.ai_temperature', 0.4);
        $this->maxTokens = (int) config('urban_goodz.ai_max_tokens', 1500);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && strlen($this->apiKey) > 10;
    }

    public function chat(string $systemPrompt, string $userMessage, array $context = []): string
    {
        if (!$this->isConfigured()) {
            Log::warning('UrbanGoodz AI: OpenAI API key not configured');
            return 'AI service is not yet configured. Please set OPENAI_API_KEY in .env';
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if (!empty($context)) {
            $contextMessage = "Here is relevant data to help you answer:\n\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $messages[] = ['role' => 'system', 'content' => $contextMessage];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                return $body['choices'][0]['message']['content'] ?? 'I could not generate a response.';
            }

            Log::error('UrbanGoodz AI: OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return 'I apologize — I encountered an error processing your request. A team member will follow up shortly.';

        } catch (\Exception $e) {
            Log::error('UrbanGoodz AI: Exception calling OpenAI', ['error' => $e->getMessage()]);
            return 'I apologize — I am temporarily unavailable. A team member will follow up shortly.';
        }
    }

    public function classifyIntent(string $query, array $possibleIntents): ?array
    {
        $intentList = collect($possibleIntents)->map(fn($i) => "- {$i['slug']}: {$i['description']}")->implode("\n");

        $systemPrompt = "You are an intent classifier for Urban Goodz, a delivery and logistics platform.
Classify the customer query into one of these intents:

{$intentList}

Return ONLY a JSON object with: {\"intent\": \"slug\", \"confidence\": 0.0-1.0, \"entities\": {}}
Do not add any explanation. Return only valid JSON.";

        $result = $this->chat($systemPrompt, $query);

        $json = json_decode(trim($result), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['intent'])) {
            return $json;
        }

        return ['intent' => 'unknown', 'confidence' => 0.0, 'entities' => []];
    }

    public function summarize(string $text, int $maxWords = 50): string
    {
        return $this->chat(
            "Summarize the following in {$maxWords} words or less. Be concise and factual.",
            $text
        );
    }

    public function analyzeLoadPricing(array $loadData): array
    {
        $prompt = "Analyze this freight load and provide pricing intelligence. Return JSON:
{
  \"fair_market_value\": number,
  \"our_price_assessment\": \"underpriced\"|\"fair\"|\"overpriced\",
  \"recommendation\": string,
  \"confidence\": 0.0-1.0,
  \"market_notes\": string
}";

        $result = $this->chat($prompt, json_encode($loadData));
        $json = json_decode(trim($result), true);
        return json_last_error() === JSON_ERROR_NONE ? $json : ['recommendation' => 'Unable to analyze', 'confidence' => 0];
    }

    public function suggestDriverAssignment(array $loadData, array $drivers): array
    {
        $prompt = "Match the best driver(s) for this load. Consider proximity, equipment, availability, and ratings. Return JSON:
{
  \"assignments\": [{\"driver_id\": number, \"reason\": string, \"score\": 0.0-1.0}],
  \"notes\": string
}";

        $context = ['load' => $loadData, 'available_drivers' => $drivers];
        $result = $this->chat($prompt, "Suggest driver assignments for this load.", $context);
        $json = json_decode(trim($result), true);
        return json_last_error() === JSON_ERROR_NONE ? $json : ['assignments' => [], 'notes' => 'Unable to suggest assignments'];
    }

    public function generateOpsSummary(array $data): string
    {
        $prompt = "You are an operations analyst for Urban Goodz logistics platform.
Generate a concise daily operations briefing based on this data.
Include: key metrics, alerts, recommended actions, and opportunities.
Be specific with numbers. Format for admin dashboard display.";

        return $this->chat($prompt, "Generate today's operations briefing.", $data);
    }
}
