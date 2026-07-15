<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\DeliveryMan;
use Illuminate\Support\Facades\Log;

class LoadBoardNLPService
{
    private UrbanGoodzAIService $ai;
    private UrbanGoodzLoadBoardService $loadBoardService;

    public function __construct(UrbanGoodzAIService $ai, UrbanGoodzLoadBoardService $loadBoardService)
    {
        $this->ai = $ai;
        $this->loadBoardService = $loadBoardService;
    }

    /**
     * Parse a single load posting from free-form text.
     */
    public function parseLoadFromText(string $text): array
    {
        if (empty(trim($text))) {
            return $this->emptyParseResult('Empty input text');
        }

        $systemPrompt = <<<'PROMPT'
You are an expert freight load parser for Urban Goodz, a trucking and logistics company.
Extract structured load data from the user's free-form text. Be precise and use standard trucking terminology.

Return ONLY a valid JSON object with these fields:
{
  "equipment_type": "flatbed|reefer|dry_van|step_deck|lowboy|tanker|box_truck|cargo_van|sprinter|car_hauler|null",
  "origin_city": "string|null",
  "origin_state": "two-letter US state|null",
  "origin_zip": "string|null",
  "destination_city": "string|null",
  "destination_state": "two-letter US state|null",
  "destination_zip": "string|null",
  "weight_lbs": number|null,
  "pieces": number|null,
  "commodity_description": "string|null",
  "payout_amount": number|null,
  "rate_per_mile": number|null,
  "load_type": "ftl|ltl|partial|null",
  "is_hazmat": boolean,
  "is_temperature_controlled": boolean,
  "temperature_min_f": number|null,
  "temperature_max_f": number|null,
  "requires_liftgate": boolean,
  "requires_pallet_jack": boolean,
  "is_team_load": boolean,
  "is_expedited": boolean,
  "special_requirements": "string|null",
  "notes": "string|null",
  "deadline": "ISO 8601 date|null",
  "shipper_name": "string|null",
  "confidence": 0.0-1.0,
  "missing_fields": ["list of key fields that could not be extracted"],
  "parse_notes": "brief explanation of what was found and what was unclear"
}

Rules:
- Normalize city names (e.g. "ATL" → "Atlanta", "DFW" → "Dallas").
- Parse weight from "45k lbs", "45,000 lbs", "45000", "45K" etc.
- Parse dates like "Friday", "7/18", "ASAP", "July 18th" into ISO 8601.
- If text says "reefer" or "refrigerated", set is_temperature_controlled=true and try to extract temps.
- If multiple loads are in the text, parse ONLY the first load.
- If rate/budget is mentioned as a range (e.g. "$2500-$3000"), use the lower number as payout_amount.
- confidence should reflect how much data was clearly present vs inferred.
PROMPT;

        $result = $this->ai->chat($systemPrompt, $text);

        return $this->parseAIJsonResponse($result, $text);
    }

    /**
     * Parse a load posting from an email body (common broker email format).
     */
    public function parseLoadFromEmail(string $emailBody, string $fromAddress = ''): array
    {
        if (empty(trim($emailBody))) {
            return $this->emptyParseResult('Empty email body');
        }

        $senderContext = '';
        if (!empty($fromAddress)) {
            $senderContext = "\nThe sender email address is: {$fromAddress}";
        }

        $systemPrompt = <<<'PROMPT'
You are an expert freight email parser for Urban Goodz, a trucking and logistics company.
Broker emails typically contain load postings in a semi-structured format with fields like
lane, equipment, weight, rate, contact info, and special instructions.

Parse the email and return ONLY a valid JSON object:
{
  "sender": {
    "company_name": "string|null",
    "contact_name": "string|null",
    "phone": "string|null",
    "email": "string|null",
    "mc_number": "string|null"
  },
  "booking_instructions": "string|null — any instructions about how to book (e.g. call only, email spot, use portal)",
  "loads": [
    {
      "equipment_type": "flatbed|reefer|dry_van|step_deck|lowboy|tanker|box_truck|cargo_van|sprinter|car_hauler|null",
      "origin_city": "string|null",
      "origin_state": "two-letter US state|null",
      "origin_zip": "string|null",
      "destination_city": "string|null",
      "destination_state": "two-letter US state|null",
      "destination_zip": "string|null",
      "weight_lbs": number|null,
      "pieces": number|null,
      "commodity_description": "string|null",
      "payout_amount": number|null,
      "rate_per_mile": number|null,
      "load_type": "ftl|ltl|partial|null",
      "is_hazmat": boolean,
      "is_temperature_controlled": boolean,
      "temperature_min_f": number|null,
      "temperature_max_f": number|null,
      "requires_liftgate": boolean,
      "requires_pallet_jack": boolean,
      "is_team_load": boolean,
      "is_expedited": boolean,
      "special_requirements": "string|null",
      "notes": "string|null",
      "deadline": "ISO 8601 date|null",
      "load_number": "string|null — broker reference number if present"
    }
  ],
  "confidence": 0.0-1.0,
  "parse_notes": "string|null"
}

Rules:
- Extract sender info from email headers, signature block, or "From:" line.
- Broker emails often have shorthand: "CHI to ATL", "ATL>DFW", "CHI-ATL". Parse these as origin>destination.
- Common abbreviations: "DV" = dry van, "RB" = reefer, "FB" = flatbed, "SD" = step deck.
- If multiple loads are listed, include ALL of them in the "loads" array.
- Extract any booking instructions like "Call for rate", "Email spot", "Portal booking only".
- Parse rates: "$2.50/mi", "2500 flat", "$2.50 per mile", "budget 2800".
PROMPT;

        $result = $this->ai->chat($systemPrompt, $emailBody . $senderContext);

        $parsed = $this->parseAIJsonResponse($result, $emailBody);

        // If AI returned a single load wrapper, normalize to loads array
        if (isset($parsed['equipment_type']) && !isset($parsed['loads'])) {
            $parsed = [
                'sender' => $parsed['sender'] ?? null,
                'booking_instructions' => $parsed['booking_instructions'] ?? null,
                'loads' => [$parsed],
                'confidence' => $parsed['confidence'] ?? 0.5,
                'parse_notes' => $parsed['parse_notes'] ?? null,
            ];
        }

        return $parsed;
    }

    /**
     * Parse multiple load postings from a single text block (e.g. broker daily email).
     */
    public function parseBatchLoads(string $text): array
    {
        if (empty(trim($text))) {
            return ['loads' => [], 'total_found' => 0, 'confidence' => 0, 'parse_notes' => 'Empty input text'];
        }

        $systemPrompt = <<<'PROMPT'
You are an expert freight load parser for Urban Goodz, a trucking and logistics company.
The user is providing a text block that may contain MULTIPLE load postings.
This is common when a broker sends a daily email with many available loads.

Parse EVERY load you can identify and return ONLY a valid JSON object:
{
  "loads": [
    {
      "equipment_type": "flatbed|reefer|dry_van|step_deck|lowboy|tanker|box_truck|cargo_van|sprinter|car_hauler|null",
      "origin_city": "string|null",
      "origin_state": "two-letter US state|null",
      "origin_zip": "string|null",
      "destination_city": "string|null",
      "destination_state": "two-letter US state|null",
      "destination_zip": "string|null",
      "weight_lbs": number|null,
      "pieces": number|null,
      "commodity_description": "string|null",
      "payout_amount": number|null,
      "rate_per_mile": number|null,
      "load_type": "ftl|ltl|partial|null",
      "is_hazmat": boolean,
      "is_temperature_controlled": boolean,
      "temperature_min_f": number|null,
      "temperature_max_f": number|null,
      "requires_liftgate": boolean,
      "requires_pallet_jack": boolean,
      "is_team_load": boolean,
      "is_expedited": boolean,
      "special_requirements": "string|null",
      "notes": "string|null",
      "deadline": "ISO 8601 date|null",
      "load_number": "string|null — broker reference if present",
      "confidence": 0.0-1.0
    }
  ],
  "total_found": number,
  "confidence": 0.0-1.0,
  "parse_notes": "string|null"
}

Rules:
- Each distinct load posting should be its own entry in the "loads" array.
- Loads may be separated by blank lines, dashes, numbers, or "Load 1:", "Load 2:" etc.
- If a line or section cannot be parsed as a load, skip it and note it in parse_notes.
- Normalize city codes: "ATL" = Atlanta, "CHI" = Chicago, "DFW" = Dallas, "LAX" = Los Angeles, etc.
- For each individual load, set its own confidence score.
- Set overall confidence to the average of individual load confidences.
PROMPT;

        $result = $this->ai->chat($systemPrompt, $text);

        $parsed = $this->parseAIJsonResponse($result, $text);

        if (!isset($parsed['loads']) || !is_array($parsed['loads'])) {
            $parsed['loads'] = [];
            $parsed['total_found'] = 0;
        }

        $parsed['total_found'] = count($parsed['loads']);

        return $parsed;
    }

    /**
     * Use AI to rank available drivers for a given load.
     */
    public function matchLoadToDriver(array $loadData, array $drivers = []): array
    {
        if (empty($loadData)) {
            return ['rankings' => [], 'notes' => 'No load data provided'];
        }

        if (empty($drivers)) {
            $drivers = DeliveryMan::where('active', 1)
                ->where('application_status', 'approved')
                ->get()
                ->toArray();
        }

        if (empty($drivers)) {
            return ['rankings' => [], 'notes' => 'No available drivers found'];
        }

        $systemPrompt = <<<'PROMPT'
You are an expert load-dispatch matching AI for Urban Goodz, a trucking and logistics company.
Rank the available drivers for the given load based on these factors (in priority order):

1. **Equipment Match** (weight: 35%) — Does the driver's truck/trailer match the load's equipment needs?
2. **Current Location & Route Proximity** (weight: 25%) — How close is the driver to the load's origin?
3. **Route Preference** (weight: 15%) — Does the lane match the driver's preferred routes or past history?
4. **HOS Compliance** (weight: 15%) — Does the driver have enough Hours of Service remaining?
5. **Historical Performance** (weight: 10%) — On-time rate, customer feedback, damage-free rate.

Return ONLY a valid JSON object:
{
  "rankings": [
    {
      "driver_id": number,
      "driver_name": "string",
      "score": 0.0-1.0,
      "breakdown": {
        "equipment_match": 0.0-1.0,
        "location_proximity": 0.0-1.0,
        "route_preference": 0.0-1.0,
        "hos_compliance": 0.0-1.0,
        "historical_performance": 0.0-1.0
      },
      "reason": "brief explanation of why this driver is ranked here",
      "concerns": "any warnings or concerns, null if none"
    }
  ],
  "recommended_driver_id": number|null,
  "recommendation_reason": "string",
  "notes": "string"
}

Rules:
- If driver data doesn't include certain info, make reasonable assumptions and note them.
- Always recommend the top-ranked driver if score > 0.6.
- If no driver scores above 0.4, set recommended_driver_id to null and explain why.
- Be conservative — a bad match is worse than no match.
PROMPT;

        $context = [
            'load' => $loadData,
            'available_drivers' => $drivers,
        ];

        $result = $this->ai->chat($systemPrompt, "Rank drivers for this load.", $context);

        $parsed = $this->parseAIJsonResponse($result, '');

        if (!isset($parsed['rankings']) || !is_array($parsed['rankings'])) {
            $parsed['rankings'] = [];
            $parsed['recommended_driver_id'] = null;
            $parsed['recommendation_reason'] = 'Unable to generate rankings';
        }

        return $parsed;
    }

    /**
     * Estimate a fair rate for a load based on lane, equipment, weight, and seasonality.
     */
    public function estimateFairRate(array $loadData): array
    {
        if (empty($loadData)) {
            return ['estimate' => null, 'notes' => 'No load data provided'];
        }

        $systemPrompt = <<<'PROMPT'
You are an expert freight rate analyst for Urban Goodz, a trucking and logistics company.
Estimate the fair market rate for the given load based on:

1. **Lane** (origin → destination): High-traffic lanes pay less per mile; deadhead lanes pay more.
2. **Equipment type**: Flatbed and reefer typically command premiums over dry van.
3. **Weight**: Heavy loads (40k+) may command surcharges.
4. **Seasonality**: Produce season (Apr-Oct) raises reefer rates. Holiday season raises all rates.
5. **Special requirements**: Hazmat, team, expedited, liftgate all affect pricing.
6. **Market conditions**: Current supply/demand balance for the lane.

Return ONLY a valid JSON object:
{
  "estimated_rate": number,
  "estimated_rate_per_mile": number,
  "rate_range_low": number,
  "rate_range_high": number,
  "confidence": 0.0-1.0,
  "breakdown": {
    "base_lane_rate": number,
    "equipment_adjustment": number,
    "weight_adjustment": number,
    "seasonal_adjustment": number,
    "special_requirements_adjustment": number
  },
  "market_notes": "string — explanation of the rate estimate and market factors",
  "seasonality_impact": "low|medium|high",
  "recommendation": "string —建议 on whether this load is worth pursuing at various price points"
}

Rules:
- Base your estimates on typical US freight market rates for 2024-2025.
- Average dry van: $1.80-$2.50/mi. Reefer: $2.20-$3.20/mi. Flatbed: $2.00-$3.00/mi.
- These are starting points; adjust for lane, weight, season, and market.
- If the load has a payout_amount already, assess whether it's fair.
- Be honest — don't inflate estimates to make loads seem better than they are.
PROMPT;

        $result = $this->ai->chat($systemPrompt, "Estimate the fair rate for this load.", $loadData);

        $parsed = $this->parseAIJsonResponse($result, '');

        if (!isset($parsed['estimated_rate'])) {
            $parsed['estimated_rate'] = null;
            $parsed['notes'] = 'Unable to estimate rate';
        }

        return $parsed;
    }

    /**
     * Detect if a new load is a duplicate or near-duplicate of existing loads.
     */
    public function detectDuplicates(array $newLoad, array $existingLoads = []): array
    {
        if (empty($newLoad)) {
            return ['is_duplicate' => false, 'matches' => [], 'notes' => 'No load data provided'];
        }

        if (empty($existingLoads)) {
            $existingLoads = UrbanGoodzLoadBoardLoad::where('status', 'available')
                ->where('created_at', '>=', now()->subDays(7))
                ->get()
                ->toArray();
        }

        if (empty($existingLoads)) {
            return ['is_duplicate' => false, 'matches' => [], 'notes' => 'No existing loads to compare against'];
        }

        $systemPrompt = <<<'PROMPT'
You are a freight load deduplication engine for Urban Goodz, a trucking and logistics company.
Determine if the "new load" is a duplicate or near-duplicate of any of the "existing loads".

Two loads are duplicates if they are clearly the same shipment posted by the same or different brokers.
Two loads are near-duplicates if they match on these criteria (with some tolerance):
- Same origin city/state (within 50 miles)
- Same destination city/state (within 50 miles)
- Same equipment type
- Similar weight (within 5,000 lbs)
- Similar rate (within 15%)
- Same or similar deadline

Return ONLY a valid JSON object:
{
  "is_duplicate": boolean,
  "is_near_duplicate": boolean,
  "matches": [
    {
      "existing_load_id": number,
      "match_type": "exact|near|possible",
      "similarity_score": 0.0-1.0,
      "matching_fields": ["list of fields that matched"],
      "differing_fields": ["list of fields that differ"],
      "notes": "explanation of the match"
    }
  ],
  "recommendation": "string — what to do (e.g. 'skip', 'merge data', 'list separately', 'flag for review')",
  "confidence": 0.0-1.0,
  "notes": "string"
}

Rules:
- A match score >= 0.90 is "exact" duplicate.
- A match score 0.70-0.89 is "near" duplicate.
- A match score 0.50-0.69 is "possible" duplicate.
- Below 0.50 is not a match.
- If the loads differ significantly in rate or deadline, they may be different loads on the same lane — mark as "possible" not "near".
- Always provide reasoning for your classification.
PROMPT;

        $context = [
            'new_load' => $newLoad,
            'existing_loads' => array_slice($existingLoads, 0, 50),
        ];

        $result = $this->ai->chat($systemPrompt, "Check this new load against existing loads for duplicates.", $context);

        $parsed = $this->parseAIJsonResponse($result, '');

        if (!isset($parsed['matches']) || !is_array($parsed['matches'])) {
            $parsed['matches'] = [];
        }

        $parsed['is_duplicate'] = $parsed['is_duplicate'] ?? false;
        $parsed['is_near_duplicate'] = $parsed['is_near_duplicate'] ?? false;

        return $parsed;
    }

    /**
     * Safely parse a JSON response from the AI, with fallback handling.
     */
    private function parseAIJsonResponse(string $raw, string $originalInput): array
    {
        $cleaned = trim($raw);

        // Strip markdown code fences if present
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*\n?/i', '', $cleaned);
            $cleaned = preg_replace('/\n?```\s*$/i', '', $cleaned);
            $cleaned = trim($cleaned);
        }

        $json = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        // Attempt to extract JSON from surrounding text
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
        }

        Log::warning('LoadBoardNLP: Failed to parse AI JSON response', [
            'raw' => substr($raw, 0, 500),
            'error' => json_last_error_msg(),
        ]);

        return [
            'error' => 'Failed to parse AI response',
            'raw_response' => substr($raw, 0, 500),
            'confidence' => 0,
            'parse_notes' => 'AI response could not be parsed as structured data',
        ];
    }

    /**
     * Return a standardized empty/error parse result.
     */
    private function emptyParseResult(string $reason): array
    {
        return [
            'equipment_type' => null,
            'origin_city' => null,
            'origin_state' => null,
            'origin_zip' => null,
            'destination_city' => null,
            'destination_state' => null,
            'destination_zip' => null,
            'weight_lbs' => null,
            'pieces' => null,
            'commodity_description' => null,
            'payout_amount' => null,
            'rate_per_mile' => null,
            'load_type' => null,
            'is_hazmat' => false,
            'is_temperature_controlled' => false,
            'temperature_min_f' => null,
            'temperature_max_f' => null,
            'requires_liftgate' => false,
            'requires_pallet_jack' => false,
            'is_team_load' => false,
            'is_expedited' => false,
            'special_requirements' => null,
            'notes' => null,
            'deadline' => null,
            'confidence' => 0,
            'missing_fields' => array_keys(UrbanGoodzLoadBoardLoad::make()->getFillable()),
            'parse_notes' => $reason,
        ];
    }
}
