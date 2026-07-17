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
            $response = $this->ai->chat($prompt, $this->encodeContext($transactionData));
            $parsed = $this->decodeResponse($response);

            if ($parsed === null) {
                return $this->fallbackAnalysis($transactionData);
            }

            $riskScore = $this->toFloat($parsed['risk_score'] ?? null, 0.0);

            return [
                'risk_score' => $riskScore,
                'risk_level' => $this->classifyRisk($riskScore),
                'flags' => $parsed['flags'] ?? [],
                'recommended_action' => $parsed['recommended_action'] ?? 'review',
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => $this->toFloat($parsed['confidence'] ?? null, 0.5),
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
            $response = $this->ai->chat($prompt, $this->encodeContext($accountData));
            $parsed = $this->decodeResponse($response);

            if ($parsed === null) {
                return $this->fallbackAccountAnalysis($accountData);
            }

            $riskScore = $this->toFloat($parsed['risk_score'] ?? null, 0.0);

            return [
                'risk_score' => $riskScore,
                'risk_level' => $this->classifyRisk($riskScore),
                'flags' => $parsed['flags'] ?? [],
                'recommended_action' => $parsed['recommended_action'] ?? 'review',
                'patterns' => $parsed['patterns'] ?? [],
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => $this->toFloat($parsed['confidence'] ?? null, 0.5),
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
            $response = $this->ai->chat($prompt, $this->encodeContext($receiptData));
            $parsed = $this->decodeResponse($response);

            if ($parsed === null) {
                return $this->fallbackReceiptAnalysis();
            }

            return [
                'manipulation_score' => $this->toFloat($parsed['manipulation_score'] ?? null, 0.0),
                'flags' => $parsed['flags'] ?? [],
                'explanation' => $parsed['explanation'] ?? null,
                'confidence' => $this->toFloat($parsed['confidence'] ?? null, 0.5),
                'source' => 'ai_receipt_analysis',
            ];
        } catch (\Exception $e) {
            Log::warning('FraudDetectionService receipt analysis failed', ['error' => $e->getMessage()]);
            return $this->fallbackReceiptAnalysis();
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
            'recommended_action' => $score > 0 ? 'review' : 'approve',
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

    private function fallbackReceiptAnalysis(): array
    {
        return [
            'manipulation_score' => 0.0,
            'flags' => [],
            'explanation' => 'Analysis unavailable',
            'confidence' => 0.0,
            'source' => 'fallback',
        ];
    }

    private function encodeContext(array $data): string
    {
        $encoded = json_encode($data);
        return is_string($encoded) ? $encoded : '{}';
    }

    private function decodeResponse(mixed $response): ?array
    {
        if (is_array($response)) {
            return $response;
        }

        if (! is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function toFloat(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }
}
