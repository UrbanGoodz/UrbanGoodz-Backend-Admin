<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FashionFitAIService
{
    private string $apiKey;
    private float $temperature;
    private int $maxTokens;

    private const MODEL = 'gpt-4o';

    private const STANDARD_SIZES = [
        'tshirt' => [
            'XS'  => ['chest' => [31, 33], 'waist' => [25, 27], 'shoulders' => [14, 15]],
            'S'   => ['chest' => [34, 36], 'waist' => [28, 30], 'shoulders' => [15.5, 16.5]],
            'M'   => ['chest' => [38, 40], 'waist' => [32, 34], 'shoulders' => [17, 18]],
            'L'   => ['chest' => [42, 44], 'waist' => [36, 38], 'shoulders' => [18.5, 19.5]],
            'XL'  => ['chest' => [46, 48], 'waist' => [40, 42], 'shoulders' => [20, 21]],
            'XXL' => ['chest' => [50, 52], 'waist' => [44, 46], 'shoulders' => [21.5, 22.5]],
        ],
        'dress_shirt' => [
            'XS'  => ['chest' => [32, 34], 'neck' => [13, 13.5], 'waist' => [26, 28]],
            'S'   => ['chest' => [35, 37], 'neck' => [14, 14.5], 'waist' => [29, 31]],
            'M'   => ['chest' => [38, 40], 'neck' => [15, 15.5], 'waist' => [32, 34]],
            'L'   => ['chest' => [42, 44], 'neck' => [16, 16.5], 'waist' => [36, 38]],
            'XL'  => ['chest' => [46, 48], 'neck' => [17, 17.5], 'waist' => [40, 42]],
            'XXL' => ['chest' => [50, 52], 'neck' => [18, 18.5], 'waist' => [44, 46]],
        ],
        'pants' => [
            'XS'  => ['waist' => [25, 27], 'inseam' => [28, 30], 'hips' => [33, 35]],
            'S'   => ['waist' => [28, 30], 'inseam' => [30, 32], 'hips' => [36, 38]],
            'M'   => ['waist' => [32, 34], 'inseam' => [30, 32], 'hips' => [39, 41]],
            'L'   => ['waist' => [36, 38], 'inseam' => [30, 34], 'hips' => [42, 44]],
            'XL'  => ['waist' => [40, 42], 'inseam' => [30, 34], 'hips' => [45, 47]],
            'XXL' => ['waist' => [44, 46], 'inseam' => [30, 34], 'hips' => [48, 50]],
        ],
        'suit_jacket' => [
            '34R' => ['chest' => [34, 35], 'shoulders' => [15.5, 16.5], 'waist' => [29, 30]],
            '36R' => ['chest' => [36, 37], 'shoulders' => [16.5, 17.5], 'waist' => [31, 32]],
            '38R' => ['chest' => [38, 39], 'shoulders' => [17.5, 18.5], 'waist' => [33, 34]],
            '40R' => ['chest' => [40, 41], 'shoulders' => [18.5, 19.5], 'waist' => [35, 36]],
            '42R' => ['chest' => [42, 43], 'shoulders' => [19.5, 20.5], 'waist' => [37, 38]],
            '44R' => ['chest' => [44, 45], 'shoulders' => [20.5, 21.5], 'waist' => [39, 40]],
            '46R' => ['chest' => [46, 47], 'shoulders' => [21.5, 22.5], 'waist' => [41, 42]],
            '48R' => ['chest' => [48, 49], 'shoulders' => [22.5, 23.5], 'waist' => [43, 44]],
        ],
        'dress' => [
            'XS'  => ['chest' => [31, 33], 'waist' => [24, 26], 'hips' => [34, 36]],
            'S'   => ['chest' => [34, 36], 'waist' => [27, 29], 'hips' => [37, 39]],
            'M'   => ['chest' => [37, 39], 'waist' => [30, 32], 'hips' => [40, 42]],
            'L'   => ['chest' => [40, 42], 'waist' => [33, 35], 'hips' => [43, 45]],
            'XL'  => ['chest' => [44, 46], 'waist' => [36, 38], 'hips' => [46, 48]],
            'XXL' => ['chest' => [48, 50], 'waist' => [40, 42], 'hips' => [50, 52]],
        ],
    ];

    public function __construct()
    {
        $this->apiKey = config('openai.api_key', env('OPENAI_API_KEY', ''));
        $this->temperature = (float) config('urban_goodz.ai_temperature', 0.3);
        $this->maxTokens = (int) config('urban_goodz.ai_max_tokens', 2000);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && strlen($this->apiKey) > 10;
    }

    /**
     * Extract body measurements from a customer photo using GPT-4o Vision.
     *
     * @param string $photoData  Base64-encoded image or a publicly accessible URL
     * @param array  $context    Optional hints (garment_type, customer_id, notes)
     * @return array{measurements: array, image_quality: string, warnings: array, confidence: float}
     */
    public function extractMeasurementsFromPhoto(string $photoData, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResult('AI service is not configured. Please set OPENAI_API_KEY.');
        }

        if (empty($photoData)) {
            return $this->errorResult('No photo data provided. Please upload a front-facing full-body photo.');
        }

        $systemPrompt = <<<'PROMPT'
You are an expert tailor and body measurement analyst. Analyze the provided photograph of a person and estimate their body measurements in inches.

IMPORTANT GUIDELINES:
- The photo should show a front-facing, full-body view for best accuracy.
- All measurement values MUST be in inches.
- Provide realistic estimates based on body proportions visible in the image.
- If the image is blurry, cropped, dark, or the body is obstructed, lower your confidence and flag quality issues.
- Consider the person's apparent build, posture, and proportions.
- Never fabricate measurements you cannot reasonably infer from the image.

Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
{
  "measurements": {
    "height": {"value": 0.0, "confidence": 0.0},
    "chest": {"value": 0.0, "confidence": 0.0},
    "waist": {"value": 0.0, "confidence": 0.0},
    "hips": {"value": 0.0, "confidence": 0.0},
    "inseam": {"value": 0.0, "confidence": 0.0},
    "shoulders": {"value": 0.0, "confidence": 0.0},
    "arm_length": {"value": 0.0, "confidence": 0.0},
    "neck": {"value": 0.0, "confidence": 0.0},
    "thigh": {"value": 0.0, "confidence": 0.0}
  },
  "image_quality": "good|fair|poor|unusable",
  "quality_notes": "brief explanation of image quality assessment",
  "overall_confidence": 0.0,
  "warnings": ["list", "of", "any", "concerns"],
  "body_type_notes": "brief body type observation for tailor context"
}
PROMPT;

        $userMessage = 'Please analyze this photo and extract body measurements.';
        if (!empty($context['garment_type'])) {
            $userMessage .= " The customer is ordering a {$context['garment_type']}.";
        }
        if (!empty($context['style_notes'])) {
            $userMessage .= " Style notes: {$context['style_notes']}";
        }

        $result = $this->callVisionApi($systemPrompt, $userMessage, $photoData);

        if ($result === null) {
            return $this->errorResult('Failed to analyze the photo. The image may be in an unsupported format.');
        }

        $parsed = json_decode(trim($result), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['measurements'])) {
            Log::warning('FashionFitAI: Failed to parse vision response', ['raw' => $result]);
            return $this->errorResult('Could not interpret measurement data from the photo. Please try a clearer, full-body photo.');
        }

        $measurements = $parsed['measurements'];
        $quality = $parsed['image_quality'] ?? 'unknown';
        $overallConfidence = $parsed['overall_confidence'] ?? 0.0;

        if ($quality === 'unusable') {
            return [
                'success' => false,
                'error' => 'photo_unusable',
                'message' => 'The uploaded photo cannot be used for measurement extraction.',
                'guidance' => $parsed['quality_notes'] ?? 'Please upload a clear, front-facing, full-body photo in good lighting.',
                'image_quality' => $quality,
                'warnings' => $parsed['warnings'] ?? [],
            ];
        }

        if ($quality === 'poor') {
            return [
                'success' => false,
                'error' => 'photo_poor_quality',
                'message' => 'The photo quality is too low for reliable measurements.',
                'guidance' => $parsed['quality_notes'] ?? 'Please retake the photo in better lighting with the full body visible.',
                'image_quality' => $quality,
                'warnings' => $parsed['warnings'] ?? [],
            ];
        }

        $flattened = [];
        foreach ($measurements as $key => $data) {
            $flattened[$key] = $data['value'] ?? null;
            $flattened[$key . '_confidence'] = $data['confidence'] ?? 0.0;
        }

        return [
            'success' => true,
            'measurements' => $flattened,
            'image_quality' => $quality,
            'quality_notes' => $parsed['quality_notes'] ?? '',
            'confidence' => $overallConfidence,
            'warnings' => $parsed['warnings'] ?? [],
            'body_type_notes' => $parsed['body_type_notes'] ?? '',
        ];
    }

    /**
     * Map raw measurements to standard sizes for a given garment type.
     *
     * @param array  $measurements   Keyed measurement values in inches
     * @param string $garmentType    e.g. tshirt, dress_shirt, pants, suit_jacket, dress
     * @param string $fitPreference  loose|regular|slim
     * @return array{recommended_size: string, size_options: array, explanation: string, fit_notes: string}
     */
    public function matchSizeToMeasurements(array $measurements, string $garmentType, string $fitPreference = 'regular'): array
    {
        if (!$this->isConfigured()) {
            return $this->fallbackSizeMatch($measurements, $garmentType, $fitPreference);
        }

        $sizeChart = self::STANDARD_SIZES[$garmentType] ?? null;
        $chartLabel = $sizeChart ? json_encode($sizeChart) : 'No standard chart available for this garment type.';

        $systemPrompt = <<<PROMPT
You are an expert fashion sizing advisor for Urban Goodz. Match the customer's body measurements to the best standard size for the given garment type.

Garment type: {$garmentType}
Fit preference: {$fitPreference}
Standard size chart reference:
{$chartLabel}

RULES:
- Use the size chart above as the primary guide.
- If the customer's measurements fall between sizes, recommend based on the fit preference:
  - "slim" → recommend the smaller size
  - "regular" → recommend the size that best fits the largest measurement
  - "loose" → recommend the larger size
- Always list the top 2-3 closest sizes with a match score.
- Provide a clear explanation of why the recommended size was chosen.

Return ONLY valid JSON:
{
  "recommended_size": "size_label",
  "match_score": 0.0,
  "size_options": [
    {"size": "label", "match_score": 0.0, "fits": {"measurement_name": "exact|close|tight|loose"}}
  ],
  "explanation": "Why this size was chosen",
  "fit_notes": "Additional fit guidance for the tailor"
}
PROMPT;

        $result = $this->chat($systemPrompt, "Customer measurements: " . json_encode($measurements));

        $parsed = json_decode(trim($result), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsed['recommended_size'])) {
            return ['success' => true] + $parsed;
        }

        return $this->fallbackSizeMatch($measurements, $garmentType, $fitPreference);
    }

    /**
     * Suggest specific tailoring adjustments based on measurements and style notes.
     *
     * @param array  $measurements
     * @param string $garmentType
     * @param string $styleNotes
     * @return array{adjustments: array, priority_notes: string, estimated_alteration_time: string}
     */
    public function suggestGarmentAdjustments(array $measurements, string $garmentType, string $styleNotes = ''): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'adjustments' => [],
                'priority_notes' => 'AI service unavailable — manual tailor review recommended.',
                'estimated_alteration_time' => 'unknown',
            ];
        }

        $styleNotesDisplay = !empty($styleNotes) ? $styleNotes : 'None provided';
        $systemPrompt = <<<PROMPT
You are a master tailor at Urban Goodz. Based on the customer's measurements and the garment type, suggest specific tailoring adjustments.

Garment type: {$garmentType}
Style notes: {$styleNotesDisplay}

Analyze the measurements for:
1. Asymmetries or imbalances between left/right proportions
2. Measurements that suggest a non-standard body shape (e.g., broader shoulders, longer torso)
3. Fit adjustments needed for the specific garment type
4. Hem, sleeve, and length adjustments
5. Ease allowances appropriate for the garment and fit preference

Return ONLY valid JSON:
{
  "adjustments": [
    {
      "area": "body_area_or_garment_section",
      "adjustment": "specific adjustment description",
      "measurements_impacted": ["list of measurement names"],
      "priority": "critical|recommended|optional",
      "notes": "tailor guidance"
    }
  ],
  "priority_notes": "Summary of the most important adjustments",
  "estimated_alteration_time": "quick|moderate|complex",
  "confidence": 0.0
}
PROMPT;

        $result = $this->chat($systemPrompt, "Measurements: " . json_encode($measurements));

        $parsed = json_decode(trim($result), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsed['adjustments'])) {
            return ['success' => true] + $parsed;
        }

        return [
            'success' => true,
            'adjustments' => [],
            'priority_notes' => 'Unable to generate AI adjustments. Manual tailor review needed.',
            'estimated_alteration_time' => 'unknown',
        ];
    }

    /**
     * Generate a persistent customer size profile from extracted measurements.
     *
     * @param array $measurements  Keyed measurement values
     * @return array{profile: array, notes: string, cross_garment_sizes: array}
     */
    public function generateSizeProfile(array $measurements): array
    {
        $crossGarmentSizes = [];

        foreach (['tshirt', 'dress_shirt', 'pants', 'suit_jacket', 'dress'] as $garment) {
            $match = $this->matchSizeToMeasurements($measurements, $garment, 'regular');
            $crossGarmentSizes[$garment] = [
                'recommended_size' => $match['recommended_size'] ?? 'Unknown',
                'match_score' => $match['match_score'] ?? 0,
            ];
        }

        $profile = [
            'measurements' => $measurements,
            'cross_garment_sizes' => $crossGarmentSizes,
            'generated_at' => now()->toIso8601String(),
        ];

        if ($this->isConfigured()) {
            $systemPrompt = <<<'PROMPT'
You are an expert tailor creating a customer size profile for Urban Goodz. Based on the measurements provided, generate a concise size profile that will be stored for future orders.

Analyze body proportions, identify the customer's likely body type, and note any special fitting considerations.

Return ONLY valid JSON:
{
  "body_type": "description",
  "fitting_notes": "concise notes for future garment orders",
  "universal_size_estimate": "estimated universal size (S/M/L/XL or numeric)",
  "special_considerations": ["list of any fitting notes"],
  "notes": "brief profile summary for tailor reference"
}
PROMPT;

            $result = $this->chat($systemPrompt, "Measurements: " . json_encode($measurements));
            $parsed = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['body_type'])) {
                $profile['ai_profile'] = $parsed;
                $profile['notes'] = $parsed['notes'] ?? '';
            } else {
                $profile['notes'] = 'Size profile generated. Manual review recommended.';
            }
        } else {
            $profile['notes'] = 'Size profile generated from measurements. AI profile unavailable (service not configured).';
        }

        return [
            'success' => true,
            'profile' => $profile,
            'cross_garment_sizes' => $crossGarmentSizes,
        ];
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    private function callVisionApi(string $systemPrompt, string $userMessage, string $photoData): ?string
    {
        $imageUrl = $this->buildImageUrl($photoData);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $userMessage],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $imageUrl,
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
                'model' => self::MODEL,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                return $body['choices'][0]['message']['content'] ?? null;
            }

            Log::error('FashionFitAI: Vision API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('FashionFitAI: Vision API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function chat(string $systemPrompt, string $userMessage): ?string
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::MODEL,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                return $body['choices'][0]['message']['content'] ?? null;
            }

            Log::error('FashionFitAI: Chat API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('FashionFitAI: Chat API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildImageUrl(string $data): string
    {
        $trimmed = trim($data);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (preg_match('/^data:image\/(\w+);base64,.+/', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $trimmed)) {
            return 'data:image/jpeg;base64,' . $trimmed;
        }

        return $trimmed;
    }

    private function errorResult(string $message): array
    {
        return [
            'success' => false,
            'error' => 'service_error',
            'message' => $message,
        ];
    }

    /**
     * Offline fallback: score each size in the chart against the provided measurements.
     */
    private function fallbackSizeMatch(array $measurements, string $garmentType, string $fitPreference): array
    {
        $chart = self::STANDARD_SIZES[$garmentType] ?? null;

        if (!$chart) {
            return [
                'success' => true,
                'recommended_size' => 'Unknown',
                'match_score' => 0,
                'size_options' => [],
                'explanation' => "No standard size chart available for garment type '{$garmentType}'.",
                'fit_notes' => 'Manual sizing by tailor required.',
            ];
        }

        $fitOffsets = [
            'loose'  => 1.0,
            'regular'=> 0.0,
            'slim'   => -1.0,
        ];
        $offset = $fitOffsets[$fitPreference] ?? 0;

        $scores = [];

        foreach ($chart as $label => $ranges) {
            $totalScore = 0;
            $count = 0;
            $fits = [];

            foreach ($ranges as $measure => $bounds) {
                $customerVal = $measurements[$measure] ?? null;
                if ($customerVal === null) {
                    continue;
                }

                $adjustedLower = $bounds[0] + $offset;
                $adjustedUpper = $bounds[1] + $offset;
                $midpoint = ($adjustedLower + $adjustedUpper) / 2;
                $halfRange = ($adjustedUpper - $adjustedLower) / 2;

                $distance = abs($customerVal - $midpoint);
                $score = max(0, 1 - ($distance / max($halfRange, 1)));

                if ($customerVal < $adjustedLower) {
                    $fits[$measure] = 'tight';
                } elseif ($customerVal > $adjustedUpper) {
                    $fits[$measure] = 'loose';
                } elseif ($score > 0.8) {
                    $fits[$measure] = 'exact';
                } else {
                    $fits[$measure] = 'close';
                }

                $totalScore += $score;
                $count++;
            }

            $avgScore = $count > 0 ? round($totalScore / $count, 3) : 0;

            $scores[] = [
                'size' => $label,
                'match_score' => $avgScore,
                'fits' => $fits,
            ];
        }

        usort($scores, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

        $best = $scores[0] ?? null;

        return [
            'success' => true,
            'recommended_size' => $best['size'] ?? 'Unknown',
            'match_score' => $best['match_score'] ?? 0,
            'size_options' => array_slice($scores, 0, 3),
            'explanation' => "Size {$best['size']} scored {$best['match_score']} (offline matching — AI unavailable).",
            'fit_notes' => 'Offline fallback matching used. AI-powered sizing with explanations is recommended.',
        ];
    }
}
