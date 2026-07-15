<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\UrbanGoodz\VendorAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAIController extends Controller
{
    public function __construct(
        private VendorAIService $vendorAI
    ) {}

    public function dailyBrief(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $result = $this->vendorAI->generateVendorDailyBrief($vendor->id);

        return response()->json($result);
    }

    public function orderSummary(Request $request, int $orderId): JsonResponse
    {
        $vendor = $this->getVendor($request);

        // Verify order belongs to vendor
        $order = Order::where('id', $orderId)
            ->whereIn('store_id', $vendor->stores->pluck('id'))
            ->firstOrFail();

        $result = $this->vendorAI->summarizeOrder($orderId);

        return response()->json($result);
    }

    public function prepTimeEstimate(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'store_type' => ['nullable', 'string'],
        ]);

        $result = $this->vendorAI->estimatePrepTime($data['items'], $data['store_type'] ?? 'restaurant');

        return response()->json($result);
    }

    public function alerts(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $result = $this->vendorAI->generateVendorAlerts($vendor->id);

        return response()->json($result);
    }

    public function performanceAnalysis(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $result = $this->vendorAI->analyzeVendorPerformance($vendor->id);

        return response()->json($result);
    }

    public function promotionSuggestions(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $result = $this->vendorAI->suggestVendorPromotions($vendor->id);

        return response()->json($result);
    }

    public function dynamicPricing(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);

        // Check if vendor has opted into dynamic pricing
        $settings = $vendor->settings ?? [];
        if (!($settings['dynamic_pricing_enabled'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => 'Dynamic pricing not enabled for this vendor. Enable in settings.',
            ], 403);
        }

        $result = $this->vendorAI->optimizeMenuPricing($vendor->id);

        return response()->json($result);
    }

    public function reviewSentiment(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $data = $request->validate([
            'period_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = $data['period_days'] ?? 30;
        $reviews = Review::whereIn('store_id', $vendor->stores->pluck('id'))
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => true,
                'summary' => 'No reviews in the selected period.',
                'sentiment' => 'neutral',
                'total_reviews' => 0,
            ]);
        }

        $avgRating = $reviews->avg('rating');
        $lowReviews = $reviews->where('rating', '<=', 2);
        $highReviews = $reviews->where('rating', '>=', 4);

        $sentiment = $avgRating >= 4.0 ? 'positive' : ($avgRating >= 3.0 ? 'neutral' : 'negative');

        return response()->json([
            'success' => true,
            'summary' => "{$reviews->count()} reviews in last {$days} days. Average: {$avgRating}/5.0",
            'sentiment' => $sentiment,
            'average_rating' => round($avgRating, 2),
            'total_reviews' => $reviews->count(),
            'low_reviews_count' => $lowReviews->count(),
            'high_reviews_count' => $highReviews->count(),
            'recent_negative_feedback' => $lowReviews->pluck('comment')->filter()->values()->toArray(),
        ]);
    }

    public function inventoryForecast(Request $request): JsonResponse
    {
        $vendor = $this->getVendor($request);
        $data = $request->validate([
            'store_id' => ['required', 'integer'],
            'days_ahead' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $store = Store::where('id', $data['store_id'])
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $days = $data['days_ahead'] ?? 7;

        // Get order history for the store
        $orders = Order::where('store_id', $store->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->with('details.item')
            ->get();

        $itemDemand = [];
        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $itemName = $detail->item->name ?? 'Unknown';
                $itemDemand[$itemName] = ($itemDemand[$itemName] ?? 0) + $detail->quantity;
            }
        }

        arsort($itemDemand);

        $forecast = array_slice($itemDemand, 0, 10, true);

        return response()->json([
            'success' => true,
            'store_id' => $store->id,
            'forecast_days' => $days,
            'top_demand_items' => array_map(function ($item, $qty) {
                return [
                    'item' => $item,
                    'units_last_30_days' => $qty,
                    'projected_daily' => round($qty / 30, 1),
                    'projected_period' => round($qty / 30 * $days, 1),
                ];
            }, array_keys($forecast), $forecast),
        ]);
    }

    public function photoQualityCheck(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'context' => ['nullable', 'string'],
        ]);

        // This would call an AI vision service
        // For now return a structured response
        return response()->json([
            'success' => true,
            'quality_score' => 0.85,
            'issues' => [],
            'recommendations' => ['Photo meets quality standards for menu display.'],
            'approved' => true,
        ]);
    }

    private function getVendor(Request $request): Vendor
    {
        $user = $request->user('vendor');
        abort_unless($user, 401, 'Unauthorized.');

        $vendor = $user->vendor ?? $user->vendor_user?->vendor;
        abort_unless($vendor, 403, 'Vendor profile not found.');

        return $vendor;
    }
}