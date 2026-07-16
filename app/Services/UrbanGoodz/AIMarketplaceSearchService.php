<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AIMarketplaceSearchService
{
    private UrbanGoodzAIService $ai;
    private float $maxSearchRadiusKm = 25.0;

    public function __construct(UrbanGoodzAIService $ai)
    {
        $this->ai = $ai;
    }

    public function search(string $query, array $context = []): array
    {
        $filters = $this->extractFilters($query, $context);

        $results = $this->queryDatabase($filters);

        if ($results->isEmpty()) {
            $results = $this->queryDatabase($filters, broad: true);
        }

        $ranked = $this->rankResults($query, $results->toArray(), $filters, $context);

        return [
            'results' => $ranked['ranked'] ?? [],
            'total' => count($ranked['ranked'] ?? []),
            'query_interpretation' => $ranked['interpretation'] ?? $query,
            'filters_applied' => $filters,
        ];
    }

    public function recommend(string $customerId, string $context = ''): array
    {
        $history = $this->getCustomerHistory($customerId);

        if (empty($history)) {
            return [
                'results' => [],
                'total' => 0,
                'query_interpretation' => 'No order history found for this customer.',
                'filters_applied' => [],
            ];
        }

        $recommendationQuery = $this->buildRecommendationPrompt($history, $context);
        $filters = $this->extractFilters($recommendationQuery, ['recommendation_mode' => true, 'customer_id' => $customerId]);
        $results = $this->queryDatabase($filters);

        $ranked = $this->rankResults($recommendationQuery, $results->toArray(), $filters, $context);

        return [
            'results' => $ranked['ranked'] ?? [],
            'total' => count($ranked['ranked'] ?? []),
            'query_interpretation' => $ranked['interpretation'] ?? 'Personalized recommendations based on your order history.',
            'filters_applied' => $filters,
        ];
    }

    private function extractFilters(string $query, array $context = []): array
    {
        $categoriesList = $this->getAvailableCategories();
        $contextSnippet = '';
        if (!empty($context['latitude']) && !empty($context['longitude'])) {
            $contextSnippet .= "User location: lat={$context['latitude']}, lng={$context['longitude']}.\n";
        }
        if (!empty($context['zone_id'])) {
            $contextSnippet .= "User zone_id: {$context['zone_id']}.\n";
        }
        if (!empty($context['time'])) {
            $contextSnippet .= "Current time: {$context['time']}.\n";
        } elseif (!empty($context['available_time'])) {
            $contextSnippet .= "Requested time: {$context['available_time']}.\n";
        }

        $systemPrompt = <<<PROMPT
You are an intent parser for Urban Goodz, a marketplace with stores, food, and services.
Parse the user's natural language query into structured search filters.

Available categories in the database:
{$categoriesList}

Return ONLY a JSON object with these fields (omit any field not inferable from the query):
{
  "search_text": "string — keyword tokens to match against item/store names and descriptions",
  "budget_max": "number|null — maximum price in dollars",
  "budget_min": "number|null — minimum price in dollars",
  "cuisine_type": "string|null — e.g. 'seafood', 'japanese', 'bbq', 'soul food'",
  "dietary": ["string|null — e.g. 'veg', 'vegan', 'halal', 'gluten_free', 'organic', 'healthy'"],
  "category_id": "number|null — best matching category_id from the list above",
  "category_name": "string|null — human-readable category name if category_id is uncertain",
  "delivery_required": "boolean|null",
  "takeaway_required": "boolean|null",
  "black_owned_preference": "boolean|null — true if user explicitly wants Black-owned businesses",
  "partner_preference": "boolean|null — true if user wants Urban Goodz partners specifically",
  "veg_only": "boolean|null — true if user wants vegetarian only",
  "halal_only": "boolean|null — true if user wants halal only",
  "organic_only": "boolean|null — true if user wants organic only",
  "sort_preference": "string|null — 'price_low', 'price_high', 'rating', 'distance', 'popular', 'delivery_time'",
  "open_now": "boolean|null — true if user wants places open right now"
}
Do not add explanations. Return only valid JSON.
PROMPT;

        $result = $this->ai->chat($systemPrompt, $query, $context);
        $parsed = $this->parseJsonResponse($result);

        return array_filter($parsed, fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function queryDatabase(array $filters, bool $broad = false): Collection
    {
        $itemQuery = DB::table('items')
            ->join('stores', 'items.store_id', '=', 'stores.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('vendors', 'stores.vendor_id', '=', 'vendors.id')
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'items.description as item_description',
                'items.price',
                'items.discount',
                'items.veg',
                'items.organic',
                'items.is_halal',
                'items.avg_rating',
                'items.reviews_count',
                'items.order_count',
                'items.recommended',
                'items.available_time_starts',
                'items.available_time_ends',
                'items.status as item_status',
                'items.is_approved',
                'stores.id as store_id',
                'stores.name as store_name',
                'stores.latitude',
                'stores.longitude',
                'stores.address as store_address',
                'stores.rating as store_rating',
                'stores.delivery as store_delivery',
                'stores.take_away as store_takeaway',
                'stores.veg as store_veg',
                'stores.non_veg as store_non_veg',
                'stores.status as store_status',
                'stores.active as store_active',
                'stores.zone_id',
                'stores.minimum_order',
                'stores.free_delivery',
                'stores.delivery_time',
                'stores.per_km_shipping_charge',
                'stores.minimum_shipping_charge',
                'stores.is_partner',
                'stores.is_claimed',
                'stores.badge_status',
                'stores.business_status',
                'categories.id as category_id',
                'categories.name as category_name'
            )
            ->where('items.status', 1)
            ->where('items.is_approved', 1)
            ->where('stores.status', 1)
            ->where('stores.active', true);

        if (!$broad) {
            if (isset($filters['search_text'])) {
                $searchTerms = $filters['search_text'];
                $itemQuery->where(function ($q) use ($searchTerms) {
                    $q->where('items.name', 'LIKE', "%{$searchTerms}%")
                      ->orWhere('items.description', 'LIKE', "%{$searchTerms}%")
                      ->orWhere('stores.name', 'LIKE', "%{$searchTerms}%")
                      ->orWhere('categories.name', 'LIKE', "%{$searchTerms}%");
                });
            }
        }

        if (isset($filters['budget_max']) && !$broad) {
            $itemQuery->where('items.price', '<=', $filters['budget_max']);
        }
        if (isset($filters['budget_min']) && !$broad) {
            $itemQuery->where('items.price', '>=', $filters['budget_min']);
        }

        if (isset($filters['category_id'])) {
            $itemQuery->where('items.category_id', $filters['category_id']);
        }

        if (isset($filters['delivery_required']) && $filters['delivery_required']) {
            $itemQuery->where('stores.delivery', true);
        }

        if (isset($filters['takeaway_required']) && $filters['takeaway_required']) {
            $itemQuery->where('stores.take_away', true);
        }

        if (isset($filters['veg_only']) && $filters['veg_only']) {
            $itemQuery->where('items.veg', true);
        }

        if (isset($filters['halal_only']) && $filters['halal_only']) {
            $itemQuery->where('items.is_halal', true);
        }

        if (isset($filters['organic_only']) && $filters['organic_only']) {
            $itemQuery->where('items.organic', true);
        }

        if (isset($filters['partner_preference']) && $filters['partner_preference']) {
            $itemQuery->where('stores.is_partner', true);
        }

        if (isset($filters['open_now']) && $filters['open_now']) {
            $now = now();
            $dayOfWeek = $now->dayOfWeek;
            $currentTime = $now->format('H:i:s');
            if (Schema::hasTable('store_schedule')) {
                $itemQuery->whereExists(function ($q) use ($dayOfWeek, $currentTime) {
                    $q->select(DB::raw(1))
                      ->from('store_schedule')
                      ->whereColumn('store_schedule.store_id', 'stores.id')
                      ->where('store_schedule.day', $dayOfWeek)
                      ->where('store_schedule.opening_time', '<=', $currentTime)
                      ->where('store_schedule.closing_time', '>=', $currentTime);
                });
            }
        }

        if (!$broad) {
            $itemQuery->limit(50);
        } else {
            $itemQuery->limit(20);
        }

        $itemQuery->orderByDesc('items.recommended')
                  ->orderByDesc('items.order_count');

        try {
            return collect($itemQuery->get());
        } catch (\Exception $e) {
            Log::error('AIMarketplaceSearchService: Query failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function rankResults(string $query, array $results, array $filters, array $context): array
    {
        if (empty($results)) {
            return ['ranked' => [], 'interpretation' => 'No matching results found for your search.'];
        }

        $latitude = $context['latitude'] ?? null;
        $longitude = $context['longitude'] ?? null;

        foreach ($results as $item) {
            $item->effective_price = $item->price - ($item->discount ?? 0);
            $item->effective_price = max(0, $item->effective_price);
            $item->distance_km = null;
            if ($latitude && $longitude && $item->latitude && $item->longitude) {
                $item->distance_km = $this->haversineDistance(
                    $latitude, $longitude,
                    $item->latitude, $item->longitude
                );
            }
            $item->store_rating_avg = $this->parseStoreRating($item->store_rating);
        }

        $chunkSize = 10;
        $chunked = array_chunk($results, $chunkSize);
        $allRanked = [];

        foreach ($chunked as $chunk) {
            $ranked = $this->aiRankChunk($query, $chunk, $filters);
            $allRanked = array_merge($allRanked, $ranked);
        }

        usort($allRanked, fn($a, $b) => ($b['ai_score'] ?? 0) <=> ($a['ai_score'] ?? 0));

        $topResults = array_slice($allRanked, 0, 20);
        $interpretation = $this->generateInterpretation($query, $filters, $topResults);

        return ['ranked' => $topResults, 'interpretation' => $interpretation];
    }

    private function aiRankChunk(string $query, array $items, array $filters): array
    {
        $summaries = array_map(function ($item, $idx) {
            $priceStr = '$' . number_format($item->effective_price, 2);
            $distStr = $item->distance_km !== null ? number_format($item->distance_km, 1) . 'km away' : 'distance unknown';
            $ratingStr = $item->store_rating_avg !== null ? number_format($item->store_rating_avg, 1) . '/5' : 'no rating';
            $flags = [];
            if ($item->is_partner) $flags[] = 'Urban Goodz Partner';
            if ($item->store_delivery) $flags[] = 'delivers';
            if ($item->veg) $flags[] = 'vegetarian';
            if ($item->organic) $flags[] = 'organic';
            if ($item->is_halal) $flags[] = 'halal';
            if ($item->free_delivery) $flags[] = 'free delivery';
            if ($item->recommended) $flags[] = 'recommended';

            $shortDesc = mb_substr($item->item_description ?? '', 0, 120);
            return "[{$idx}] {$item->item_name} at {$item->store_name} | {$priceStr} | {$distStr} | {$ratingStr} | flags: " . implode(', ', $flags) . " | desc: {$shortDesc}";
        }, $items, array_keys($items));

        $itemList = implode("\n", $summaries);

        $budgetNote = isset($filters['budget_max']) ? "Budget max: \${$filters['budget_max']}." : 'No budget cap.';
        $dietaryNote = '';
        if (!empty($filters['veg_only'])) $dietaryNote .= ' Must be vegetarian.';
        if (!empty($filters['halal_only'])) $dietaryNote .= ' Must be halal.';
        if (!empty($filters['organic_only'])) $dietaryNote .= ' Must be organic.';
        $partnerNote = !empty($filters['partner_preference']) ? ' User prefers Urban Goodz Partner businesses.' : '';

        $systemPrompt = <<<PROMPT
You are the marketplace ranking engine for Urban Goodz.
Given the user query and a list of marketplace items, assign each a relevance score from 0.0 to 1.0.

User query: "{$query}"
{$budgetNote}{$dietaryNote}{$partnerNote}

Ranking criteria (weight each based on the query):
- Query text relevance (does this item/store match what the user asked for?)
- Budget match (is the price within the user's stated budget?)
- Distance proximity (closer is better when location is provided)
- Store rating and item rating
- Delivery availability (if delivery was requested)
- Dietary compliance (veg, halal, organic match)
- Popularity (order_count, reviews_count)
- Partner status (Urban Goodz Partner gets a small boost if preference stated)

Return ONLY a JSON array of objects:
[{"item_id": number, "ai_score": float 0.0-1.0, "reason": "one sentence explaining why this ranks here"}]
Order by highest score first. Return all items. Do not skip any.
PROMPT;

        $result = $this->ai->chat($systemPrompt, $itemList, [
            'item_count' => count($items),
            'filters' => $filters,
        ]);

        $ranked = $this->parseJsonResponse($result);
        if (!is_array($ranked)) {
            $ranked = [];
        }

        $scoreMap = [];
        foreach ($ranked as $entry) {
            if (isset($entry['item_id']) && isset($entry['ai_score'])) {
                $scoreMap[(int) $entry['item_id']] = $entry;
            }
        }

        $output = [];
        foreach ($items as $item) {
            $entry = $scoreMap[$item->item_id] ?? null;
            $output[] = [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'item_description' => $item->item_description,
                'price' => $item->effective_price,
                'original_price' => $item->price,
                'discount' => $item->discount ?? 0,
                'store_id' => $item->store_id,
                'store_name' => $item->store_name,
                'store_address' => $item->store_address,
                'category_name' => $item->category_name,
                'avg_rating' => $item->avg_rating,
                'store_rating' => $item->store_rating_avg,
                'distance_km' => $item->distance_km,
                'delivery_available' => (bool) $item->store_delivery,
                'takeaway_available' => (bool) $item->store_takeaway,
                'is_partner' => (bool) $item->is_partner,
                'badge_status' => $item->badge_status,
                'veg' => (bool) $item->veg,
                'organic' => (bool) $item->organic,
                'halal' => (bool) $item->is_halal,
                'order_count' => $item->order_count,
                'free_delivery' => (bool) $item->free_delivery,
                'minimum_order' => $item->minimum_order,
                'delivery_time' => $item->delivery_time,
                'ai_score' => $entry['ai_score'] ?? 0.5,
                'ai_reason' => $entry['reason'] ?? 'Matched your search criteria.',
            ];
        }

        usort($output, fn($a, $b) => $b['ai_score'] <=> $a['ai_score']);
        return $output;
    }

    private function generateInterpretation(string $query, array $filters, array $results): string
    {
        if (empty($results)) {
            return "I searched for \"{$query}\" but could not find matching results. Try broadening your search.";
        }

        $resultSummary = array_map(function ($r, $i) {
            return ($i + 1) . ". {$r['item_name']} at {$r['store_name']} (\${$r['price']})";
        }, array_slice($results, 0, 5), range(0, min(4, count($results) - 1)));

        $resultList = implode("\n", $resultSummary);

        $systemPrompt = <<<PROMPT
You are a helpful marketplace assistant for Urban Goodz.
Generate a brief, friendly interpretation of the search results.
State what was searched, how many results were found, and highlight the top 2-3 options.
Keep it under 3 sentences. Be conversational and helpful.
PROMPT;

        $context = [
            'query' => $query,
            'filters' => $filters,
            'total_results' => count($results),
            'top_results' => $resultList,
        ];

        return $this->ai->chat($systemPrompt, "Interpret these search results for the customer.", $context);
    }

    private function getCustomerHistory(string $customerId): array
    {
        if (!Schema::hasTable('orders') || !Schema::hasTable('order_details')) {
            return [];
        }

        try {
            $history = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('items', 'order_details.item_id', '=', 'items.id')
                ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                ->leftJoin('stores', 'items.store_id', '=', 'stores.id')
                ->where('orders.user_id', $customerId)
                ->select(
                    'items.name as item_name',
                    'items.price',
                    'items.category_id',
                    'categories.name as category_name',
                    'stores.name as store_name',
                    'stores.id as store_id',
                    'order_details.quantity',
                    'orders.created_at as ordered_at'
                )
                ->orderByDesc('orders.created_at')
                ->limit(30)
                ->get()
                ->toArray();

            return $history;
        } catch (\Exception $e) {
            Log::error('AIMarketplaceSearchService: Failed to get customer history', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function buildRecommendationPrompt(array $history, string $context): string
    {
        $historySummary = array_map(function ($h) {
            $categoryName = $h->category_name ?? 'N/A';
            return "{$h->item_name} ({$categoryName}) from {$h->store_name} - \${$h->price} x{$h->quantity}";
        }, $history);

        $historyList = implode("\n", $historySummary);

        $systemPrompt = <<<PROMPT
You are Urban Goodz's recommendation engine.
Given a customer's order history, generate a natural language search query that captures their preferences.
Consider: favorite categories, price range, preferred stores, order frequency, dietary patterns.

Order history:
{$historyList}

Additional context: {$context}

Return ONLY a JSON object:
{
  "suggested_query": "natural language search query capturing their preferences",
  "reasoning": "one sentence explaining why this query represents their taste"
}
PROMPT;

        $result = $this->ai->chat($systemPrompt, "Generate a recommendation query for this customer.");
        $parsed = $this->parseJsonResponse($result);

        return $parsed['suggested_query'] ?? 'popular items near me';
    }

    private function getAvailableCategories(): string
    {
        if (!Schema::hasTable('categories')) {
            return 'Categories table not available.';
        }

        try {
            $categories = DB::table('categories')
                ->where('status', 1)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            if ($categories->isEmpty()) {
                return 'No active categories found.';
            }

            return $categories->map(fn($c) => "{$c->id}: {$c->name}")->implode("\n");
        } catch (\Exception $e) {
            return 'Categories table not available.';
        }
    }

    private function haversineDistance(float $lat1, float $lng1, $lat2, $lng2): float
    {
        $lat1 = (float) $lat1;
        $lng1 = (float) $lng1;
        $lat2 = (float) $lat2;
        $lng2 = (float) $lng2;

        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }

    private function parseStoreRating($ratingValue): ?float
    {
        if (is_null($ratingValue)) {
            return null;
        }

        if (is_array($ratingValue)) {
            $ratings = $ratingValue;
        } elseif (is_string($ratingValue)) {
            $decoded = json_decode($ratingValue, true);
            $ratings = is_array($decoded) ? $decoded : null;
        } else {
            return null;
        }

        if (empty($ratings)) {
            return null;
        }

        if (isset($ratings[0])) {
            return (float) $ratings[0];
        }

        $total = 0;
        $count = 0;
        foreach ($ratings as $star => $num) {
            $total += (int) $star * (int) $num;
            $count += (int) $num;
        }

        return $count > 0 ? round($total / $count, 1) : null;
    }

    private function parseJsonResponse(string $raw): mixed
    {
        $trimmed = trim($raw);

        $start = strpos($trimmed, '[');
        $end = strrpos($trimmed, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $jsonStr = substr($trimmed, $start, $end - $start + 1);
            $decoded = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $jsonStr = substr($trimmed, $start, $end - $start + 1);
            $decoded = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return null;
    }
}
