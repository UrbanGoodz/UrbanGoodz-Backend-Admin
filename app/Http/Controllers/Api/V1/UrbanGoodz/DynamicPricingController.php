<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Review;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\UrbanGoodz\VendorAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicPricingController extends Controller
{
    public function __construct(
        private VendorAIService $vendorAI
    ) {}

    // ─── PRICE RECOMMENDATIONS ─────────────────────────────────────────

    public function recommendPrices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'min_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_price_increase_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'competitor_analysis' => ['nullable', 'boolean'],
        ]);

        $vendor = Vendor::with('stores')->findOrFail($data['vendor_id']);

        // Check if vendor has dynamic pricing enabled
        $settings = $vendor->settings ?? [];
        if (!($settings['dynamic_pricing_enabled'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Dynamic pricing is not enabled for this vendor. Enable in settings.',
            ], 403);
        }

        $storeIds = $vendor->stores->pluck('id')->toArray();

        // Get item performance data (last 60 days)
        $ordersLast60 = Order::whereIn('store_id', $storeIds)
            ->where('created_at', '>=', now()->subDays(60))
            ->where('order_status', 'delivered')
            ->with('details.item')
            ->get();

        $itemStats = [];
        foreach ($ordersLast60 as $order) {
            foreach ($order->details as $detail) {
                $itemId = $detail->item_id;
                if (!isset($itemStats[$itemId])) {
                    $itemStats[$itemId] = [
                        'name' => $detail->item->name ?? 'Unknown',
                        'current_price' => $detail->price,
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'order_count' => 0,
                    ];
                }
                $itemStats[$itemId]['total_quantity'] += $detail->quantity;
                $itemStats[$itemId]['total_revenue'] += $detail->price * $detail->quantity;
                $itemStats[$itemId]['order_count']++;
            }
        }

        // Get reviews for rating context
        $reviewStats = Review::whereIn('store_id', $storeIds)
            ->where('created_at', '>=', now()->subDays(60))
            ->groupBy('item_id')
            ->selectRaw('item_id, AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->get()
            ->keyBy('item_id');

        // Build context for AI
        $itemsContext = array_map(function ($stats, $itemId) use ($reviewStats) {
            $review = $reviewStats->get($itemId);
            return [
                'item_id' => $itemId,
                'name' => $stats['name'],
                'current_price' => $stats['current_price'],
                'quantity_sold' => $stats['total_quantity'],
                'revenue' => $stats['total_revenue'],
                'orders' => $stats['order_count'],
                'avg_rating' => $review->avg_rating ?? null,
                'review_count' => $review->review_count ?? 0,
            ];
        }, $itemStats, array_keys($itemStats));

        $constraints = [
            'min_margin_percent' => $data['min_margin_percent'] ?? 20,
            'max_price_increase_percent' => $data['max_price_increase_percent'] ?? 15,
            'min_price_decrease_percent' => $data['max_price_increase_percent'] ?? 10, // symmetric
        ];

        $result = $this->vendorAI->optimizeMenuPricing($vendor->id);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'AI pricing optimization failed',
                'fallback' => $result,
            ], 500);
        }

        // Filter and validate recommendations against constraints
        $validated = [];
        foreach ($result['pricing']['price_adjustments'] ?? [] as $adj) {
            $currentPrice = $adj['current_price'];
            $suggestedPrice = $adj['suggested_price'];
            $changePercent = (($suggestedPrice - $currentPrice) / $currentPrice) * 100;

            $maxIncrease = $constraints['max_price_increase_percent'];
            $maxDecrease = $constraints['max_price_increase_percent']; // using same for decrease
            $minMargin = $constraints['min_margin_percent'];

            // Check constraints
            if ($changePercent > $maxIncrease) {
                $suggestedPrice = $currentPrice * (1 + $maxIncrease / 100);
                $adj['constrained'] = true;
                $adj['constraint_reason'] = "Increase capped at {$maxIncrease}%";
            } elseif ($changePercent < -$maxDecrease) {
                $suggestedPrice = $currentPrice * (1 - $maxDecrease / 100);
                $adj['constrained'] = true;
                $adj['constraint_reason'] = "Decrease capped at {$maxDecrease}%";
            }

            // Check margin (assuming cost = 60% of current price as baseline)
            $estimatedCost = $currentPrice * 0.6;
            $newMargin = (($suggestedPrice - $estimatedCost) / $suggestedPrice) * 100;
            if ($newMargin < $minMargin) {
                $suggestedPrice = $estimatedCost / (1 - $minMargin / 100);
                $adj['constrained'] = true;
                $adj['constraint_reason'] = "Minimum margin {$minMargin}% enforced";
            }

            $validated[] = array_merge($adj, [
                'suggested_price' => round($suggestedPrice, 2),
                'price_change_percent' => round((($suggestedPrice - $currentPrice) / $currentPrice) * 100, 1),
                'estimated_margin_percent' => round($newMargin, 1),
            ]);
        }

        return response()->json([
            'success' => true,
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'recommendations' => $validated,
            'constraints_applied' => $constraints,
            'summary' => $result['pricing']['summary'] ?? null,
            'insights' => $result['pricing']['general_pricing_insights'] ?? [],
            'generated_at' => now()->toISOString(),
        ]);
    }

    // ─── PRICE CHANGE SIMULATION ───────────────────────────────────────

    public function simulatePriceChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'new_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);

        // Get historical data for this item
        $orderDetails = OrderDetail::where('item_id', $data['item_id'])
            ->whereHas('order', fn($q) => $q->whereIn('store_id', $vendor->stores->pluck('id')))
            ->whereHas('order', fn($q) => $q->where('created_at', '>=', now()->subDays(90)))
            ->get();

        $currentPrice = $orderDetails->first()?->price ?? 0;
        $newPrice = $data['new_price'];

        if ($currentPrice <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot find current price for item',
            ], 404);
        }

        $changePercent = (($newPrice - $currentPrice) / $currentPrice) * 100;

        // Simple elasticity model (in production, use ML model)
        $elasticity = -1.5; // typical price elasticity for food
        $quantityChangePercent = $elasticity * ($changePercent / 100);

        $historicalQuantity = $orderDetails->sum('quantity');
        $historicalRevenue = $orderDetails->sum(fn($d) => $d->price * $d->quantity);
        $projectedQuantity = max(0, $historicalQuantity * (1 + $quantityChangePercent));
        $projectedRevenue = $projectedQuantity * $newPrice;

        // Get reviews for sentiment
        $reviews = Review::where('item_id', $data['item_id'])
            ->where('created_at', '>=', now()->subDays(90))
            ->get();

        $avgRating = $reviews->avg('rating') ?? 0;
        $reviewCount = $reviews->count();

        // AI analysis of price change impact
        $analysis = $this->vendorAI->optimizeMenuPricing($vendor->id);

        return response()->json([
            'success' => true,
            'simulation' => [
                'item_id' => $data['item_id'],
                'current_price' => $currentPrice,
                'proposed_price' => $newPrice,
                'price_change_percent' => round($changePercent, 1),
                'current_metrics' => [
                    'quantity_90d' => $historicalQuantity,
                    'revenue_90d' => $historicalRevenue,
                    'avg_price' => $currentPrice,
                ],
                'projected_metrics' => [
                    'quantity_90d' => round($projectedQuantity),
                    'revenue_90d' => round($projectedRevenue, 2),
                    'revenue_change_percent' => $historicalRevenue > 0
                        ? round((($projectedRevenue - $historicalRevenue) / $historicalRevenue) * 100, 1)
                        : 0,
                ],
                'elasticity_assumptions' => [
                    'price_elasticity' => $elasticity,
                    'note' => 'Based on typical food service elasticity. Actual results may vary.',
                ],
                'risk_factors' => [
                    'rating_impact' => $avgRating > 4.0 ? 'low' : ($avgRating > 3.5 ? 'medium' : 'high'),
                    'review_volume' => $reviewCount > 20 ? 'high' : 'low',
                    'price_sensitivity' => abs($changePercent) > 15 ? 'high' : 'moderate',
                ],
                'recommendation' => $this->getPriceChangeRecommendation($changePercent, $projectedRevenue, $historicalRevenue),
            ],
        ]);
    }

    private function getPriceChangeRecommendation(float $changePercent, float $projected, float $current): string
    {
        if ($changePercent > 10 && $projected < $current * 0.9) {
            return 'NOT RECOMMENDED: Price increase likely to reduce revenue significantly';
        }
        if ($changePercent > 5 && $projected < $current) {
            return 'CAUTION: Small revenue decrease expected. Consider smaller increase or bundle deal.';
        }
        if ($changePercent > 0 && $projected > $current) {
            return 'RECOMMENDED: Price increase projected to increase revenue';
        }
        if ($changePercent < -5 && $projected > $current * 1.1) {
            return 'RECOMMENDED: Price decrease projected to significantly increase revenue';
        }
        if ($changePercent < 0 && $projected > $current) {
            return 'RECOMMENDED: Strategic price decrease to drive volume';
        }
        return 'NEUTRAL: Minimal revenue impact expected';
    }

    // ─── PRICE HISTORY ────────────────────────────────────────────────

    public function getPriceHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);
        $storeIds = $vendor->stores->pluck('id');

        $query = OrderDetail::whereIn('order_id', function ($q) use ($storeIds) {
            $q->select('id')->from('orders')->whereIn('store_id', $storeIds);
        });

        if ($data['item_id'] ?? false) {
            $query->where('item_id', $data['item_id']);
        }

        $days = $data['days'] ?? 90;
        $query->where('created_at', '>=', now()->subDays($days));

        $history = $query->selectRaw('
            item_id,
            DATE(created_at) as date,
            AVG(price) as avg_price,
            MIN(price) as min_price,
            MAX(price) as max_price,
            SUM(quantity) as total_quantity,
            COUNT(DISTINCT order_id) as order_count
        ')
        ->groupBy('item_id', 'date')
        ->orderBy('date')
        ->get()
        ->get()
        ->groupBy('item_id')
        ->map(fn($items) => $items->map(fn($h) => [
            'date' => $h->date,
            'avg_price' => round($h->avg_price, 2),
            'min_price' => round($h->min_price, 2),
            'max_price' => round($h->max_price, 2),
            'quantity' => $h->total_quantity,
            'orders' => $h->order_count,
        ])->values());

        return response()->json([
            'success' => true,
            'vendor_id' => $vendor->id,
            'period_days' => $days,
            'history' => $history,
        ]);
    }

    // ─── ROLLBACK ──────────────────────────────────────────────────────

    public function rollbackPrice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'target_price' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string'],
        ]);

        // In production, this would update the item price in the store system
        // For now, we log the rollback request
        $vendor = Vendor::findOrFail($data['vendor_id']);

        \DB::table('urban_goodz_price_rollbacks')->insert([
            'vendor_id' => $data['vendor_id'],
            'item_id' => $data['item_id'],
            'target_price' => $data['target_price'],
            'reason' => $data['reason'] ?? 'Manual rollback via AI pricing',
            'requested_by' => auth('vendor')->id() ?? auth('admin')->id() ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Price rollback request submitted for approval',
            'rollback_id' => 'RB-' . now()->format('YmdHis') . '-' . random_int(1000, 9999),
        ]);
    }
}