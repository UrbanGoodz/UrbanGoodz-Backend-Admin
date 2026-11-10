<?php

namespace App\Services;

use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Services\UrbanGoodzAiDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAnywhereDispatchIntegrationService
{
    public function __construct(
        private UrbanGoodzAiDispatchService $dispatchService,
        private OrderAnywhereCardService $cardService,
        private UrbanGoodzPaymentService $paymentService,
    ) {}

    public function findNearestDrivers(float $lat, float $lng, float $radiusMiles = 50, array $criteria = []): array
    {
        $query = DeliveryMan::query()
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->where('available_for_order_anywhere', true)
            ->whereNotNull('private_endpoint_lat')
            ->whereNotNull('private_endpoint_lng')
            ->where(fn($q) => $q->whereNull('current_orders')->orWhere('current_orders', '<', (int) (config('dm_maximum_orders') ?? 10)));

        if (!empty($criteria['vehicle_type'])) {
            $query->whereHas('vehicle', fn($q) => $q->where('type', $criteria['vehicle_type']));
        }
        if (!empty($criteria['zone_id'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('zone_id', $criteria['zone_id'])
                  ->orWhere(function ($sub) use ($criteria) {
                      $sub->whereNotNull('preferred_zones')
                          ->whereJsonContains('preferred_zones', (string) $criteria['zone_id']);
                  });
            });
        }

        $drivers = $query->get();

        $driversWithDistance = $drivers->map(function ($driver) use ($lat, $lng) {
            $distance = $this->haversineDistance(
                (float) $driver->private_endpoint_lat,
                (float) $driver->private_endpoint_lng,
                $lat,
                $lng
            );
            $driver->distance_miles = round($distance, 2);
            return $driver;
        })->filter(fn($d) => $d->distance_miles <= $radiusMiles)
          ->sortBy('distance_miles')
          ->values()
          ->toArray();

        return $driversWithDistance;
    }

    public function createDispatchForOrder(Order $order, int $driverId, array $options = []): AiDispatch
    {
        return DB::transaction(function () use ($order, $driverId, $options) {
            $payload = [
                'order_id' => $order->id,
                'delivery_man_id' => $driverId,
                'customer_id' => $order->user_id,
                'vendor_id' => $order->store_id,
                'source_type' => 'order_anywhere',
                'source_id' => $order->id,
                'created_by_type' => $options['created_by_type'] ?? 'customer',
                'created_by_id' => $options['created_by_id'] ?? $order->user_id,
                'title' => 'Order #' . $order->id,
                'driver_payout_amount' => $options['driver_payout_amount'] ?? null,
                'offer_expires_at' => $options['offer_expires_at'] ?? now()->addMinutes(15),
                'metadata' => [
                    'order_type' => $order->order_type,
                    'order_amount' => $order->order_amount,
                    'delivery_charge' => $order->delivery_charge,
                    'payment_method' => $order->payment_method,
                    'distance' => $order->distance,
                    'pickup_address' => $options['pickup_address'] ?? '',
                    'dropoff_address' => $order->delivery_address,
                ],
            ];

            $dispatch = $this->dispatchService->createAndSend($payload);

            return $dispatch;
        });
    }

    public function onDispatchAccepted(AiDispatch $dispatch): void
    {
        $order = $dispatch->order;
        if (!$order) return;

        DB::transaction(function () use ($dispatch, $order) {
            $order->delivery_man_id = $dispatch->delivery_man_id;
            $order->order_status = in_array($order->order_status, ['pending', 'confirmed']) ? 'accepted' : $order->order_status;
            $order->accepted = now();
            $order->save();

            $dm = DeliveryMan::find($dispatch->delivery_man_id);
            if ($dm) {
                $dm->increment('current_orders');
                $dm->increment('assigned_order_count');
            }

        });

        // Money moves at the moment a driver commits: capture the customer's authorized funds,
        // THEN issue the driver's purchase card. Done after the assignment commits so a slow
        // payment provider never holds the driver's acceptance open, and never inside it.
        $orderRequest = OrderAnywhereRequest::where('order_id', $order->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->latest()
            ->first();

        // Record WHO took the job. Without this the request never knew its driver: no driver pay was
        // allocated at capture, the driver endpoints (which look jobs up by assigned driver) could not
        // find it, and automatic re-dispatch could not tell it was taken.
        if ($orderRequest && ! $orderRequest->assigned_delivery_man_id) {
            $orderRequest->forceFill([
                'assigned_delivery_man_id' => $dispatch->delivery_man_id,
                'driver_task_status' => 'assigned',
                'assigned_at' => now(),
            ])->save();

            if ($orderRequest->isExternalMerchant() && $orderRequest->status === 'approved') {
                try {
                    $orderRequest = app(OrderAnywhereDriverFlow::class)->advance($orderRequest, 'accepted');
                } catch (\Throwable $e) {
                    Log::warning('Could not advance Order Anywhere request after dispatch acceptance', ['request_id' => $orderRequest->id, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($orderRequest && $orderRequest->isExternalMerchant() && $this->captureFundsForDriver($orderRequest)) {
            try {
                $cardRequest = $this->cardService->createCardRequest($orderRequest->fresh());
                Log::info('Purchase card auto-issued for dispatch', [
                    'dispatch_id' => $dispatch->id,
                    'card_request_id' => $cardRequest->id,
                ]);
            } catch (\Exception $e) {
                Log::warning('Card issuance deferred', [
                    'dispatch_id' => $dispatch->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Capture the customer's authorized payment when a driver accepts. The customer is only
     * ever charged once someone has actually committed to the job; if nobody does, the
     * authorization is voided instead (no charge, no refund, no processor fee).
     *
     * @return bool true when funds are secured and a purchase card may be issued
     */
    public function captureFundsForDriver(OrderAnywhereRequest $request): bool
    {
        if (! config('urban_goodz_payments.order_anywhere.capture_on_accept', true)) {
            return in_array($request->payment_status, ['authorized', 'captured'], true);
        }

        if (in_array($request->payment_status, ['captured', 'partially_captured'], true)) {
            return true;
        }

        if ($request->payment_status !== 'authorized') {
            app(OrderAnywhereOpsNotifier::class)->admins(
                'Order Anywhere: driver accepted an unpaid request',
                "Request {$request->request_number} is '{$request->payment_status}', not authorized. No purchase card was issued.",
                ['request_id' => $request->id]
            );

            return false;
        }

        try {
            $payments = app(\App\Services\UrbanGoodzPaymentService::class);
            // The driver's pay must be a split before the money is released.
            $payments->attachDriverSplit($request->fresh());
            $payments->captureCustomerPayment($request->fresh(), [
                'captured_amount' => (float) $request->authorized_amount,
                'capture_reference' => 'capture-on-accept-' . $request->request_number,
                'source' => 'driver_accept',
            ]);

            // The reconciliation gate checks the request's totals, not who actually has a payout row. Check the
            // rows too, and tell an admin if the money was captured but someone is missing from the split.
            $problems = $payments->splitRowMismatches($request->fresh());
            if ($problems) {
                app(OrderAnywhereOpsNotifier::class)->admins(
                    'Order Anywhere: payout split does not match',
                    "Request {$request->request_number} was captured but its payout rows disagree with its totals: " . implode('; ', $problems),
                    ['request_id' => $request->id],
                    'order_anywhere_split_mismatch'
                );
            }

            return true;
        } catch (\Throwable $e) {
            // Authorization expired or the card no longer holds the funds. Do NOT issue a card
            // we cannot cover; tell an admin.
            Log::error('Order Anywhere capture on driver acceptance failed', ['request_id' => $request->id, 'error' => $e->getMessage()]);
            $request->update(['metadata' => [...($request->metadata ?? []), 'capture_failed_at' => now()->toIso8601String(), 'capture_error' => mb_substr($e->getMessage(), 0, 200)]]);
            app(OrderAnywhereOpsNotifier::class)->admins(
                'Order Anywhere: payment capture failed',
                "Request {$request->request_number}: a driver accepted but the customer's payment could not be captured. No purchase card was issued.",
                ['request_id' => $request->id]
            );

            return false;
        }
    }

    public function onDispatchDelivered(AiDispatch $dispatch): void
    {
        $order = $dispatch->order;
        if (!$order) return;

        DB::transaction(function () use ($dispatch, $order) {
            $order->order_status = 'delivered';
            $order->save();

            $orderRequest = OrderAnywhereRequest::where('order_id', $order->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
                ->latest()
                ->first();

            if ($orderRequest) {
                try {
                    $cardRequest = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $orderRequest->id)
                        ->where('card_status', 'used')
                        ->latest()
                        ->first();

                    if ($cardRequest) {
                        $this->paymentService->accrueEarnings($orderRequest);
                        $this->paymentService->finalizeSplits($orderRequest);
                        $this->paymentService->settleSplits($orderRequest);
                    }

                    if (in_array($orderRequest->status, ['picked_up', 'out_for_delivery'])) {
                        $orderRequest->transitionTo('completed');
                    }
                } catch (\Exception $e) {
                    Log::error('Payment settlement failed after delivery', [
                        'dispatch_id' => $dispatch->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 3959;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
