<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    /**
     * AI-powered fraud risk analysis.
     * Combines deterministic rules with AI pattern analysis.
     */
    public function analyzeTransaction(array $transactionData): array
    {
        $prompt = $this->buildAnalysisPrompt($transactionData);

        try {
            $response = $this->ai->chat($prompt, [
                'model' => config('urban_goodz.ai_model', 'gpt-4o'),
                'temperature' => 0.2,
                'max_tokens' => 800,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = is_string($response) ? json_decode($response, true) : $response;

            if (! is_array($parsed)) {
                return $this->fallbackAnalysis($transactionData);
            }

            return [
                'risk_score' => (float) ($parsed['risk_score'] ?? 0.0),
                'risk_level' => $this->classifyRisk((float) ($parsed['risk_score'] ?? 0.0)),
                'flags' => $parsed['flags'] ?? [],
                'recommended_action' => $parsed['recommended_action'] ?? 'review',
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.5),
                'source' => 'ai_analysis',
            ];
        } catch (\Exception $e) {
            Log::warning('FraudDetectionService AI analysis failed', ['error' => $e->getMessage()]);
            return $this->fallbackAnalysis($transactionData);
        }
    }

    /**
     * AI-powered account risk scoring.
     */
    public function analyzeAccount(array $accountData): array
    {
        $prompt = $this->buildAccountPrompt($accountData);

        try {
            $response = $this->ai->chat($prompt, [
                'model' => config('urban_goodz.ai_model', 'gpt-4o'),
                'temperature' => 0.2,
                'max_tokens' => 800,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = is_string($response) ? json_decode($response, true) : $response;

            if (! is_array($parsed)) {
                return $this->fallbackAccountAnalysis($accountData);
            }

            return [
                'risk_score' => (float) ($parsed['risk_score'] ?? 0.0),
                'risk_level' => $this->classifyRisk((float) ($parsed['risk_score'] ?? 0.0)),
                'flags' => $parsed['flags'] ?? [],
                'recommended_action' => $parsed['recommended_action'] ?? 'review',
                'patterns' => $parsed['patterns'] ?? [],
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.5),
                'source' => 'ai_analysis',
            ];
        } catch (\Exception $e) {
            Log::warning('FraudDetectionService AI account analysis failed', ['error' => $e->getMessage()]);
            return $this->fallbackAccountAnalysis($accountData);
        }
    }

    /**
     * AI-powered receipt manipulation detection.
     */
    public function analyzeReceipt(array $receiptData): array
    {
        $prompt = "Analyze this receipt data for potential fraud or manipulation. Look for:\n"
            . "- Round number patterns suggesting fabrication\n"
            . "- Tax calculation inconsistencies\n"
            . "- Item pricing anomalies\n"
            . "- Merchant name mismatches\n"
            . "- Date/time anomalies\n"
            . "- Duplicate receipt indicators\n\n"
            . "Receipt data: " . json_encode($receiptData) . "\n\n"
            . "Return JSON: {\"manipulation_score\": 0-100, \"flags\": [...], \"explanation\": \"...\", \"confidence\": 0-1}";

        try {
            $response = $this->ai->chat($prompt, [
                'temperature' => 0.2,
                'max_tokens' => 600,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = is_string($response) ? json_decode($response, true) : $response;

            return [
                'manipulation_score' => (float) ($parsed['manipulation_score'] ?? 0),
                'flags' => $parsed['flags'] ?? [],
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.5),
                'source' => 'ai_receipt_analysis',
            ];
        } catch (\Exception $e) {
            Log::warning('FraudDetectionService receipt analysis failed', ['error' => $e->getMessage()]);
            return ['manipulation_score' => 0, 'flags' => [], 'explanation' => 'Analysis unavailable', 'confidence' => 0, 'source' => 'fallback'];
        }
    }

    private function buildAnalysisPrompt(array $data): string
    {
        return "Analyze this transaction for fraud risk. Consider:\n"
            . "- Transaction amount vs customer history\n"
            . "- Payment method risk\n"
            . "- Timing patterns\n"
            . "- Geographic anomalies\n"
            . "- Device/browser fingerprints\n"
            . "- Refund history\n"
            . "- Vendor relationship\n\n"
            . "Transaction: " . json_encode($data) . "\n\n"
            . "Return JSON: {\"risk_score\": 0-100, \"flags\": [{\"type\": \"...\", \"severity\": \"low|medium|high|critical\", \"message\": \"...\"}], \"recommended_action\": \"approve|review|block\", \"explanation\": \"...\", \"confidence\": 0-1}";
    }

    private function buildAccountPrompt(array $data): string
    {
        return "Analyze this account for fraud risk patterns. Consider:\n"
            . "- Account age and activity\n"
            . "- Transaction velocity\n"
            . "- Refund frequency\n"
            . "- Login patterns\n"
            . "- Device changes\n"
            . "- Address changes\n"
            . "- Payment method diversity\n\n"
            . "Account data: " . json_encode($data) . "\n\n"
            . "Return JSON: {\"risk_score\": 0-100, \"flags\": [...], \"patterns\": [...], \"recommended_action\": \"approve|review|suspend\", \"explanation\": \"...\", \"confidence\": 0-1}";
    }

    private function classifyRisk(float $score): string
    {
        return match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 40 => 'medium',
            $score >= 20 => 'low',
            default => 'minimal',
        };
    }

    private function fallbackAnalysis(array $data): array
    {
        $score = 0;
        $flags = [];

        if (($data['amount'] ?? 0) > 500) {
            $score += 25;
            $flags[] = ['type' => 'high_amount', 'severity' => 'medium', 'message' => 'Transaction exceeds $500 threshold'];
        }

        return [
            'risk_score' => min($score, 100),
            'risk_level' => $this->classifyRisk($score),
            'flags' => $flags,
            'recommended_action' => $score >= 60 ? 'review' : 'approve',
            'explanation' => 'AI analysis unavailable — using rule-based fallback',
            'confidence' => 0.3,
            'source' => 'fallback',
        ];
    }

    private function fallbackAccountAnalysis(array $data): array
    {
        return [
            'risk_score' => 0.0,
            'risk_level' => 'low',
            'flags' => [],
            'recommended_action' => 'review',
            'patterns' => [],
            'explanation' => 'AI analysis unavailable — manual review recommended',
            'confidence' => 0.0,
            'source' => 'fallback',
        ];
    }
}
