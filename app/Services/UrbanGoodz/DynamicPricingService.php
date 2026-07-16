<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Log;

class DynamicPricingService
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    /**
     * AI-powered dynamic price calculation.
     * Uses demand signals, competition, and market context.
     */
    public function calculateDynamicPrice(array $params): array
    {
        $prompt = $this->buildPricingPrompt($params);

        try {
            $response = $this->ai->chat($prompt, [
                'model' => config('urban_goodz.ai_model', 'gpt-4o'),
                'temperature' => 0.3,
                'max_tokens' => 1000,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = is_string($response) ? json_decode($response, true) : $response;

            if (! is_array($parsed)) {
                return $this->fallbackPricing($params);
            }

            $basePrice = (float) ($params['base_price'] ?? 0);
            $multiplier = (float) ($parsed['dynamic_multiplier'] ?? 1.0);
            $multiplier = max(0.5, min(3.0, $multiplier)); // Clamp to 0.5x–3.0x

            $finalPrice = round($basePrice * $multiplier, 2);

            return [
                'base_price' => $basePrice,
                'dynamic_multiplier' => $multiplier,
                'final_price' => $finalPrice,
                'factors' => $parsed['factors'] ?? [],
                'recommendation' => $parsed['recommendation'] ?? null,
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.5),
                'source' => 'ai_pricing',
            ];
        } catch (\Exception $e) {
            Log::warning('DynamicPricingService AI calculation failed', ['error' => $e->getMessage()]);
            return $this->fallbackPricing($params);
        }
    }

    /**
     * AI-powered price simulation.
     */
    public function simulatePriceChange(array $params): array
    {
        $prompt = "Simulate the impact of a price change. Consider:\n"
            . "- Current price and proposed price\n"
            . "- Demand elasticity for this category\n"
            . "- Competitor pricing\n"
            . "- Seasonality\n"
            . "- Customer sensitivity\n"
            . "- Revenue impact estimate\n\n"
            . "Data: " . json_encode($params) . "\n\n"
            . "Return JSON: {\"projected_demand_change_percent\": ..., \"projected_revenue_change_percent\": ..., \"risk_level\": \"low|medium|high\", \"factors\": [...], \"recommendation\": \"...\", \"confidence\": 0-1}";

        try {
            $response = $this->ai->chat($prompt, [
                'temperature' => 0.3,
                'max_tokens' => 800,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = is_string($response) ? json_decode($response, true) : $response;

            return [
                'projected_demand_change_percent' => (float) ($parsed['projected_demand_change_percent'] ?? 0),
                'projected_revenue_change_percent' => (float) ($parsed['projected_revenue_change_percent'] ?? 0),
                'risk_level' => $parsed['risk_level'] ?? 'medium',
                'factors' => $parsed['factors'] ?? [],
                'recommendation' => $parsed['recommendation'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.5),
                'source' => 'ai_simulation',
            ];
        } catch (\Exception $e) {
            Log::warning('DynamicPricingService simulation failed', ['error' => $e->getMessage()]);
            return ['projected_demand_change_percent' => 0, 'projected_revenue_change_percent' => 0, 'risk_level' => 'medium', 'factors' => [], 'recommendation' => 'Simulation unavailable', 'confidence' => 0, 'source' => 'fallback'];
        }
    }

    private function buildPricingPrompt(array $params): string
    {
        return "Calculate an optimal dynamic price for this item/service. Consider:\n"
            . "- Base price and cost\n"
            . "- Current demand level (low/medium/high/peak)\n"
            . "- Time of day and day of week\n"
            . "- Competitor pricing if available\n"
            . "- Inventory/availability\n"
            . "- Customer segment\n"
            . "- Historical sales velocity\n"
            . "- Category margins\n\n"
            . "Item data: " . json_encode($params) . "\n\n"
            . "Constraints:\n"
            . "- Never exceed 3x base price\n"
            . "- Never go below 0.5x base price\n"
            . "- Maintain minimum margin if cost provided\n"
            . "- Explain every factor\n\n"
            . "Return JSON: {\"dynamic_multiplier\": 0.5-3.0, \"factors\": [{\"name\": \"...\", \"impact\": \"positive|negative|neutral\", \"weight\": 0-1}], \"recommendation\": \"...\", \"explanation\": \"...\", \"confidence\": 0-1}";
    }

    private function fallbackPricing(array $params): array
    {
        $basePrice = (float) ($params['base_price'] ?? 0);
        $demand = $params['demand_level'] ?? 'medium';

        $multiplier = match ($demand) {
            'peak' => 1.25,
            'high' => 1.15,
            'medium' => 1.0,
            'low' => 0.95,
            default => 1.0,
        };

        return [
            'base_price' => $basePrice,
            'dynamic_multiplier' => $multiplier,
            'final_price' => round($basePrice * $multiplier, 2),
            'factors' => [['name' => 'demand_level', 'impact' => $demand === 'low' ? 'negative' : 'positive', 'weight' => 0.5]],
            'recommendation' => 'AI pricing unavailable — using demand-based fallback',
            'explanation' => "Demand level: {$demand}. Multiplier: {$multiplier}x",
            'confidence' => 0.3,
            'source' => 'fallback',
        ];
    }
}
