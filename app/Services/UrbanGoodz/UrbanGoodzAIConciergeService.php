<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;

class UrbanGoodzAIConciergeService
{
    public function processQuery(string $queryText, ?int $customerId = null, string $source = 'customer_api'): UrbanGoodzAIConversation
    {
        $queryLower = strtolower(trim($queryText));

        $intents = UrbanGoodzAIIntent::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $bestIntent = null;
        $bestScore = 0;

        foreach ($intents as $intent) {
            $keywords = $intent->keywords ?? [];
            foreach ($keywords as $keyword) {
                $keywordLower = strtolower(trim($keyword));
                if (str_contains($queryLower, $keywordLower)) {
                    $score = $this->scoreKeyword($queryLower, $keywordLower);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestIntent = $intent;
                    }
                }
            }
        }

        $responseText = $bestIntent?->response_template ?? $this->defaultFallbackResponse();

        return UrbanGoodzAIConversation::create([
            'customer_id' => $customerId,
            'query_text' => $queryText,
            'detected_intent_id' => $bestIntent?->id,
            'confidence_score' => $bestScore > 0 ? $bestScore : null,
            'response_text' => $responseText,
            'status' => $bestIntent ? 'resolved' : 'pending',
            'source' => $source,
        ]);
    }

    private function scoreKeyword(string $query, string $keyword): float
    {
        $baseScore = 50;
        $wordCount = str_word_count($keyword);
        $exactMatch = str_contains($query, $keyword);
        if ($exactMatch && $wordCount > 1) {
            return $baseScore + 30;
        }
        if ($exactMatch) {
            return $baseScore + 10;
        }
        return 0;
    }

    private function defaultFallbackResponse(): string
    {
        return "Thanks for reaching out to Urban Goodz! I'm not quite sure how to help with that yet. "
            . "A customer service representative will review your query and get back to you soon.";
    }
}
