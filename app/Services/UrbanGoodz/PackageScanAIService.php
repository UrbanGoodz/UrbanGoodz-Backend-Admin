<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PackageScanAIService
{
    private UrbanGoodzAIService $aiService;
    private string $apiKey;
    private string $model;

    public function __construct(UrbanGoodzAIService $aiService)
    {
        $this->aiService = $aiService;
        $this->apiKey = (string) (config('openai.api_key', env('OPENAI_API_KEY')) ?? '');
        $this->model = config('urban_goodz.ai_model', 'gpt-4o');
    }

    public function verifyPickup(string $photoData, array $orderData): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured.');
        }

        $orderSummary = collect([
            'Order ID' => $orderData['id'] ?? 'N/A',
            'Items' => $orderData['items_summary'] ?? 'N/A',
            'Package Description' => $orderData['package_description'] ?? 'N/A',
            'Expected Weight' => $orderData['weight'] ?? 'N/A',
            'Pickup Location' => $orderData['pickup_address'] ?? 'N/A',
            'Special Instructions' => $orderData['special_instructions'] ?? 'None',
        ])->filter(fn($v) => $v !== 'N/A')->implode("\n");

        $systemPrompt = "You are an AI package verification system for Urban Goodz delivery drivers.
Analyze the photo of a package at pickup and verify it matches the order.

Return ONLY valid JSON with this structure:
{
  \"verified\": true,
  \"confidence\": 0.0-1.0,
  \"package_matches_order\": true,
  \"label_readable\": true,
  \"label_details\": {
    \"tracking_number\": \"string or null\",
    \"carrier\": \"string or null\",
    \"sender\": \"string or null\",
    \"recipient\": \"string or null\"
  },
  \"condition\": {
    \"status\": \"good\"|\"damaged\"|\"questionable\",
    \"details\": \"brief description\"
  },
  \"barcode_detected\": true,
  \"issues\": [\"list of any problems found\"],
  \"notes\": \"any additional observations\"
}";

        $result = $this->visionChat($systemPrompt, $orderSummary, $photoData);
        return $this->parseJson($result, [
            'verified' => false,
            'confidence' => 0,
            'package_matches_order' => false,
            'label_readable' => false,
            'condition' => ['status' => 'unknown', 'details' => 'Analysis failed'],
            'issues' => ['AI analysis did not return valid data'],
        ]);
    }

    public function verifyDelivery(string $photoData, array $deliveryContext): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured.');
        }

        $contextSummary = collect([
            'Order ID' => $deliveryContext['order_id'] ?? 'N/A',
            'Delivery Address' => $deliveryContext['delivery_address'] ?? 'N/A',
            'Recipient Name' => $deliveryContext['recipient_name'] ?? 'N/A',
            'Drop-off Instructions' => $deliveryContext['dropoff_instructions'] ?? 'None',
            'Package Description' => $deliveryContext['package_description'] ?? 'N/A',
            'Weather' => $deliveryContext['weather'] ?? 'Unknown',
        ])->filter(fn($v) => $v !== 'N/A')->implode("\n");

        $systemPrompt = "You are an AI delivery verification system for Urban Goodz.
Analyze the delivery photo and verify safe and correct package placement.

Return ONLY valid JSON with this structure:
{
  \"delivery_verified\": true,
  \"confidence\": 0.0-1.0,
  \"safe_dropoff\": true,
  \"dropoff_assessment\": {
    \"location_quality\": \"excellent\"|\"good\"|\"poor\"|\"unsafe\",
    \"visibility\": \"clear\"|\"partially_obscured\"|\"obscured\",
    \"details\": \"description of drop-off location\"
  },
  \"package_visible\": true,
  \"package_condition_at_delivery\": \"good\"|\"damaged\"|\"compromised\",
  \"address_visible\": true,
  \"address_match\": true,
  \"environment\": {
    \"weather_visible\": \"string\",
    \"safety_concerns\": [\"list any concerns\"],
    \"notes\": \"environmental observations\"
  },
  \"issues\": [\"list of problems\"],
  \"recommendation\": \"proceed\"|\"retry\"|\"escalate\"
}";

        $result = $this->visionChat($systemPrompt, $contextSummary, $photoData);
        return $this->parseJson($result, [
            'delivery_verified' => false,
            'confidence' => 0,
            'safe_dropoff' => false,
            'package_visible' => false,
            'issues' => ['AI analysis did not return valid data'],
            'recommendation' => 'escalate',
        ]);
    }

    public function detectBarcodeOrLabel(string $photoData): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured.');
        }

        $systemPrompt = "You are an AI barcode and shipping label reader for Urban Goodz.
Examine the photo and detect any barcodes, QR codes, or shipping labels.

Return ONLY valid JSON with this structure:
{
  \"barcode_detected\": true,
  \"barcode_number\": \"string or null\",
  \"barcode_type\": \"UPC_A\"|\"UPC_E\"|\"EAN_13\"|\"EAN_8\"|\"CODE_128\"|\"QR_CODE\"|\"DATA_MATRIX\"|\"none\",
  \"tracking_number\": \"string or null\",
  \"carrier\": \"string or null\",
  \"sender\": {
    \"name\": \"string or null\",
    \"address\": \"string or null\"
  },
  \"recipient\": {
    \"name\": \"string or null\",
    \"address\": \"string or null\"
  },
  \"label_condition\": \"pristine\"|\"good\"|\"worn\"|\"damaged\"|\"illegible\",
  \"label_readable\": true,
  \"additional_barcodes_found\": 0,
  \"confidence\": 0.0-1.0,
  \"notes\": \"any observations\"
}";

        $result = $this->visionChat($systemPrompt, 'Detect and read any barcodes, QR codes, or shipping labels on this package.', $photoData);
        return $this->parseJson($result, [
            'barcode_detected' => false,
            'barcode_number' => null,
            'tracking_number' => null,
            'carrier' => null,
            'label_condition' => 'unknown',
            'confidence' => 0,
        ]);
    }

    public function assessPackageCondition(string $photoData, string $stage = 'pickup'): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured.');
        }

        $stageContext = $stage === 'delivery'
            ? 'This is a delivery-stage inspection. The package has been transported and is being delivered.'
            : 'This is a pickup-stage inspection. The package is being picked up from the sender.';

        $systemPrompt = "You are an AI package condition inspector for Urban Goodz.
Perform a detailed condition assessment of the package in the photo.

Context: {$stageContext}

Return ONLY valid JSON with this structure:
{
  \"overall_condition\": \"good\"|\"damaged\"|\"compromised\",
  \"confidence\": 0.0-1.0,
  \"packaging_integrity\": {
    \"status\": \"intact\"|\"minor_wear\"|\"damaged\"|\"compromised\",
    \"details\": \"string\"
  },
  \"damage_detection\": {
    \"has_damage\": false,
    \"damage_types\": [\"tear\"|\"crush\"|\"water\"|\"stain\"|\"puncture\"|\"none\"],
    \"severity\": \"none\"|\"minor\"|\"moderate\"|\"severe\",
    \"details\": \"string\"
  },
  \"tampering_signs\": {
    \"detected\": false,
    \"indicators\": [\"resealed_tape\"|\"mismatched_labels\"|\"opened_box\"|\"none\"],
    \"details\": \"string\"
  },
  \"handling_instructions\": {
    \"visible\": false,
    \"instructions\": [\"fragile\"|\"this_side_up\"|\"keep_dry\"|\"perishable\"|\"none\"],
    \"details\": \"string\"
  },
  \"packaging_quality\": {
    \"tape_seal\": \"secure\"|\"loose\"|\"missing\",
    \"cushioning\": \"adequate\"|\"insufficient\"|\"none\",
    \"box_condition\": \"new\"|\"reused\"|\"damaged\",
    \"details\": \"string\"
  },
  \"notes\": \"any additional observations\"
}";

        $result = $this->visionChat($systemPrompt, "Assess the detailed condition of this package.", $photoData);
        return $this->parseJson($result, [
            'overall_condition' => 'unknown',
            'confidence' => 0,
            'packaging_integrity' => ['status' => 'unknown', 'details' => 'Assessment failed'],
            'damage_detection' => ['has_damage' => false, 'damage_types' => [], 'severity' => 'none'],
            'tampering_signs' => ['detected' => false, 'indicators' => []],
            'handling_instructions' => ['visible' => false, 'instructions' => []],
            'packaging_quality' => ['tape_seal' => 'unknown', 'cushioning' => 'unknown', 'box_condition' => 'unknown'],
            'notes' => 'AI analysis did not return valid data',
        ]);
    }

    public function generateDeliveryProof(array $verificationData): array
    {
        $proofId = 'UPF-' . now()->format('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));

        $photoCount = count($verificationData['photos'] ?? []);
        $hasSignature = !empty($verificationData['customer_signature']);
        $avgConfidence = $this->calculateAverageConfidence($verificationData);

        $conditionNotes = $verificationData['condition_assessment']['overall_condition'] ?? 'not assessed';
        $verificationNotes = $verificationData['verification_result'] ?? [];

        return [
            'proof_id' => $proofId,
            'order_id' => $verificationData['order_id'] ?? null,
            'delivery_man_id' => $verificationData['delivery_man_id'] ?? null,
            'timestamp' => now()->toIso8601String(),
            'photos' => $verificationData['photos'] ?? [],
            'photo_count' => $photoCount,
            'gps_location' => [
                'latitude' => $verificationData['latitude'] ?? null,
                'longitude' => $verificationData['longitude'] ?? null,
                'accuracy_meters' => $verificationData['gps_accuracy'] ?? null,
            ],
            'condition_assessment' => $verificationData['condition_assessment'] ?? null,
            'condition_summary' => $conditionNotes,
            'verification_result' => [
                'pickup_verified' => $verificationNotes['pickup_verified'] ?? null,
                'delivery_verified' => $verificationNotes['delivery_verified'] ?? null,
                'overall_confidence' => $avgConfidence,
            ],
            'customer_signature' => [
                'present' => $hasSignature,
                'signed_at' => $verificationData['signature_timestamp'] ?? null,
                'signature_data' => $verificationData['customer_signature'] ?? null,
            ],
            'delivery_details' => [
                'pickup_address' => $verificationData['pickup_address'] ?? null,
                'delivery_address' => $verificationData['delivery_address'] ?? null,
                'special_instructions_followed' => $verificationData['instructions_followed'] ?? null,
            ],
            'ai_metadata' => [
                'model_used' => $this->model,
                'analysis_timestamp' => now()->toIso8601String(),
                'proof_generated_by' => 'PackageScanAIService',
            ],
        ];
    }

    private function isConfigured(): bool
    {
        return !empty($this->apiKey) && strlen($this->apiKey) > 10;
    }

    private function visionChat(string $systemPrompt, string $userMessage, string $photoData): string
    {
        if (!$this->isConfigured()) {
            Log::warning('PackageScan AI: OpenAI API key not configured');
            return json_encode(['error' => 'AI service is not yet configured.']);
        }

        $imageDataUrl = $photoData;
        if (!str_starts_with($photoData, 'data:image')) {
            $imageDataUrl = 'data:image/jpeg;base64,' . $photoData;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $userMessage,
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $imageDataUrl,
                            'detail' => 'high',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.2,
                'max_tokens' => 2000,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                return $body['choices'][0]['message']['content'] ?? '{}';
            }

            Log::error('PackageScan AI: OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return json_encode(['error' => 'AI API returned an error.']);

        } catch (\Exception $e) {
            Log::error('PackageScan AI: Exception calling OpenAI Vision', ['error' => $e->getMessage()]);
            return json_encode(['error' => 'AI service temporarily unavailable.']);
        }
    }

    private function parseJson(string $result, array $fallback): array
    {
        $cleaned = trim($result);
        $cleaned = preg_replace('/```json\s*/', '', $cleaned);
        $cleaned = preg_replace('/```\s*$/', '', $cleaned);

        $json = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        return $fallback;
    }

    private function calculateAverageConfidence(array $verificationData): float
    {
        $confidences = [];

        if (isset($verificationData['pickup_verification']['confidence'])) {
            $confidences[] = $verificationData['pickup_verification']['confidence'];
        }
        if (isset($verificationData['delivery_verification']['confidence'])) {
            $confidences[] = $verificationData['delivery_verification']['confidence'];
        }
        if (isset($verificationData['condition_assessment']['confidence'])) {
            $confidences[] = $verificationData['condition_assessment']['confidence'];
        }

        if (empty($confidences)) {
            return 0.0;
        }

        return round(array_sum($confidences) / count($confidences), 2);
    }

    private function errorResponse(string $message): array
    {
        return [
            'verified' => false,
            'confidence' => 0,
            'error' => $message,
        ];
    }
}
