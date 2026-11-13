<?php

namespace App\Services;

use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\Module;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The business bridge between the Order Anywhere sourcing workflow and the
 * existing Order + AiDispatch delivery engine.
 *
 * Order Anywhere initially only writes an `order_anywhere_requests` row.
 * When the sourced item/pricing is approved the request converts into a real
 * `orders` row (`order_anywhere_requests.order_id` populated). That conversion
 * is the dispatch trigger: it fires nearest-driver matching exactly once,
 * reusing the existing OrderAnywhereDispatchIntegrationService pipeline so the
 * driver offer, push, acceptance and purchase-card flows stay unchanged.
 *
 * All entry points are idempotent and safe for retries:
 *  - convertToOrder() returns the existing Order when one is already linked.
 *  - autoDispatchNearestDriver() skips orders that already have a driver or an
 *    active (non-final) dispatch.
 */
class OrderAnywhereOrderConversionService
{
    public function __construct(
        private OrderAnywhereDispatchIntegrationService $integrationService,
    ) {}

    /**
     * Create the real Order backing an approved request and link it back.
     * Returns the existing Order when already linked (idempotent).
     */
    public function convertToOrder(OrderAnywhereRequest $request): ?Order
    {
        if ($request->order_id) {
            $order = Order::find($request->order_id);
            if ($order) {
                return $order;
            }
            Log::warning('Order Anywhere conversion: linked order missing', [
                'request_id' => $request->id,
                'order_id' => $request->order_id,
            ]);
        }

        if (! in_array($request->status, ['approved', 'shopper_assigned', 'shopping', 'purchased', 'ready_for_pickup', 'driver_assigned', 'picked_up', 'out_for_delivery', 'delivered', 'completed'], true)) {
            Log::debug('Order Anywhere conversion skipped: request not yet delivery-capable', [
                'request_id' => $request->id,
                'status' => $request->status,
            ]);

            return null;
        }

        $store = null;
        if ($request->vendor_id) {
            $store = Store::where('vendor_id', $request->vendor_id)->first();
        }

        $zoneId = $store->zone_id
            ?? data_get($request->metadata, 'zone_id')
            ?? Zone::where('status', 1)->value('id');

        $moduleId = $store->module_id ?? Module::first()?->id;

        $dropoffAddress = $request->dropoff_address
            ?? data_get($request->metadata, 'delivery_address')
            ?? data_get($request->metadata, 'address', '');

        $order = DB::transaction(function () use ($request, $store, $zoneId, $moduleId, $dropoffAddress) {
            $order = Order::create([
                'user_id' => $request->customer_id,
                'store_id' => $store->id ?? null,
                'module_id' => $moduleId,
                'zone_id' => $zoneId,
                'order_type' => 'delivery',
                'order_amount' => (float) ($request->final_amount ?? $request->quote_amount ?? 0),
                'delivery_charge' => (float) ($request->delivery_fee ?? 0),
                'order_status' => 'pending',
                'delivery_address' => $dropoffAddress,
                'payment_method' => $request->payment_method ?? 'cash_on_delivery',
                'distance' => (float) data_get($request->metadata, 'distance_miles', 0),
            ]);

            $request->order_id = $order->id;
            $request->pickup_address = $store->address ?? $request->pickup_address;

            $lat = $store->latitude ?? $request->pickup_latitude;
            $lng = $store->longitude ?? $request->pickup_longitude;
            $source = $lat !== null && $lng !== null ? ($store ? 'store' : 'provided') : null;

            // "Anywhere" merchants have no store row: look the address up before falling back to a guess.
            if ($source === null) {
                $geo = app(GeocodingService::class)->geocode($request->pickup_address ?: $request->store_vendor_address_or_website);
                if ($geo) {
                    [$lat, $lng, $source] = [$geo['lat'], $geo['lng'], 'geocoded'];
                }
            }
            if ($source === null) {
                [$lat, $lng, $source] = [$this->zoneCenterLat($zoneId), $this->zoneCenterLng($zoneId), 'estimated_zone_center'];
            }

            $request->pickup_latitude = $lat;
            $request->pickup_longitude = $lng;
            // An estimate means "nearest driver" is only nearest to the middle of the zone. Say so, so an admin can fix it.
            $request->metadata = array_merge((array) $request->metadata, ['pickup_location_source' => $source]);
            $request->dropoff_address = $dropoffAddress;
            $request->save();

            return $order;
        });

        Log::info('Order Anywhere converted to real order', [
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'order_id' => $order->id,
        ]);

        return $order;
    }

    /**
     * Match the nearest eligible driver to the order and create a single
     * dispatch offer. Idempotent: does nothing when a driver is already
     * assigned or an active dispatch already exists for this order.
     */
    public function autoDispatchNearestDriver(Order $order, ?int $requestId = null): ?AiDispatch
    {
        if ($order->delivery_man_id) {
            Log::info('Order Anywhere auto-dispatch skipped: driver already assigned', [
                'order_id' => $order->id,
                'delivery_man_id' => $order->delivery_man_id,
            ]);

            return null;
        }

        $activeDispatch = AiDispatch::where('order_id', $order->id)
            ->whereNotIn('status', [
                AiDispatch::STATUS_CLOSED,
                AiDispatch::STATUS_CANCELLED,
                AiDispatch::STATUS_DELIVERED,
                AiDispatch::STATUS_SETTLED,
                AiDispatch::STATUS_EXPIRED,
                AiDispatch::STATUS_DECLINED,
            ])
            ->latest()
            ->first();

        if ($activeDispatch) {
            Log::info('Order Anywhere auto-dispatch skipped: active dispatch exists', [
                'order_id' => $order->id,
                'dispatch_id' => $activeDispatch->id,
                'dispatch_status' => $activeDispatch->status,
            ]);

            return $activeDispatch;
        }

        $pickupLat = (float) ($order->store?->latitude ?? $order->store?->lat ?? 0);
        $pickupLng = (float) ($order->store?->longitude ?? $order->store?->lng ?? 0);

        if ((! $pickupLat || ! $pickupLng) && $requestId) {
            $request = OrderAnywhereRequest::find($requestId);
            if ($request) {
                $pickupLat = (float) ($request->pickup_latitude ?? 0);
                $pickupLng = (float) ($request->pickup_longitude ?? 0);
            }
        }

        if (! $pickupLat || ! $pickupLng) {
            Log::warning('Order Anywhere auto-dispatch skipped: no pickup coordinates', [
                'order_id' => $order->id,
                'request_id' => $requestId,
            ]);

            return null;
        }

        $orderRequest = $requestId ? OrderAnywhereRequest::find($requestId) : null;
        // The admin-set radius, unless a person (or Monique, at an admin's request) widened the
        // search for this one job. The override can only widen, never narrow, the global setting.
        $radius = max(
            (float) OrderAnywhereDispatchSettings::get('radius_miles'),
            (float) ($orderRequest?->metadata['dispatch_radius_override_miles'] ?? 0)
        );

        // Every driver within that radius, nearest first.
        $inRadius = $this->integrationService->findNearestDrivers(
            $pickupLat,
            $pickupLng,
            $radius,
            ['zone_id' => $order->zone_id] + $this->integrationService->poolCriteriaForOrder($order)
        );

        $driverId = fn ($d) => is_array($d) ? $d['id'] : $d->id;
        // Dispatches at or below this id belong to earlier rounds (exact, unlike timestamps).
        $roundWatermark = (int) ($orderRequest?->metadata['dispatch_round_after_id'] ?? 0);

        // Cycle: nearest, then next nearest, and so on. A driver who already declined, let the
        // offer lapse or was withdrawn in THIS round is skipped.
        $alreadyTried = AiDispatch::where('order_id', $order->id)
            ->whereIn('status', [AiDispatch::STATUS_DECLINED, AiDispatch::STATUS_EXPIRED, AiDispatch::STATUS_CANCELLED])
            ->where('id', '>', $roundWatermark)
            ->pluck('delivery_man_id')
            ->all();
        $nearbyDrivers = array_values(array_filter($inRadius, fn ($d) => ! in_array($driverId($d), $alreadyTried, true)));

        // Everyone in range has had their turn and nobody took it: start another round from the
        // nearest. This continues until a driver accepts, an admin assigns one, or it is cancelled.
        if (empty($nearbyDrivers) && ! empty($inRadius) && $orderRequest) {
            $round = (int) ($orderRequest->metadata['dispatch_round'] ?? 1) + 1;
            $orderRequest->update(['metadata' => [
                ...($orderRequest->metadata ?? []),
                'dispatch_round' => $round,
                'dispatch_round_after_id' => (int) AiDispatch::where('order_id', $order->id)->max('id'),
                'dispatch_round_started_at' => now()->toIso8601String(),
            ]]);
            Log::info('Order Anywhere dispatch: starting a new round', ['order_id' => $order->id, 'round' => $round]);
            $nearbyDrivers = $inRadius;
        }

        if (empty($nearbyDrivers)) {
            Log::warning('Order Anywhere auto-dispatch: no drivers available within the radius', [
                'order_id' => $order->id,
                'request_id' => $requestId,
                'radius_miles' => $radius,
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
            ]);

            return null;
        }

        $nearest = $nearbyDrivers[0];
        $nearestId = $driverId($nearest);

        $dispatch = $this->integrationService->createDispatchForOrder($order, $nearestId, [
            'created_by_type' => 'system',
            'created_by_id' => null,
            'pickup_address' => $order->store?->address ?? '',
            'driver_payout_amount' => $order->delivery_charge ?? null,
            // Short, so a silent driver costs the customer minutes, not a quarter of an hour.
            'offer_expires_at' => now()->addMinutes(OrderAnywhereDispatchSettings::get('offer_minutes')),
        ]);

        if ($requestId) {
            $request = OrderAnywhereRequest::find($requestId);
            if ($request) {
                $request->update([
                    'metadata' => [
                        ...($request->metadata ?? []),
                        'dispatch_triggered_at' => now()->toIso8601String(),
                        'dispatch_id' => $dispatch->id,
                    ],
                ]);
            }
        }

        Log::info('Order Anywhere auto-dispatch created', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'dispatch_id' => $dispatch->id,
            'delivery_man_id' => $nearestId,
        ]);

        return $dispatch;
    }

    /**
     * A driver just declined: offer the job to the next nearest straight away instead of waiting
     * for the next scheduler tick.
     */
    public function redispatchAfterDecline(AiDispatch $dispatch): ?AiDispatch
    {
        if ($dispatch->source_type !== 'order_anywhere' || ! $dispatch->order_id) {
            return null;
        }

        $request = OrderAnywhereRequest::where('order_id', $dispatch->order_id)->where('status', 'approved')->latest()->first();

        return $request ? $this->handleApproved($request) : null;
    }

    /**
     * Full conversion entry point used by the observer when a request is
     * approved (customer-approved price) or when order_id is populated.
     */
    public function handleApproved(OrderAnywhereRequest $request): ?AiDispatch
    {
        if ($request->assigned_delivery_man_id) {
            Log::info('Order Anywhere auto-dispatch skipped: admin assigned a driver manually', [
                'request_id' => $request->id,
                'delivery_man_id' => $request->assigned_delivery_man_id,
            ]);

            return null;
        }

        $order = $this->convertToOrder($request);
        if (! $order) {
            return null;
        }

        return $this->autoDispatchNearestDriver($order, $request->id);
    }

    private function zoneCenterLat(?int $zoneId): ?float
    {
        if (! $zoneId) {
            return null;
        }
        $coords = json_decode((string) Zone::find($zoneId)?->coordinates, true);

        return isset($coords[0][0][1]) ? (float) $coords[0][0][1] : null;
    }

    private function zoneCenterLng(?int $zoneId): ?float
    {
        if (! $zoneId) {
            return null;
        }
        $coords = json_decode((string) Zone::find($zoneId)?->coordinates, true);

        return isset($coords[0][0][0]) ? (float) $coords[0][0][0] : null;
    }
}
