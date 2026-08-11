<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Services\OrderAnywhereDispatchIntegrationService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class OrderAiDispatchAdminController extends Controller
{
    public function __construct(
        private OrderAnywhereDispatchIntegrationService $integrationService,
    ) {}

    public function pendingOrders(Request $request)
    {
        $query = Order::with(['delivery_man', 'store'])
            ->where(function ($q) {
                $q->whereNull('delivery_man_id')->orWhereNull('accepted');
            })
            ->whereNotIn('order_status', ['canceled', 'failed', 'delivered', 'refunded'])
            ->latest();

        if ($request->filled('order_id')) {
            $query->where('id', $request->order_id);
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        $orders = $query->paginate(50);
        $dispatchMap = AiDispatch::whereIn('order_id', $orders->pluck('id'))
            ->latest()->get()->groupBy('order_id');

        return response()->json([
            'success' => true,
            'data' => $orders->map(function ($o) use ($dispatchMap) {
                return [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'customer_name' => $o->customer ? $o->customer->f_name . ' ' . $o->customer->l_name : null,
                    'store_name' => $o->store ? $o->store->name : null,
                    'store_address' => $o->store ? $o->store->address : null,
                    'zone_id' => $o->zone_id,
                    'order_status' => $o->order_status,
                    'order_amount' => $o->order_amount,
                    'delivery_charge' => $o->delivery_charge,
                    'delivery_address' => $o->delivery_address,
                    'payment_method' => $o->payment_method,
                    'delivery_man' => $o->delivery_man ? [
                        'id' => $o->delivery_man->id,
                        'name' => $o->delivery_man->f_name . ' ' . $o->delivery_man->l_name,
                        'phone' => $o->delivery_man->phone,
                    ] : null,
                    'ai_dispatch' => isset($dispatchMap[$o->id]) ? $dispatchMap[$o->id]->first() : null,
                    'created_at' => $o->created_at,
                ];
            }),
            'pagination' => [
                'total' => $orders->total(),
                'count' => $orders->count(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'total_pages' => $orders->lastPage(),
            ],
        ]);
    }

    public function triggerNearestDriver(Request $request, $orderId)
    {
        $order = Order::with('store')->findOrFail($orderId);

        $store = $order->store;
        $pickupLat = (float) ($store->latitude ?? $store->lat ?? $request->input('pickup_lat', 0));
        $pickupLng = (float) ($store->longitude ?? $store->lng ?? $request->input('pickup_lng', 0));

        if (!$pickupLat || !$pickupLng) {
            return response()->json(['success' => false, 'message' => 'Pickup location not available'], 422);
        }

        $nearbyDrivers = $this->integrationService->findNearestDrivers(
            $pickupLat, $pickupLng, $request->input('radius_miles', 50),
            ['zone_id' => $order->zone_id]
        );

        if (empty($nearbyDrivers)) {
            return response()->json(['success' => false, 'message' => 'No available nearby drivers'], 404);
        }

        $nearest = $nearbyDrivers[0];
        $driverId = is_array($nearest) ? $nearest['id'] : $nearest->id;

        $dispatch = $this->integrationService->createDispatchForOrder($order, $driverId, [
            'created_by_type' => 'admin', 'created_by_id' => auth('admin')->id(),
            'pickup_address' => $store->address ?? '',
        ]);
        // createDispatchForOrder sends the offer exactly once (single FCM push
        // + single in-app notification). Redundant sendToDriver/pushToDriver
        // calls here caused duplicate push notifications.

        return response()->json([
            'success' => true,
            'data' => ['dispatch_id' => $dispatch->id, 'delivery_man_id' => $driverId],
        ]);
    }

    public function assignDriver(Request $request, $orderId)
    {
        $order = Order::with('store')->findOrFail($orderId);
        $data = $request->validate(['driver_id' => 'required|integer']);

        $deliveryMan = DeliveryMan::where('id', $data['driver_id'])->active()->available()->first();
        if (!$deliveryMan) {
            return response()->json(['success' => false, 'message' => 'Driver not available'], 404);
        }

        $dispatch = $this->integrationService->createDispatchForOrder($order, $data['driver_id'], [
            'created_by_type' => 'admin', 'created_by_id' => auth('admin')->id(),
            'pickup_address' => $order->store->address ?? '',
        ]);
        // Single send: createDispatchForOrder -> createAndSend handles it.

        return response()->json(['success' => true, 'data' => ['dispatch_id' => $dispatch->id]]);
    }

    public function cancelDispatch($dispatchId, Request $request)
    {
        $dispatch = AiDispatch::with('order')->findOrFail($dispatchId);
        $dispatch->cancelDispatch($request->input('reason'));
        return response()->json(['success' => true, 'message' => 'Dispatch cancelled']);
    }

    public function getAllDispatches(Request $request)
    {
        $query = AiDispatch::with(['deliveryMan', 'order'])->where('source_type', 'order_anywhere')->latest();

        if ($request->filled('order_id')) $query->where('order_id', $request->order_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function getDispatchDetail($dispatchId)
    {
        $dispatch = AiDispatch::with(['deliveryMan', 'order.store', 'order.user'])
            ->where('source_type', 'order_anywhere')->findOrFail($dispatchId);

        $ledgers = UrbanGoodzPaymentLedger::where('payable_type', Order::class)
            ->where('payable_id', $dispatch->order_id)->with('splits')->get();

        $orderRequest = OrderAnywhereRequest::where('order_id', $dispatch->order_id)->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'dispatch' => $dispatch,
                'payment_history' => $ledgers,
                'order_anywhere_request' => $orderRequest ? [
                    'status' => $orderRequest->status,
                    'payment_status' => $orderRequest->payment_status,
                    'quote_amount' => $orderRequest->quote_amount,
                    'final_amount' => $orderRequest->final_amount,
                ] : null,
            ],
        ]);
    }
}
