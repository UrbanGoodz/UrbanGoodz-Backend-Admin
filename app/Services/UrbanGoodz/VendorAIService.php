<?php

namespace App\Services\UrbanGoodz;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderTransaction;
use App\Models\Review;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorAIService
{
    private UrbanGoodzAIService $ai;

    public function __construct(UrbanGoodzAIService $ai)
    {
        $this->ai = $ai;
    }

    public function summarizeOrder(int $orderId): array
    {
        try {
            $order = Order::with(['details.item', 'store', 'customer', 'payments', 'delivery_man'])
                ->findOrFail($orderId);

            $items = $order->details->map(function (OrderDetail $detail) {
                return [
                    'name' => $detail->item->name ?? 'Unknown Item',
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'add_ons' => $detail->total_add_on_price,
                    'variations' => $detail->variation ?? [],
                ];
            })->toArray();

            $context = [
                'order_id' => $order->id,
                'store_name' => $order->store->name ?? 'N/A',
                'order_status' => $order->order_status,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->order_status === 'delivered' ? 'completed' : 'pending',
                'order_type' => $order->order_type,
                'items' => $items,
                'subtotal' => $order->order_amount,
                'delivery_charge' => $order->delivery_charge,
                'tax' => $order->total_tax_amount,
                'discount' => $order->coupon_discount_amount,
                'schedule_at' => $order->schedule_at ?? 'ASAP',
                'special_instructions' => $order->cutlery ? 'Cutlery requested' : 'No special instructions',
                'created_at' => $order->created_at,
                'customer_name' => $order->customer->f_name ?? 'Guest',
            ];

            $systemPrompt = "You are a vendor assistant for Urban Goodz. Generate a clear, concise, vendor-friendly order summary.
Include: order number, items with quantities, special instructions, customer preferences, delivery timeline, and payment status.
Format as a structured summary that a vendor can quickly scan. Be direct and actionable.";
            $userMessage = "Summarize this order for the vendor. Order #{$order->id}.";

            $aiSummary = $this->ai->chat($systemPrompt, $userMessage, $context);

            return [
                'success' => true,
                'order_id' => $order->id,
                'summary' => $aiSummary,
                'raw_data' => $context,
            ];
        } catch (\Exception $e) {
            Log::error("VendorAIService::summarizeOrder failed for order {$orderId}", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to generate order summary.'];
        }
    }

    public function estimatePrepTime(array $items, string $storeType = 'restaurant'): array
    {
        try {
            $context = [
                'items' => $items,
                'store_type' => $storeType,
                'item_count' => count($items),
            ];

            $systemPrompt = "You are a food preparation time estimator for a {$storeType} on Urban Goodz.
Estimate realistic preparation time based on:
- Items ordered and their complexity
- Typical prep times for the store type
- Volume of items
- Any items that require special preparation

Return JSON only:
{
  \"estimated_minutes\": number,
  \"breakdown\": [{\"item\": string, \"prep_minutes\": number}],
  \"confidence\": \"high\"|\"medium\"|\"low\",
  \"notes\": string
}";
            $userMessage = "Estimate preparation time for this order.";

            $result = $this->ai->chat($systemPrompt, $userMessage, $context);
            $json = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['estimated_minutes'])) {
                return ['success' => true, 'estimate' => $json];
            }

            return ['success' => true, 'estimate' => [
                'estimated_minutes' => max(15, count($items) * 8),
                'breakdown' => [],
                'confidence' => 'low',
                'notes' => 'Default estimate applied. AI response could not be parsed.',
                'raw_ai' => $result,
            ]];
        } catch (\Exception $e) {
            Log::error('VendorAIService::estimatePrepTime failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to estimate preparation time.'];
        }
    }

    public function generateVendorAlerts(int $vendorId): array
    {
        try {
            $vendor = Vendor::findOrFail($vendorId);
            $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

            $recentOrders = Order::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->get();

            $pendingOrders = $recentOrders->where('order_status', 'pending');
            $processingOrders = $recentOrders->whereIn('order_status', ['confirmed', 'processing']);
            $canceledOrders = $recentOrders->where('order_status', 'canceled');

            $recentReviews = Review::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(14))
                ->get();

            $lowRatingReviews = $recentReviews->where('rating', '<=', 2);

            $wallet = $vendor->wallet;
            $context = [
                'vendor_id' => $vendorId,
                'store_count' => count($storeIds),
                'total_orders_7d' => $recentOrders->count(),
                'pending_orders' => $pendingOrders->count(),
                'processing_orders' => $processingOrders->count(),
                'canceled_orders_7d' => $canceledOrders->count(),
                'cancellation_rate_7d' => $recentOrders->count() > 0
                    ? round(($canceledOrders->count() / $recentOrders->count()) * 100, 1)
                    : 0,
                'total_reviews_14d' => $recentReviews->count(),
                'low_rating_reviews' => $lowRatingReviews->count(),
                'recent_negative_comments' => $lowRatingReviews->pluck('comment')->filter()->values()->toArray(),
                'wallet_balance' => $wallet->balance ?? 0,
                'order_volume_trend' => $this->getOrderVolumeTrend($storeIds),
            ];

            $systemPrompt = "You are a vendor alert analyst for Urban Goodz. Analyze this vendor's recent data and generate actionable alerts.
Identify: rush orders approaching, low inventory patterns, unusual order volumes, payment delays, customer complaints needing attention.
Return JSON:
{
  \"alerts\": [
    {
      \"priority\": \"critical\"|\"warning\"|\"info\",
      \"category\": string,
      \"title\": string,
      \"description\": string,
      \"recommended_action\": string
    }
  ],
  \"summary\": string
}";
            $userMessage = "Generate alerts for this vendor based on recent activity.";

            $result = $this->ai->chat($systemPrompt, $userMessage, $context);
            $json = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['alerts'])) {
                return ['success' => true, 'alerts' => $json];
            }

            $fallbackAlerts = [];
            if ($pendingOrders->count() > 5) {
                $fallbackAlerts[] = [
                    'priority' => 'warning',
                    'category' => 'rush_orders',
                    'title' => 'High pending order count',
                    'description' => "{$pendingOrders->count()} orders are pending acceptance.",
                    'recommended_action' => 'Review and accept pending orders promptly.',
                ];
            }
            if ($canceledOrders->count() > 3) {
                $fallbackAlerts[] = [
                    'priority' => 'warning',
                    'category' => 'cancellations',
                    'title' => 'Elevated cancellation rate',
                    'description' => "{$canceledOrders->count()} orders canceled in the last 7 days.",
                    'recommended_action' => 'Review cancellation reasons and address recurring issues.',
                ];
            }
            if ($lowRatingReviews->count() > 0) {
                $fallbackAlerts[] = [
                    'priority' => 'critical',
                    'category' => 'customer_complaint',
                    'title' => 'Low rating reviews detected',
                    'description' => "{$lowRatingReviews->count()} reviews rated 2 stars or below in the last 14 days.",
                    'recommended_action' => 'Read and respond to negative reviews. Address customer concerns.',
                ];
            }

            return ['success' => true, 'alerts' => ['alerts' => $fallbackAlerts, 'summary' => 'AI analysis unavailable. Showing rule-based alerts.']];
        } catch (\Exception $e) {
            Log::error("VendorAIService::generateVendorAlerts failed for vendor {$vendorId}", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to generate vendor alerts.'];
        }
    }

    public function analyzeVendorPerformance(int $vendorId): array
    {
        try {
            $vendor = Vendor::findOrFail($vendorId);
            $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

            $ordersLast30 = Order::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->get();

            $totalOrders = $ordersLast30->count();
            $deliveredOrders = $ordersLast30->where('order_status', 'delivered');
            $canceledOrders = $ordersLast30->where('order_status', 'canceled');

            $reviewsLast30 = Review::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->get();

            $avgRating = $reviewsLast30->count() > 0
                ? round($reviewsLast30->avg('rating'), 2)
                : 0;

            $totalRevenue = $deliveredOrders->sum('order_amount');

            $wallet = $vendor->wallet;

            $context = [
                'vendor_id' => $vendorId,
                'period' => 'last_30_days',
                'total_orders' => $totalOrders,
                'delivered_orders' => $deliveredOrders->count(),
                'canceled_orders' => $canceledOrders->count(),
                'cancellation_rate' => $totalOrders > 0 ? round(($canceledOrders->count() / $totalOrders) * 100, 1) : 0,
                'total_revenue' => round($totalRevenue, 2),
                'avg_order_value' => $deliveredOrders->count() > 0 ? round($totalRevenue / $deliveredOrders->count(), 2) : 0,
                'avg_rating' => $avgRating,
                'total_reviews' => $reviewsLast30->count(),
                'orders_per_day' => round($totalOrders / 30, 1),
                'wallet_balance' => $wallet->balance ?? 0,
                'daily_order_breakdown' => $this->getDailyOrderBreakdown($storeIds, 30),
            ];

            $systemPrompt = "You are a vendor performance analyst for Urban Goodz. Analyze this vendor's metrics and provide actionable insights.
Evaluate: order volume, average prep time, cancellation rate, customer ratings, revenue trends.
Return JSON:
{
  \"performance_score\": number (1-100),
  \"rating_assessment\": \"excellent\"|\"good\"|\"needs_improvement\"|\"critical\",
  \"strengths\": [string],
  \"weaknesses\": [string],
  \"recommendations\": [string],
  \"revenue_insights\": string,
  \"summary\": string
}";
            $userMessage = "Analyze this vendor's performance over the last 30 days.";

            $result = $this->ai->chat($systemPrompt, $userMessage, $context);
            $json = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['performance_score'])) {
                return ['success' => true, 'performance' => $json, 'metrics' => $context];
            }

            return ['success' => true, 'performance' => [
                'performance_score' => $this->calculateFallbackScore($context),
                'rating_assessment' => $avgRating >= 4.5 ? 'excellent' : ($avgRating >= 3.5 ? 'good' : ($avgRating >= 2.5 ? 'needs_improvement' : 'critical')),
                'strengths' => [],
                'weaknesses' => [],
                'recommendations' => ['AI analysis unavailable. Please review metrics manually.'],
                'revenue_insights' => 'AI analysis unavailable.',
                'summary' => 'AI analysis could not be parsed. Showing raw metrics.',
                'raw_ai' => $result,
            ], 'metrics' => $context];
        } catch (\Exception $e) {
            Log::error("VendorAIService::analyzeVendorPerformance failed for vendor {$vendorId}", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to analyze vendor performance.'];
        }
    }

    public function suggestVendorPromotions(int $vendorId): array
    {
        try {
            $vendor = Vendor::findOrFail($vendorId);
            $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

            $ordersLast30 = Order::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->with('details.item')
                ->get();

            $itemFrequency = [];
            $ordersLast30->each(function ($order) use (&$itemFrequency) {
                $order->details->each(function ($detail) use (&$itemFrequency) {
                    $name = $detail->item->name ?? 'Unknown';
                    $itemFrequency[$name] = ($itemFrequency[$name] ?? 0) + $detail->quantity;
                });
            });

            arsort($itemFrequency);

            $hourlyDistribution = $ordersLast30->groupBy(function ($order) {
                return Carbon::parse($order->created_at)->format('H');
            })->map->count();

            $context = [
                'vendor_id' => $vendorId,
                'total_orders_30d' => $ordersLast30->count(),
                'top_items' => array_slice($itemFrequency, 0, 10, true),
                'least_popular_items' => array_slice($itemFrequency, -5, 5, true),
                'hourly_order_distribution' => $hourlyDistribution->toArray(),
                'total_revenue_30d' => round($ordersLast30->sum('order_amount'), 2),
                'avg_order_value' => $ordersLast30->count() > 0
                    ? round($ordersLast30->sum('order_amount') / $ordersLast30->count(), 2)
                    : 0,
            ];

            $systemPrompt = "You are a promotions strategist for an Urban Goodz vendor. Based on order history, suggest targeted promotions.
Consider: slow periods, popular items, discount strategies, combo deals, loyalty incentives.
Return JSON:
{
  \"promotions\": [
    {
      \"name\": string,
      \"type\": \"percentage_off\"|\"combo_deal\"|\"free_item\"|\"loyalty_reward\"|\"time_based_discount\",
      \"description\": string,
      \"target_items\": [string],
      \"discount_value\": string,
      \"best_time_to_run\": string,
      \"expected_impact\": string
    }
  ],
  \"general_recommendations\": [string],
  \"summary\": string
}";
            $userMessage = "Suggest promotions for this vendor.";

            $result = $this->ai->chat($systemPrompt, $userMessage, $context);
            $json = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['promotions'])) {
                return ['success' => true, 'promotions' => $json, 'data' => $context];
            }

            return ['success' => true, 'promotions' => [
                'promotions' => [],
                'general_recommendations' => ['AI analysis unavailable. Consider running a percentage-off promotion on top-selling items.'],
                'summary' => 'AI suggestions could not be parsed. Showing default recommendations.',
                'raw_ai' => $result,
            ], 'data' => $context];
        } catch (\Exception $e) {
            Log::error("VendorAIService::suggestVendorPromotions failed for vendor {$vendorId}", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to generate promotion suggestions.'];
        }
    }

    public function generateVendorDailyBrief(int $vendorId): array
    {
        try {
            $vendor = Vendor::findOrFail($vendorId);
            $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

            $today = Carbon::today();
            $tomorrow = Carbon::tomorrow();

            $todaysOrders = Order::whereIn('store_id', $storeIds)
                ->whereDate('created_at', $today)
                ->get();

            $pendingOrders = $todaysOrders->where('order_status', 'pending');
            $processingOrders = $todaysOrders->whereIn('order_status', ['confirmed', 'processing', 'handover']);
            $completedToday = $todaysOrders->where('order_status', 'delivered');

            $scheduledTomorrow = Order::whereIn('store_id', $storeIds)
                ->where('scheduled', 1)
                ->whereBetween('schedule_at', [$today, $tomorrow->endOfDay()])
                ->get();

            $lastWeekSameDay = Order::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subWeek()->startOfDay())
                ->where('created_at', '<', Carbon::now()->subWeek()->endOfDay())
                ->get();

            $wallet = $vendor->wallet;

            $context = [
                'vendor_id' => $vendorId,
                'date' => $today->format('l, F j, Y'),
                'todays_orders_total' => $todaysOrders->count(),
                'pending_orders' => $pendingOrders->count(),
                'processing_orders' => $processingOrders->count(),
                'completed_orders_today' => $completedToday->count(),
                'todays_revenue_so_far' => round($completedToday->sum('order_amount'), 2),
                'scheduled_orders_coming' => $scheduledTomorrow->count(),
                'last_week_same_day_orders' => $lastWeekSameDay->count(),
                'wallet_balance' => $wallet->balance ?? 0,
                'order_type_breakdown' => $todaysOrders->groupBy('order_type')->map->count()->toArray(),
            ];

            $systemPrompt = "You are a daily briefing assistant for an Urban Goodz vendor. Generate a morning briefing.
Include: today's expected orders based on historical patterns, pending deliveries, staff recommendations, inventory alerts, revenue forecast.
Return JSON:
{
  \"greeting\": string,
  \"todays_outlook\": string,
  \"key_metrics\": [{\"label\": string, \"value\": string, \"status\": \"good\"|\"warning\"|\"critical\"}],
  \"action_items\": [string],
  \"revenue_forecast\": string,
  \"staff_recommendations\": [string],
  \"summary\": string
}";
            $userMessage = "Generate today's morning briefing for this vendor.";

            $result = $this->ai->chat($systemPrompt, $userMessage, $context);
            $json = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['greeting'])) {
                return ['success' => true, 'brief' => $json, 'metrics' => $context];
            }

            return ['success' => true, 'brief' => [
                'greeting' => "Good morning! Here's your daily brief for {$today->format('l, F j')}.",
                'todays_outlook' => "{$todaysOrders->count()} orders so far today. {$pendingOrders->count()} pending, {$processingOrders->count()} in progress.",
                'key_metrics' => [
                    ['label' => 'Orders Today', 'value' => (string) $todaysOrders->count(), 'status' => 'good'],
                    ['label' => 'Pending', 'value' => (string) $pendingOrders->count(), 'status' => $pendingOrders->count() > 10 ? 'warning' : 'good'],
                    ['label' => 'Revenue So Far', 'value' => '$' . number_format($completedToday->sum('order_amount'), 2), 'status' => 'good'],
                ],
                'action_items' => $pendingOrders->count() > 0 ? ['Review and accept pending orders'] : ['No pending orders. Monitor for new incoming orders.'],
                'revenue_forecast' => "Yesterday same day had {$lastWeekSameDay->count()} orders.",
                'staff_recommendations' => [],
                'summary' => 'Showing fallback briefing. AI analysis unavailable.',
                'raw_ai' => $result,
            ], 'metrics' => $context];
        } catch (\Exception $e) {
            Log::error("VendorAIService::generateVendorDailyBrief failed for vendor {$vendorId}", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to generate daily briefing.'];
        }
    }

    public function optimizeMenuPricing(int $vendorId): array
    {
        try {
            $vendor = Vendor::findOrFail($vendorId);
            $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

            $ordersLast60 = Order::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(60))
                ->with('details.item')
                ->get();

            $itemStats = [];
            $ordersLast60->each(function ($order) use (&$itemStats) {
                $order->details->each(function ($detail) use (&$itemStats) {
                    $itemId = $detail->item_id;
                    $itemName = $detail->item->name ?? 'Unknown';
                    if (!isset($itemStats[$itemId])) {
                        $itemStats[$itemId] = [
                            'name' => $itemName,
                            'total_quantity_sold' => 0,
                            'total_revenue' => 0,
                            'current_price' => $detail->price,
                            'order_count' => 0,
                        ];
                    }
                    $itemStats[$itemId]['total_quantity_sold'] += $detail->quantity;
                    $itemStats[$itemId]['total_revenue'] += $detail->price * $detail->quantity;
                    $itemStats[$itemId]['order_count']++;
                });
            });

            $reviewsLast60 = Review::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', Carbon::now()->subDays(60))
                ->with('item')
                ->get();

            $itemRatings = $reviewsLast60->groupBy('item_id')->map(function ($reviews, $itemId) use ($itemStats) {
                return [
                    'name' => $itemStats[$itemId]['name'] ?? 'Unknown',
                    'avg_rating' => round($reviews->avg('rating'), 2),
                    'review_count' => $reviews->count(),
                ];
            })->toArray();

            $context = [
                'vendor_id' => $vendorId,
                'period' => 'last_60_days',
                'item_performance' => array_values($itemStats),
                'item_ratings' => array_values($itemRatings),
                'total_revenue_60d' => round($ordersLast60->sum('order_amount'), 2),
                'total_orders_60d' => $ordersLast60->count(),
            ];

            $systemPrompt = "You are a menu pricing optimization expert for an Urban Goodz vendor. Analyze order history and suggest optimal price adjustments.
Consider: item popularity, price elasticity, competitive positioning, profit margins, customer perceived value.
Return JSON:
{
  \"price_adjustments\": [
    {
      \"item_name\": string,
      \"current_price\": number,
      \"suggested_price\": number,
      \"reason\": string,
      \"confidence\": \"high\"|\"medium\"|\"low\",
      \"expected_impact\": string
    }
  ],
  \"general_pricing_insights\": [string],
  \"summary\": string
}";
            $userMessage = "Analyze and suggest optimal menu pricing for this vendor.";

            $result = $this->ai->chat($systemPrompt, $userMessage, $context);
            $json = json_decode(trim($result), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['price_adjustments'])) {
                return ['success' => true, 'pricing' => $json, 'data' => $context];
            }

            return ['success' => true, 'pricing' => [
                'price_adjustments' => [],
                'general_pricing_insights' => ['AI pricing analysis unavailable. Review top-performing items for potential price increases.'],
                'summary' => 'AI analysis could not be parsed. Showing raw data for manual review.',
                'raw_ai' => $result,
            ], 'data' => $context];
        } catch (\Exception $e) {
            Log::error("VendorAIService::optimizeMenuPricing failed for vendor {$vendorId}", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unable to optimize menu pricing.'];
        }
    }

    private function getOrderVolumeTrend(array $storeIds): array
    {
        $thisWeek = Order::whereIn('store_id', $storeIds)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();

        $lastWeek = Order::whereIn('store_id', $storeIds)
            ->where('created_at', '>=', Carbon::now()->subWeek()->startOfWeek())
            ->where('created_at', '<', Carbon::now()->startOfWeek())
            ->count();

        return [
            'this_week' => $thisWeek,
            'last_week' => $lastWeek,
            'trend' => $lastWeek > 0 ? round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1) : 0,
            'direction' => $thisWeek > $lastWeek ? 'up' : ($thisWeek < $lastWeek ? 'down' : 'flat'),
        ];
    }

    private function getDailyOrderBreakdown(array $storeIds, int $days): array
    {
        return Order::whereIn('store_id', $storeIds)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(order_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function calculateFallbackScore(array $metrics): int
    {
        $score = 50;

        if ($metrics['cancellation_rate'] < 5) {
            $score += 15;
        } elseif ($metrics['cancellation_rate'] < 10) {
            $score += 5;
        } else {
            $score -= 10;
        }

        if ($metrics['avg_rating'] >= 4.5) {
            $score += 20;
        } elseif ($metrics['avg_rating'] >= 3.5) {
            $score += 10;
        } elseif ($metrics['avg_rating'] < 2.5) {
            $score -= 15;
        }

        if ($metrics['orders_per_day'] > 10) {
            $score += 10;
        } elseif ($metrics['orders_per_day'] > 5) {
            $score += 5;
        }

        return max(1, min(100, $score));
    }

    /**
     * Real, deterministic catalog issues (out of stock, missing photos,
     * stale listings) — the Vendor AI Assistant screen previously called an
     * endpoint that did not exist anywhere in the backend. No AI narrative
     * here: these are facts about the vendor's own items, not judgment
     * calls, so fabricating an LLM opinion on top would add nothing real.
     */
    public function catalogSuggestions(int $vendorId): array
    {
        $vendor = Vendor::findOrFail($vendorId);
        $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

        $suggestions = [];

        $outOfStock = \App\Models\Item::whereIn('store_id', $storeIds)
            ->where('status', 1)
            ->where('stock', 0)
            ->select('id', 'name')
            ->limit(25)
            ->get();
        foreach ($outOfStock as $item) {
            $suggestions[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'type' => 'out_of_stock',
                'message' => "\"{$item->name}\" is out of stock and still shown as active.",
                'severity' => 'high',
            ];
        }

        $missingImage = \App\Models\Item::whereIn('store_id', $storeIds)
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('image')->orWhere('image', '')->orWhere('image', 'def.png');
            })
            ->select('id', 'name')
            ->limit(25)
            ->get();
        foreach ($missingImage as $item) {
            $suggestions[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'type' => 'missing_image',
                'message' => "\"{$item->name}\" has no product photo.",
                'severity' => 'medium',
            ];
        }

        $staleItemIds = \App\Models\Item::whereIn('store_id', $storeIds)
            ->where('status', 1)
            ->where('created_at', '<=', Carbon::now()->subDays(60))
            ->pluck('id');
        $recentlyOrderedIds = OrderDetail::whereIn('item_id', $staleItemIds)
            ->where('created_at', '>=', Carbon::now()->subDays(60))
            ->distinct()
            ->pluck('item_id');
        $stale = \App\Models\Item::whereIn('id', $staleItemIds->diff($recentlyOrderedIds))
            ->select('id', 'name')
            ->limit(25)
            ->get();
        foreach ($stale as $item) {
            $suggestions[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'type' => 'no_recent_sales',
                'message' => "\"{$item->name}\" has had no orders in 60 days.",
                'severity' => 'low',
            ];
        }

        return ['success' => true, 'suggestions' => $suggestions];
    }

    /**
     * Real, actionable operational items: orders waiting too long for a
     * status update, and customer reviews with no vendor reply. Same
     * fact-based approach as catalogSuggestions() above.
     */
    public function recommendedActions(int $vendorId): array
    {
        $vendor = Vendor::findOrFail($vendorId);
        $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

        $actions = [];

        $stalePending = Order::whereIn('store_id', $storeIds)
            ->where('order_status', 'pending')
            ->where('created_at', '<=', Carbon::now()->subHours(2))
            ->select('id', 'created_at')
            ->limit(25)
            ->get();
        foreach ($stalePending as $order) {
            $actions[] = [
                'type' => 'stale_pending_order',
                'target_id' => $order->id,
                'message' => "Order #{$order->id} has been pending for " . Carbon::parse($order->created_at)->diffForHumans(null, true) . '.',
                'priority' => 'high',
            ];
        }

        $unreplied = Review::whereIn('store_id', $storeIds)
            ->whereNull('reply')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->select('id', 'created_at')
            ->limit(25)
            ->get();
        foreach ($unreplied as $review) {
            $actions[] = [
                'type' => 'unreplied_review',
                'target_id' => $review->id,
                'message' => "Review #{$review->id} from " . Carbon::parse($review->created_at)->diffForHumans() . ' has no reply yet.',
                'priority' => 'medium',
            ];
        }

        return ['success' => true, 'actions' => $actions];
    }

    /**
     * Real wallet/settlement figures from store_wallets — no fabricated
     * numbers. The vendor app renders whatever keys are present, so this
     * intentionally returns a flat map matching what an earnings summary
     * screen needs, not a nested envelope.
     */
    public function settlementMetrics(int $vendorId): array
    {
        $wallet = StoreWallet::where('vendor_id', $vendorId)->first();

        $pendingWithdrawalTotal = DB::table('withdraw_requests')
            ->where('vendor_id', $vendorId)
            ->sum('amount');

        return [
            'success' => true,
            'total_earning' => (float) ($wallet->total_earning ?? 0),
            'total_withdrawn' => (float) ($wallet->total_withdrawn ?? 0),
            'pending_withdraw' => (float) ($wallet->pending_withdraw ?? 0),
            'collected_cash' => (float) ($wallet->collected_cash ?? 0),
            'requested_withdrawal_total' => (float) $pendingWithdrawalTotal,
            'available_balance' => (float) (($wallet->total_earning ?? 0) - ($wallet->total_withdrawn ?? 0) - ($wallet->pending_withdraw ?? 0)),
        ];
    }

    /**
     * Applies one vendor-initiated catalog change from the AI Assistant
     * screen. Ownership is enforced (item must belong to one of this
     * vendor's stores) and only a narrow, safe field set is writable —
     * this endpoint did not exist before, so there was no prior contract
     * to preserve; scoping it tightly here rather than allowing arbitrary
     * mass-assignment is the safe default for a new write endpoint.
     */
    public function applyCatalogUpdate(int $vendorId, int $itemId, array $changes): array
    {
        $vendor = Vendor::findOrFail($vendorId);
        $storeIds = $vendor->stores()->pluck('stores.id')->toArray();

        $item = \App\Models\Item::whereIn('store_id', $storeIds)->findOrFail($itemId);

        $allowed = array_intersect_key($changes, array_flip(['price', 'discount', 'discount_type', 'stock', 'status']));
        if (empty($allowed)) {
            return ['success' => false, 'error' => 'No supported fields in changes.'];
        }

        $item->fill($allowed);
        $item->save();

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'discount' => (float) $item->discount,
                'stock' => (int) $item->stock,
                'status' => (int) $item->status,
            ],
        ];
    }
}
