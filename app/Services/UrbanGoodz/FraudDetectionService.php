<?php

namespace App\Services\UrbanGoodz;

class FraudDetectionService
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    public function analyzeTransaction(array $transactionData): array
    {
        return [
            'risk_score' => 0.0,
            'risk_level' => 'low',
            'flags' => [],
            'recommended_action' => 'approve',
        ];
    }
}
