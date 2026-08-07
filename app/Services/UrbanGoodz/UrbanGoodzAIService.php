<?php

namespace App\Services\UrbanGoodz;

use App\Contracts\AI\AIProviderInterface;
use App\Services\UrbanGoodz\AI\AIProviderManager;
use App\Services\UrbanGoodz\AI\Persona\Persona;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;

class UrbanGoodzAIService
{
    private AIProviderInterface $provider;

    private PersonaRegistry $personas;

    public function __construct(?AIProviderManager $providers = null, ?PersonaRegistry $personas = null)
    {
        $this->provider = ($providers ?? new AIProviderManager)->resolve();
        $this->personas = $personas ?? new PersonaRegistry;
    }

    public function getBaseUrl(): string
    {
        return match ($this->provider->name()) {
            'gemini' => (string) config('urban_goodz_ai.providers.gemini.base_url'),
            'openrouter' => (string) config('urban_goodz_ai.providers.openrouter.base_url'),
            default => (string) config('openai.base_url', config('urban_goodz_ai.providers.openai.base_url')),
        };
    }

    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function healthCheck(): array
    {
        return $this->provider->healthCheck();
    }

    public function chat(string $systemPrompt, string $userMessage, array $context = []): string
    {
        return $this->chatResult($systemPrompt, $userMessage, $context)['response'];
    }

    public function chatResult(string $systemPrompt, string $userMessage, array $context = []): array
    {
        return $this->provider->chatResult($systemPrompt, $userMessage, $context);
    }

    public function persona(string $key): Persona
    {
        return $this->personas->get($key);
    }

    public function personaForSurface(string $surface): Persona
    {
        return $this->personas->forSurface($surface);
    }

    /**
     * Speak as one of the Urban Goodz personalities.
     *
     * The persona supplies identity, voice, and the platform rule block; the
     * caller supplies only the task. Every surface that routes through here
     * inherits brand voice and the safety block without restating either.
     */
    public function chatAsPersona(string $persona, string $taskPrompt, string $userMessage, array $context = []): string
    {
        return $this->chatResultAsPersona($persona, $taskPrompt, $userMessage, $context)['response'];
    }

    public function chatResultAsPersona(string $persona, string $taskPrompt, string $userMessage, array $context = []): array
    {
        $systemPrompt = $this->personas->get($persona)->systemPrompt($taskPrompt, $context);

        // Context is already grounded into the system prompt; passing it again
        // would duplicate it in the provider payload.
        return $this->provider->chatResult($systemPrompt, $userMessage);
    }

    public function classifyIntent(string $query, array $possibleIntents): ?array
    {
        if (! $this->isConfigured()) {
            return $this->classifyIntentWithRules($query);
        }

        $intentList = collect($possibleIntents)->map(fn ($i) => "- {$i['slug']}: {$i['description']}")->implode("\n");

        $systemPrompt = "You are an intent classifier for Urban Goodz, a delivery and logistics platform.
Classify the customer query into one of these intents:

{$intentList}

Return ONLY a JSON object with: {\"intent\": \"slug\", \"confidence\": 0.0-1.0, \"entities\": {}}
Do not add any explanation. Return only valid JSON.";

        $result = $this->chat($systemPrompt, $query);

        $json = json_decode(trim($result), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['intent'])) {
            $json['intent'] = str_replace('_', '-', (string) $json['intent']);

            return $json;
        }

        return $this->classifyIntentWithRules($query);
    }

    private function classifyIntentWithRules(string $query): array
    {
        $normalized = strtolower(trim($query));
        $entities = [];

        if (preg_match('/\$(\d+(?:\.\d{1,2})?)/', $query, $budgetMatch)) {
            $entities['budget'] = (float) $budgetMatch[1];
            $entities['budget_max'] = (float) $budgetMatch[1];
        }

        if (str_contains($normalized, 'tomorrow')) {
            $entities['preferred_date'] = now()->addDay()->toDateString();
        }

        $rules = [
            'fashion-fit' => ['tailor', 'custom suit', 'alteration', 'measurement', 'fashion fit'],
            'load-board' => ['cargo van load', 'loads from', 'freight load', 'load board', 'haul'],
            'order-anywhere' => ['order a ', 'order pizza', 'order food', 'delivery to'],
            'marketplace-search' => ['search for', 'organic vegetables', 'marketplace'],
            'book-services' => ['mobile mechanic', 'mechanic', 'plumber', 'electrician', 'braider', 'book a service'],
        ];

        if (str_contains($normalized, 'ignore all previous instructions') || str_contains($normalized, 'delete all users')) {
            return ['intent' => 'unknown', 'confidence' => 0.0, 'entities' => []];
        }

        foreach ($rules as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (! str_contains($normalized, $keyword)) {
                    continue;
                }

                if ($intent === 'book-services') {
                    $entities['service_name'] = str_contains($normalized, 'mobile mechanic')
                        ? 'mobile mechanic'
                        : $keyword;
                } elseif ($intent === 'load-board' || $intent === 'marketplace-search') {
                    $entities['search_query'] = $query;
                } elseif ($intent === 'order-anywhere') {
                    $entities['create'] = true;
                    $entities['items'] = $query;
                }

                return ['intent' => $intent, 'confidence' => 0.82, 'entities' => $entities];
            }
        }

        return ['intent' => 'unknown', 'confidence' => 0.0, 'entities' => $entities];
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
        $prompt = 'Analyze this freight load and provide pricing intelligence. Return JSON:
{
  "fair_market_value": number,
  "our_price_assessment": "underpriced"|"fair"|"overpriced",
  "recommendation": string,
  "confidence": 0.0-1.0,
  "market_notes": string
}';

        $result = $this->chat($prompt, json_encode($loadData));
        $json = json_decode(trim($result), true);

        return json_last_error() === JSON_ERROR_NONE ? $json : ['recommendation' => 'Unable to analyze', 'confidence' => 0];
    }

    public function suggestDriverAssignment(array $loadData, array $drivers): array
    {
        $prompt = 'Match the best driver(s) for this load. Consider proximity, equipment, availability, and ratings. Return JSON:
{
  "assignments": [{"driver_id": number, "reason": string, "score": 0.0-1.0}],
  "notes": string
}';

        $context = ['load' => $loadData, 'available_drivers' => $drivers];
        $result = $this->chat($prompt, 'Suggest driver assignments for this load.', $context);
        $json = json_decode(trim($result), true);

        return json_last_error() === JSON_ERROR_NONE ? $json : ['assignments' => [], 'notes' => 'Unable to suggest assignments'];
    }

    public function generateOpsSummary(array $data): string
    {
        $prompt = 'You are an operations analyst for Urban Goodz logistics platform.
Generate a concise daily operations briefing based on this data.
Include: key metrics, alerts, recommended actions, and opportunities.
Be specific with numbers. Format for admin dashboard display.';

        return $this->chat($prompt, "Generate today's operations briefing.", $data);
    }
}
