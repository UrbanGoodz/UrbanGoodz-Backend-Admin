<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzHistoricalReconstructionConfiguration;
use App\Models\UrbanGoodzHistoricalMonthlySnapshot;
use App\Services\UrbanGoodz\HistoricalReconstructionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrbanGoodzHistoricalReconstructionApiController extends Controller
{
    protected HistoricalReconstructionService $service;

    public function __construct(HistoricalReconstructionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $configs = UrbanGoodzHistoricalReconstructionConfiguration::withCount('snapshots')
            ->when($request->filled('active'), function ($q) {
                $q->where('is_active', true);
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $data = $this->service->getReconstructionSummary($id);

        return response()->json([
            'success' => true,
            'data' => [
                'configuration' => $data['configuration'],
                'evidentiary_disclaimer' => $data['configuration']->evidentiary_disclaimer,
                'summary' => $data['summary'],
                'snapshots' => $data['snapshots']->map(fn ($s) => [
                    'month' => $s->snapshot_month->format('M Y'),
                    'reconstruction_id' => $s->reconstruction_id,
                    'orders' => (float) $s->estimated_orders,
                    'average_order_value' => (float) $s->estimated_average_order_value,
                    'total_order_value' => (float) $s->estimated_total_order_value,
                    'order_commission_revenue' => (float) $s->estimated_order_commission_revenue,
                    'delivery_fee_revenue' => (float) $s->estimated_delivery_fee_revenue,
                    'platform_delivery_fee_revenue' => (float) $s->estimated_platform_delivery_fee_revenue,
                    'total_platform_revenue' => (float) $s->estimated_total_platform_revenue,
                    'active_drivers' => $s->estimated_active_driver_count,
                    'owner_deliveries' => $s->estimated_owner_deliveries,
                    'driver_payouts' => (float) $s->estimated_driver_payouts,
                    'operating_expenses' => (float) $s->estimated_operating_expenses,
                    'estimated_net_income' => (float) $s->estimated_net_income,
                    'confidence' => $s->confidence,
                    'source_type' => $s->source_type,
                    'reconstruction_method' => $s->reconstruction_method,
                    'reconstruction_version' => $s->reconstruction_version,
                ]),
            ],
        ]);
    }

    public function snapshot(int $configId, string $reconstructionId): JsonResponse
    {
        $snapshot = UrbanGoodzHistoricalMonthlySnapshot::where('configuration_id', $configId)
            ->where('reconstruction_id', $reconstructionId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $snapshot,
        ]);
    }

    public function truckTimeline(int $id): JsonResponse
    {
        $timeline = $this->service->getTruckPurchaseTimeline($id);

        return response()->json([
            'success' => true,
            'data' => [
                'truck_purchase_date' => $timeline['truck_purchase_date'],
                'pre_purchase_avg_orders' => $timeline['pre_purchase_avg_orders'],
                'pre_purchase_avg_revenue' => $timeline['pre_purchase_avg_revenue'],
                'post_purchase_avg_orders' => $timeline['post_purchase_avg_orders'],
                'post_purchase_avg_revenue' => $timeline['post_purchase_avg_revenue'],
                'pre_purchase_months' => $timeline['pre_purchase_months']->map(fn ($s) => [
                    'month' => $s->snapshot_month->format('M Y'),
                    'orders' => (float) $s->estimated_orders,
                    'total_platform_revenue' => (float) $s->estimated_total_platform_revenue,
                ]),
                'purchase_month' => $timeline['purchase_month'] ? [
                    'month' => $timeline['purchase_month']->snapshot_month->format('M Y'),
                    'orders' => (float) $timeline['purchase_month']->estimated_orders,
                    'total_platform_revenue' => (float) $timeline['purchase_month']->estimated_total_platform_revenue,
                ] : null,
                'post_purchase_months' => $timeline['post_purchase_months']->map(fn ($s) => [
                    'month' => $s->snapshot_month->format('M Y'),
                    'orders' => (float) $s->estimated_orders,
                    'total_platform_revenue' => (float) $s->estimated_total_platform_revenue,
                ]),
            ],
        ]);
    }

    public function exportJson(int $id): JsonResponse
    {
        $data = $this->service->exportJson($id);

        return response()->json($data);
    }
}
