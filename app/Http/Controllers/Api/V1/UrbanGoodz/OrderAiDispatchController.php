<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Models\Order;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Services\OrderAnywhereDispatchIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderAiDispatchController extends Controller
{
    public function __construct(
        private OrderAnywhereDispatchIntegrationService $integrationService,
    ) {}

    public function triggerNearestDriver(Request $request, $orderId)
    {
        $request->validate([
            'vehicle_type' => 'nullable|string',
            'radius_miles' => 'nullable|numeric|min:1|max:200',
        ]);

        $order = Order::with('store')
            ->where('user_id', auth('api')->id())
            ->findOrFail($orderId);

        if ($order->delivery_man_id) {
            return response()->json([
                'message' => 'Order already has a driver assigned',
                'delivery_man_id' => $order->delivery_man_id,
            ], 409);
        }

        $activeDispatch = AiDispatch::where('order_id', $order->id)
            ->whereNotIn('status', [AiDispatch::STATUS_CLOSED, AiDispatch::STATUS_CANCELLED, AiDispatch::STATUS_DELIVERED, AiDispatch::STATUS_SETTLED, AiDispatch::STATUS_EXPIRED, AiDispatch::STATUS_DECLINED])
            ->where('delivery_man_id', '!=', null)
            ->latest()
            ->first();

        if ($activeDispatch && $activeDispatch->status === AiDispatch::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'You already have an active dispatch for this order',
                'dispatch' => $activeDispatch->only(['id', 'status', 'uuid', 'delivery_man_id']),
                'delivery_man' => $activeDispatch->deliveryMan ? [
                    'id' => $activeDispatch->deliveryMan->id,
                    'name' => $activeDispatch->deliveryMan->f_name . ' ' . $activeDispatch->deliveryMan->l_name,
                    'phone' => $activeDispatch->deliveryMan->phone,
                    'rating' => $activeDispatch->deliveryMan->avg_rating,
                    'vehicle_no' => $activeDispatch->deliveryMan->vehicle_id,
                ] : null,
            ], 200);
        }

        $store = $order->store;
        $pickupLat = (float) ($store->latitude ?? $store->lat ?? $request->input('pickup_lat', 0));
        $pickupLng = (float) ($store->longitude ?? $store->lng ?? $request->input('pickup_lng', 0));

        if (!$pickupLat || !$pickupLng) {
            $zone = $order->zone_id ? \App\Models\Zone::find($order->zone_id) : null;
            if ($zone && $zone->coordinates) {
                $coords = json_decode($zone->coordinates, true);
                if (!empty($coords[0][0] ?? null)) {
                    $pickupLat = (float) $coords[0][0][1] ?? 0;
                    $pickupLng = (float) $coords[0][0][0] ?? 0;
                }
            }

            if (!$pickupLat || !$pickupLng) {
                $orderRequest = OrderAnywhereRequest::where('order_id', $order->id)->latest()->first();
                if ($orderRequest && $orderRequest->pickup_latitude && $orderRequest->pickup_longitude) {
                    $pickupLat = (float) $orderRequest->pickup_latitude;
                    $pickupLng = (float) $orderRequest->pickup_longitude;
                }
            }
        }

        if (!$pickupLat || !$pickupLng) {
            return response()->json([
                'message' => 'Pickup location not available. Please provide pickup_lat and pickup_lng.',
            ], 422);
        }

        $radiusMiles = $request->input('radius_miles', 50);
        $nearbyDrivers = $this->integrationService->findNearestDrivers(
            $pickupLat, $pickupLng, $radiusMiles,
            ['vehicle_type' => $request->vehicle_type, 'zone_id' => $order->zone_id]
        );

        if (empty($nearbyDrivers)) {
            return response()->json([
                'message' => 'No available nearby drivers found',
                'pickup' => ['lat' => $pickupLat, 'lng' => $pickupLng],
                'radius_miles' => $radiusMiles,
            ], 404);
        }

        $nearestDriver = $nearbyDrivers[0];
        if (is_array($nearestDriver)) {
            $driverId = $nearestDriver['id'];
        } else {
            $driverId = $nearestDriver->id;
        }

        $dispatch = $this->integrationService->createDispatchForOrder($order, $driverId, [
            'created_by_type' => 'customer',
            'created_by_id' => auth('api')->id(),
            'pickup_address' => $store->address ?? '',
            'driver_payout_amount' => $order->delivery_charge ?? null,
        ]);

        // createDispatchForOrder -> createAndSend already sends the offer to
        // the driver exactly once (status 'sent' + one FCM push + one in-app
        // notification). Do NOT call sendToDriver()/pushToDriver() again here:
        // that is what produced duplicate push notifications.
        return response()->json([
            'message' => 'Nearest driver dispatched',
            'dispatch' => $dispatch->only(['id', 'status', 'uuid']),
            'driver' => [
                'id' => $nearestDriver['id'] ?? $nearestDriver->id,
                'name' => ($nearestDriver['f_name'] ?? '') . ' ' . ($nearestDriver['l_name'] ?? ''),
                'distance_miles' => $nearestDriver['distance_miles'] ?? $nearestDriver->distance_miles,
                'vehicle_no' => $nearestDriver['vehicle_id'] ?? null,
            ],
        ]);
    }

    public function dispatchStatus(Request $request, $orderId)
    {
        $order = Order::with(['delivery_man', 'store'])
            ->where('user_id', auth('api')->id())
            ->findOrFail($orderId);

        $dispatches = AiDispatch::with('deliveryMan')
            ->where('order_id', $order->id)
            ->latest()
            ->get();

        $nearbyDrivers = null;
        $pickupLat = (float) ($order->store->latitude ?? $order->store->lat ?? 0);
        $pickupLng = (float) ($order->store->longitude ?? $order->store->lng ?? 0);
        if ($pickupLat && $pickupLng) {
            $nearbyDrivers = collect(app(OrderAnywhereDispatchIntegrationService::class)
                ->findNearestDrivers($pickupLat, $pickupLng, 50))
                ->take(5)
                ->map(fn($d) => [
                    'id' => $d['id'],
                    'name' => ($d['f_name'] ?? '') . ' ' . ($d['l_name'] ?? ''),
                    'phone' => $d['phone'] ?? '',
                    'rating' => $d['avg_rating'] ?? 0,
                    'distance_miles' => $d['distance_miles'] ?? 0,
                    'vehicle_id' => $d['vehicle_id'] ?? null,
                ]);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'delivery_man' => $order->delivery_man ? [
                'id' => $order->delivery_man->id,
                'name' => $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name,
                'phone' => $order->delivery_man->phone,
                'rating' => $order->delivery_man->avg_rating,
            ] : null,
            'ai_dispatches' => $dispatches->map(fn($d) => [
                'id' => $d->id,
                'status' => $d->status,
                'driver_name' => $d->deliveryMan ? $d->deliveryMan->f_name . ' ' . $d->deliveryMan->l_name : null,
                'distance_miles' => $d->metadata['distance_miles'] ?? null,
                'created_at' => $d->created_at,
            ]),
            'nearby_drivers' => $nearbyDrivers,
        ]);
    }
}
