<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Log;

class OrderAnywhereNLPService
{
    public function __construct(
        private readonly UrbanGoodzAIService $ai
    ) {}

    public function parseFromText(string $text, array $context = []): array
    {
        if (!$this->ai->isConfigured()) {
            return $this->emptyResult('AI service is not configured.');
        }

        $systemPrompt = $this->buildParseSystemPrompt();
        $userMessage = $this->buildParseUserMessage($text, $context);

        $raw = $this->ai->chat($systemPrompt, $userMessage);
        $parsed = $this->decodeJson($raw);

        if ($parsed === null) {
            Log::warning('OrderAnywhereNLP: Failed to parse AI response', ['raw' => $raw]);
            return $this->emptyResult('Could not parse AI response.');
        }

        $parsed = $this->normalizeParsedFields($parsed);
        $missing = $this->identifyMissingFields($parsed);
        $confidence = (float) ($parsed['confidence'] ?? 0.0);
        $prompts = $this->generateFollowUpPrompts($missing);

        return [
            'concierge' => [
                'persona' => 'monique',
                'name' => 'Skylar',
                'title' => 'Urban Goodz AI Concierge',
                'voice_id' => '03vEurziQfq3V8WZhQvn',
                'opening_phrase' => "How you doin'? What's GOOD? Tell Skylar what you need from anywhere!",
                'search_phrase' => "Hold on, baby. Let me see what I can put together for you right quick...",
            ],
            'parsed' => $this->mapToRequestFields($parsed),
            'missing' => $missing,
            'confidence' => $confidence,
            'follow_up_prompts' => $prompts,
            'raw_extraction' => $parsed,
        ];
    }

    public function suggestSubstitutions(array $items, string $storeName): array
    {
        if (!$this->ai->isConfigured()) {
            return ['substitutions' => [], 'notes' => 'AI service is not configured.'];
        }

        $systemPrompt = "You are Skylar, the official Urban Goodz AI Concierge — a confident, stylish, charismatic Southern Black woman who brings Urban Goodz to life.
The customer wants items from \"{$storeName}\" through Order Anywhere, but some items may be unavailable or the store may not carry them.

Given the requested items, suggest realistic alternative items and alternative stores in your iconic warm, sassy, helpful Skylar voice.
Consider:
- Similar products at the same store
- Equivalent products at nearby/alternative stores
- Common brand substitutions
- Budget-friendly alternatives
- Premium alternatives if the original is budget-tier

Return ONLY a JSON object with this exact structure:
{
  \"substitutions\": [
    {
      \"original_item\": \"string\",
      \"suggested_item\": \"string\",
      \"suggested_store\": \"string\",
      \"reason\": \"string\",
      \"estimated_price_diff\": \"string\"
    }
  ],
  \"alternative_stores\": [
    {
      \"store_name\": \"string\",
      \"why\": \"string\"
    }
  ],
  \"notes\": \"string\"
}
No explanation outside the JSON.";

        $userMessage = "Requested items:\n" . json_encode($items, JSON_PRETTY_PRINT);

        $raw = $this->ai->chat($systemPrompt, $userMessage);
        $result = $this->decodeJson($raw);

        if ($result === null) {
            return ['substitutions' => [], 'alternative_stores' => [], 'notes' => 'Could not generate suggestions.'];
        }

        return $result;
    }

    public function refineWithContext(string $text, array $existingParsed, array $customerResponses): array
    {
        $context = array_merge($existingParsed, ['customer_clarifications' => $customerResponses]);
        return $this->parseFromText($text, $context);
    }

    private function buildParseSystemPrompt(): string
    {
        return <<<'PROMPT'
You are Skylar, the official Urban Goodz AI Concierge and Order Anywhere specialist.
A customer wants to request an item from any store and have it delivered via Order Anywhere.

Extract structured order information from the customer's natural language message.
You must extract every detail the customer mentions and infer what you can while keeping Skylar's attentive concierge standard.

Return ONLY a JSON object with this exact structure — no markdown, no explanation:
{
  "store_name": "string or null",
  "store_address_or_website": "string or null",
  "items": [
    {
      "name": "string",
      "quantity": number,
      "size": "string or null",
      "color": "string or null",
      "notes": "string or null"
    }
  ],
  "quantity": number or null,
  "budget_estimate": number or null,
  "substitutions_allowed": true or false or null,
  "deadline": "ISO 8601 datetime string or null",
  "delivery_address": "string or null",
  "special_instructions": "string or null",
  "customer_name": "string or null",
  "customer_phone": "string or null",
  "confidence": 0.0 to 1.0
}

Rules:
- confidence reflects how certain you are about the extracted fields (1.0 = every field clear, <0.5 = mostly vague)
- If the customer says "no substitutions" set substitutions_allowed to false
- If they say "I'm flexible" or "anything is fine" set it to true
- quantity should be the total item count if items array is empty, otherwise use items array
- deadline should be an ISO 8601 string. Infer date from context if possible.
- If the customer mentions "ASAP" or "right away", set deadline to null with special_instructions noting urgency
- Never fabricate details the customer did not provide — use null for unknown fields
PROMPT;
    }

    private function buildParseUserMessage(string $text, array $context): string
    {
        $message = "Customer request:\n\"{$text}\"";

        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $message .= "\n\nAdditional context:\n{$contextStr}";
        }

        return $message;
    }

    private function normalizeParsedFields(array $parsed): array
    {
        $parsed['store_name'] = $parsed['store_name'] ?? null;
        $parsed['store_address_or_website'] = $parsed['store_address_or_website'] ?? null;
        $parsed['items'] = $parsed['items'] ?? [];
        $parsed['quantity'] = $parsed['quantity'] ?? null;
        $parsed['budget_estimate'] = $parsed['budget_estimate'] ?? null;
        $parsed['substitutions_allowed'] = $parsed['substitutions_allowed'] ?? null;
        $parsed['deadline'] = $parsed['deadline'] ?? null;
        $parsed['delivery_address'] = $parsed['delivery_address'] ?? null;
        $parsed['special_instructions'] = $parsed['special_instructions'] ?? null;
        $parsed['customer_name'] = $parsed['customer_name'] ?? null;
        $parsed['customer_phone'] = $parsed['customer_phone'] ?? null;
        $parsed['confidence'] = max(0.0, min(1.0, (float) ($parsed['confidence'] ?? 0.0)));

        if (!empty($parsed['items']) && empty($parsed['quantity'])) {
            $parsed['quantity'] = array_sum(array_column($parsed['items'], 'quantity'));
        }

        return $parsed;
    }

    private function identifyMissingFields(array $parsed): array
    {
        $missing = [];
        $critical = [
            'store_name' => 'store_name',
            'items' => 'items',
        ];

        $important = [
            'delivery_address' => 'delivery_address',
            'quantity' => 'quantity',
        ];

        foreach ($critical as $field => $label) {
            if ($this->isBlank($parsed[$field] ?? null)) {
                $missing[] = [
                    'field' => $label,
                    'severity' => 'critical',
                    'message' => $this->criticalFieldMessage($label),
                ];
            }
        }

        foreach ($important as $field => $label) {
            if ($this->isBlank($parsed[$field] ?? null)) {
                $missing[] = [
                    'field' => $label,
                    'severity' => 'important',
                    'message' => $this->importantFieldMessage($label),
                ];
            }
        }

        return $missing;
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);
        if (is_numeric($value)) return $value <= 0;
        return false;
    }

    private function criticalFieldMessage(string $field): string
    {
        return match ($field) {
            'store_name' => "Baby, which store you looking to order from?",
            'items' => "What you looking for today? Tell Skylar what items you need!",
            default => "Please provide: {$field}",
        };
    }

    private function importantFieldMessage(string $field): string
    {
        return match ($field) {
            'delivery_address' => "Where am I sending this to, honey? What's the drop-off address?",
            'quantity' => "How many of each item are we putting together for you right quick?",
            default => "Could you provide: {$field}?",
        };
    }

    private function generateFollowUpPrompts(array $missing): array
    {
        $prompts = [];

        foreach ($missing as $item) {
            $prompts[] = $item['message'];
        }

        return $prompts;
    }

    private function mapToRequestFields(array $parsed): array
    {
        $itemDetails = '';
        if (!empty($parsed['items'])) {
            $parts = [];
            foreach ($parsed['items'] as $item) {
                $line = $item['name'] ?? 'Unknown item';
                $qty = $item['quantity'] ?? 1;
                $line .= " x{$qty}";
                if (!empty($item['size'])) $line .= " ({$item['size']})";
                if (!empty($item['color'])) $line .= " [{$item['color']}]";
                if (!empty($item['notes'])) $line .= " — {$item['notes']}";
                $parts[] = $line;
            }
            $itemDetails = implode("\n", $parts);
        }

        $requestDetails = $parsed['special_instructions'] ?? '';
        if (!empty($parsed['deadline'])) {
            $deadline = $parsed['deadline'];
            try {
                $deadline = \Carbon\Carbon::parse($deadline)->format('M j, Y g:i A');
            } catch (\Exception) {
                // keep as-is
            }
            $requestDetails = ($requestDetails ? $requestDetails . "\n" : '') . "Deadline: {$deadline}";
        }
        if (isset($parsed['substitutions_allowed'])) {
            $subNote = $parsed['substitutions_allowed'] ? 'Substitutions allowed' : 'No substitutions';
            $requestDetails = ($requestDetails ? $requestDetails . "\n" : '') . $subNote;
        }

        $data = [
            'store_vendor_name' => $parsed['store_name'] ?? null,
            'store_vendor_address_or_website' => $parsed['store_address_or_website'] ?? null,
            'request_details' => trim($requestDetails) ?: null,
            'item_details' => $itemDetails ?: null,
            'quantity' => $parsed['quantity'] ?? null,
            'budget_estimate' => $parsed['budget_estimate'] ?? null,
            'customer_name' => $parsed['customer_name'] ?? null,
            'customer_phone' => $parsed['customer_phone'] ?? null,
        ];

        return array_filter($data, fn($v) => $v !== null);
    }

    private function decodeJson(string $raw): ?array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```json\s*/i', '', $cleaned);
        $cleaned = preg_replace('/```\s*$/', '', $cleaned);

        $json = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        return null;
    }

    private function emptyResult(string $reason): array
    {
        return [
            'parsed' => [],
            'missing' => [],
            'confidence' => 0.0,
            'follow_up_prompts' => [],
            'raw_extraction' => null,
            'error' => $reason,
        ];
    }
}
