<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\LoadEmailIngestion;
use App\Models\ExternalLoad;
use App\Models\LoadSource;
use Illuminate\Support\Facades\Log;

class LoadEmailIngestionService
{
    public function ingestEmail(array $emailData): array
    {
        $sourceEmailId = $emailData['source_email_id'] ?? null;
        if (!$sourceEmailId) {
            return ['success' => false, 'error' => 'source_email_id required'];
        }

        $existing = LoadEmailIngestion::where('source_email_id', $sourceEmailId)->first();
        if ($existing) {
            return ['success' => false, 'error' => 'Duplicate email ingestion blocked', 'existing_id' => $existing->id];
        }

        $subject = $emailData['subject'] ?? '';
        $body = $emailData['body'] ?? $emailData['raw_body'] ?? '';

        $extracted = $this->extractLoadData($subject, $body, $emailData);

        $confidence = $this->calculateConfidence($extracted);

        $ingestion = LoadEmailIngestion::create([
            'source_email_id' => $sourceEmailId,
            'from_address' => $emailData['from'] ?? $emailData['from_address'] ?? null,
            'from_name' => $emailData['from_name'] ?? null,
            'subject' => $subject,
            'received_at' => $emailData['received_at'] ?? now(),
            'raw_body' => $body,
            'origin_city' => $extracted['origin_city'] ?? null,
            'origin_state' => $extracted['origin_state'] ?? null,
            'destination_city' => $extracted['destination_city'] ?? null,
            'destination_state' => $extracted['destination_state'] ?? null,
            'equipment_type' => $extracted['equipment_type'] ?? null,
            'weight' => $extracted['weight'] ?? null,
            'commodity' => $extracted['commodity'] ?? null,
            'rate' => $extracted['rate'] ?? null,
            'broker_name' => $extracted['broker_name'] ?? null,
            'broker_contact' => $extracted['broker_contact'] ?? null,
            'broker_reference' => $extracted['broker_reference'] ?? null,
            'confidence_score' => $confidence,
            'status' => $confidence >= 0.6 ? 'pending_review' : 'received',
            'metadata' => ['extraction_method' => 'regex_pattern_matching'],
        ]);

        return [
            'success' => true,
            'ingestion' => $ingestion,
            'confidence' => $confidence,
            'needs_human_review' => $confidence < 0.6,
        ];
    }

    public function approve(int $ingestionId, ?int $approvedBy, array $editedData = []): array
    {
        $ingestion = LoadEmailIngestion::find($ingestionId);
        if (!$ingestion) {
            return ['success' => false, 'error' => 'Ingestion not found'];
        }

        $source = LoadSource::where('source_key', 'email_inbox')->first();
        if (!$source) {
            return ['success' => false, 'error' => 'Email inbox source not configured'];
        }

        $data = array_merge([
            'origin_city' => $ingestion->origin_city,
            'origin_state' => $ingestion->origin_state,
            'destination_city' => $ingestion->destination_city,
            'destination_state' => $ingestion->destination_state,
            'equipment_type' => $ingestion->equipment_type,
            'weight' => $ingestion->weight,
            'commodity' => $ingestion->commodity,
            'gross_rate' => $ingestion->rate,
            'broker_name' => $ingestion->broker_name,
            'broker_contact' => $ingestion->broker_contact,
            'broker_reference' => $ingestion->broker_reference,
        ], $editedData);

        $normalizer = new LoadNormalizer();
        $normalized = $normalizer->normalize(array_merge($data, [
            'external_id' => 'email-' . $ingestion->id,
            'compliance_status' => 'email_sourced',
            'status' => 'pending_review',
        ]), $source->id);

        $externalLoad = $normalizer->persistNormalized($normalized);

        $ingestion->update([
            'status' => 'approved',
            'external_load_id' => $externalLoad->id,
            'processed_by' => $approvedBy,
            'processed_at' => now(),
        ]);

        return ['success' => true, 'external_load_id' => $externalLoad->id];
    }

    public function reject(int $ingestionId, ?int $rejectedBy, string $reason): array
    {
        $ingestion = LoadEmailIngestion::find($ingestionId);
        if (!$ingestion) {
            return ['success' => false, 'error' => 'Ingestion not found'];
        }

        $ingestion->update([
            'status' => 'rejected',
            'processed_by' => $rejectedBy,
            'processed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return ['success' => true];
    }

    private function extractLoadData(string $subject, string $body, array $emailData): array
    {
        $text = $subject . "\n" . $body;
        $extracted = [];

        $originPatterns = [
            '/(?:from|origin|pickup|loading)\s*[:;]?\s*([A-Z][a-z]+(?:\s[A-Z][a-z]+)*),?\s*([A-Z]{2})/i',
            '/([A-Z][a-z]+(?:\s[A-Z][a-z]+)*),\s*([A-Z]{2})\s*(?:to|->|->>|=>)/i',
        ];
        foreach ($originPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $extracted['origin_city'] = trim($matches[1]);
                $extracted['origin_state'] = strtoupper(trim($matches[2]));
                break;
            }
        }

        $destPatterns = [
            '/(?:to|destination|deliver|drop)\s*[:;]?\s*([A-Z][a-z]+(?:\s[A-Z][a-z]+)*),?\s*([A-Z]{2})/i',
            '/(?:->|->>|=>)\s*([A-Z][a-z]+(?:\s[A-Z][a-z]+)*),\s*([A-Z]{2})/i',
        ];
        foreach ($destPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $extracted['destination_city'] = trim($matches[1]);
                $extracted['destination_state'] = strtoupper(trim($matches[2]));
                break;
            }
        }

        if (preg_match('/(?:rate|price|pay|offer)\s*[:;]?\s*\$?([\d,]+(?:\.\d{2})?)/i', $text, $matches)) {
            $extracted['rate'] = (float) str_replace(',', '', $matches[1]);
        }

        if (preg_match('/(\d{1,3}(?:,\d{3})*)\s*(?:lbs?|pounds?|lb)/i', $text, $matches)) {
            $extracted['weight'] = (float) str_replace(',', '', $matches[1]);
        }

        $equipKeywords = ['van', 'reefer', 'flatbed', 'tanker', 'step deck', 'dry van', 'enclosed'];
        foreach ($equipKeywords as $kw) {
            if (stripos($text, $kw) !== false) {
                $extracted['equipment_type'] = $kw;
                break;
            }
        }

        if (preg_match('/(?:broker|contact|call)\s*[:;]?\s*([A-Z][a-z]+(?:\s[A-Z][a-z]+)*?)(?:\s+at\s+|\s*,\s*|\s+on\s+|$)/i', $text, $matches)) {
            $extracted['broker_name'] = trim($matches[1]);
        }

        if (preg_match('/([\w.+-]+@[\w-]+\.[\w.]+)/', $text, $matches)) {
            $extracted['broker_contact'] = $matches[1];
        }

        if (preg_match('/(?:ref|reference|load\s*#?|bol)\s*[:;]?\s*(\S+)/i', $text, $matches)) {
            $extracted['broker_reference'] = trim($matches[1]);
        }

        return $extracted;
    }

    private function calculateConfidence(array $extracted): float
    {
        $fields = ['origin_city', 'origin_state', 'destination_city', 'destination_state', 'equipment_type', 'rate', 'weight', 'broker_name'];
        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($extracted[$field])) $filled++;
        }
        return round($filled / count($fields), 2);
    }
}
