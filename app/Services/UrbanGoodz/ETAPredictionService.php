<?php

namespace App\Services\UrbanGoodz;

use App\Models\Order;
use App\Models\DeliveryMan;
use Illuminate\Support\Facades\Log;

class ETAPredictionService
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    public function predictOrderETA(int $orderId): array
    {
        $order = Order::with(['details.item', 'store', 'deliveryMan', 'customer'])
            ->find($orderId);

        if (!$order) {
            return ['error' => 'Order not found.'];
        }

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
                'driver_location' => $driverLocation,
                'store_location' => $storeLocation,
                'delivery_location' => $deliveryLocation,
                'order_status' => $order->order_status,
                'created_at' => $order->created_at?->toIso8601String(),
                'items_count' => $order->details->count(),
                'special_instructions' => $order->special_instructions ?? 'None',
            ]
        );

        if (is_string($prediction)) {
            $decoded = json_decode($prediction, true);
            return is_array($decoded) ? $decoded : ['raw_prediction' => $prediction];
        }

        return $prediction;
    }
}
