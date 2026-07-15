<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzRouteBatch;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ETAPredictionController extends Controller
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    // ORDER ETA PREDICTION

    public function predictOrderETA(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = Order::with(['details.item', 'store', 'deliveryMan', 'customer'])
            ->findOrFail($data['order_id']);

        $driverLocation = null;
        if ($order->delivery_man_id) {
            $driver = DeliveryMan::find($order->delivery_man_id);
            if ($driver && $driver->current_lat && $driver->current_lng) {
                $driverLocation = [
                    'lat' => $driver->current_lat,
                    'lng' => $driver->current_lng,
                ];
            }
        }

        $storeLocation = [
            'lat' => $order->store->latitude ?? 0,
            'lng' => $order->store->longitude ?? 0,
        ];

        $deliveryLocation = [
            'lat' => $order->delivery_latitude ?? 0,
            'lng' => $order->delivery_longitude ?? 0,
        ];

        $prediction = $this->ai->chat(
            'You are an ETA prediction engine for Urban Goodz delivery service.
Predict the delivery ETA based on:
- Current driver location (if available)
- Store/merchant location
- Delivery destination
- Current time
- Traffic patterns
- Historical completion times

Return JSON:
{
  "predicted_eta": "ISO 8601 datetime",
  "confidence": 0.0-1.0,
  "current_phase": "preparing|picked_up|in_transit|arriving",
  "driver_distance_to_store_miles": 0.0,
  "driver_distance_to_delivery_miles": 0.0,
  "estimated_prep_time_minutes": 0,
  "estimated_transit_time_minutes": 0,
  "traffic_impact_minutes": 0,
  "delay_risk": "low|medium|high",
  "delay_reasons": ["string"],
  "recommended_notification_time": "ISO 8601 datetime"
}',
            "Predict ETA for order {$order->order_number}.",
            [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->order_status,
                    'store' => $storeLocation,
                    'delivery' => $deliveryLocation,
                    'driver' => $driverLocation,
                    'items_count' => $order->details->count(),
                    'created_at' => $order->created_at,
                ],
            ]
        );

        $parsed = json_decode(trim($prediction), true);
        if (!$parsed) {
            return response()->json([
                'success' => false,
                'message' => 'AI prediction failed',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'prediction' => $parsed,
        ]);
    }

    // ROUTE ETA PREDICTION

    public function predictRouteETA(Request $request): JsonResponse
    {
        $data = $request->validate([
            'route_batch_id' => ['required', 'integer'],
        ]);

        $routeBatch = UrbanGoodzRouteBatch::with('packages.pickupLocation', 'packages.deliveryLocation', 'deliveryMan')
            ->findOrFail($data['route_batch_id']);

        $driverLocation = null;
        if ($routeBatch->delivery_man_id) {
            $driver = DeliveryMan::find($routeBatch->delivery_man_id);
            if ($driver && $driver->current_lat && $driver->current_lng) {
                $driverLocation = [
                    'lat' => $driver->current_lat,
                    'lng' => $driver->current_lng,
                ];
            }
        }

        $stops = $routeBatch->packages->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'type' => 'pickup',
                'address' => $pkg->pickup_address,
                'lat' => $pkg->pickup_latitude,
                'lng' => $pkg->pickup_longitude,
                'window_start' => $pkg->pickup_window_start,
                'window_end' => $pkg->pickup_window_end,
                'service_time' => $pkg->service_time_minutes ?? 15,
            ];
        })->toArray();

        foreach ($routeBatch->packages as $pkg) {
            $stops[] = [
                'id' => 'd_' . $pkg->id,
                'type' => 'delivery',
                'address' => $pkg->delivery_address,
                'lat' => $pkg->delivery_latitude,
                'lng' => $pkg->delivery_longitude,
                'window_start' => $pkg->delivery_window_start,
                'window_end' => $pkg->delivery_window_end,
                'service_time' => $pkg->service_time_minutes ?? 10,
            ];
        }

        $prediction = $this->ai->chat(
            'You are a route ETA prediction engine for Urban Goodz logistics.
Predict completion ETA for a multi-stop route.

Input:
- Driver current location (if available)
- Array of stops with type (pickup/delivery), coordinates, time windows, service time
- Current time

Return JSON:
{
  "predicted_completion": "ISO 8601 datetime",
  "confidence": 0.0-1.0,
  "total_distance_miles": 0.0,
  "total_time_minutes": 0,
  "stops_eta": [
    {"stop_id": "id", "predicted_arrival": "ISO 8601", "predicted_departure": "ISO 8601", "on_time": true}
  ],
  "delay_risk": "low|medium|high",
  "major_delays": [{"stop_id": "id", "reason": "string", "delay_minutes": 0}],
  "recommendations": ["string"]
}',
            "Predict ETA for route batch {$routeBatch->batch_number}.",
            [
                'route' => [
                    'id' => $routeBatch->id,
                    'batch_number' => $routeBatch->batch_number,
                    'driver_location' => $driverLocation,
                    'stops' => $stops,
                    'vehicle_type' => $routeBatch->vehicle_type,
                    'current_time' => now()->toISOString(),
                ],
            ]
        );

        $parsed = json_decode(trim($prediction), true);
        if (!$parsed) {
            return response()->json([
                'success' => false,
                'message' => 'AI prediction failed',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'route_batch_id' => $routeBatch->id,
            'batch_number' => $routeBatch->batch_number,
            'prediction' => $parsed,
        ]);
    }

    // DRIVER ETA TO SPECIFIC STOP

    public function driverStopETA(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer'],
            'stop_id' => ['required', 'string'],
        ]);

        $driver = DeliveryMan::findOrFail($data['driver_id']);
        if (!$driver->current_lat || !$driver->current_lng) {
            return response()->json([
                'success' => false,
                'message' => 'Driver location not available',
            ], 400);
        }

        $isDelivery = str_starts_with($data['stop_id'], 'd_');
        $pkgId = $isDelivery ? substr($data['stop_id'], 2) : $data['stop_id'];

        $package = UrbanGoodzRoutePackage::with(['routeBatch'])->findOrFail($pkgId);

        $targetLat = $isDelivery ? $package->delivery_latitude : $package->pickup_latitude;
        $targetLng = $isDelivery ? $package->delivery_longitude : $package->pickup_longitude;

        if (!$targetLat || !$targetLng) {
            return response()->json([
                'success' => false,
                'message' => 'Stop location not available',
            ], 400);
        }

        $distance = $this->haversine($driver->current_lat, $driver->current_lng, $targetLat, $targetLng);
        $avgSpeed = 30;
        $travelTimeMinutes = ($distance / $avgSpeed) * 60;

        $trafficBuffer = 1.2;
        $etaMinutes = ceil($travelTimeMinutes * $trafficBuffer);

        $eta = now()->addMinutes($etaMinutes);

        return response()->json([
            'success' => true,
            'stop_id' => $data['stop_id'],
            'stop_type' => $isDelivery ? 'delivery' : 'pickup',
            'driver_location' => ['lat' => $driver->current_lat, 'lng' => $driver->current_lng],
            'stop_location' => ['lat' => $targetLat, 'lng' => $targetLng],
            'distance_miles' => round($distance, 1),
            'travel_time_minutes' => round($travelTimeMinutes),
            'buffered_eta_minutes' => $etaMinutes,
            'predicted_eta' => $eta->toISOString(),
            'confidence' => 0.75,
        ]);
    }

    // HISTORICAL ACCURACY

    public function getAccuracyMetrics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $period = $data['period_days'] ?? 30;
        $startDate = now()->subDays($period);

        $completedOrders = Order::where('order_status', 'delivered')
            ->where('delivered_at', '>=', $startDate)
            ->whereNotNull('predicted_eta')
            ->get();

        $total = $completedOrders->count();
        if ($total === 0) {
            return response()->json([
                'success' => true,
                'message' => 'No completed orders with predictions in period',
                'metrics' => [
                    'total_predictions' => 0,
                    'accuracy_within_10min' => 0,
                    'accuracy_within_30min' => 0,
                    'mean_absolute_error_minutes' => 0,
                    'bias_minutes' => 0,
                ],
            ]);
        }

        $within10 = 0;
        $within30 = 0;
        $totalError = 0;
        $totalBias = 0;

        foreach ($completedOrders as $order) {
            if (!$order->predicted_eta || !$order->delivered_at) continue;

            $predicted = \Carbon\Carbon::parse($order->predicted_eta);
            $actual = \Carbon\Carbon::parse($order->delivered_at);

            $errorMinutes = abs($predicted->diffInMinutes($actual));
            $biasMinutes = $predicted->diffInMinutes($actual);

            $totalError += $errorMinutes;
            $totalBias += $biasMinutes;

            if ($errorMinutes <= 10) $within10++;
            if ($errorMinutes <= 30) $within30++;
        }

        return response()->json([
            'success' => true,
            'period_days' => $period,
            'metrics' => [
                'total_predictions' => $total,
                'accuracy_within_10min' => round(($within10 / $total) * 100, 1),
                'accuracy_within_30min' => round(($within30 / $total) * 100, 1),
                'mean_absolute_error_minutes' => round($totalError / $total, 1),
                'bias_minutes' => round($totalBias / $total, 1),
                'predictions_within_10min' => $within10,
                'predictions_within_30min' => $within30,
            ],
        ]);
    }

    // HELPER

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $h = sin($dlat/2)**2 + cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2;
        return 3959 * 2 * asin(sqrt($h));
    }
}