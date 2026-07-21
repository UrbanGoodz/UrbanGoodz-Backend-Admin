<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderAiDispatchVendorController extends Controller
{
    private function getStoreId(Request $request)
    {
        $vendor = $request->vendor ?? auth('vendor')->user();
        if (!$vendor) {
            abort(401, 'Unauthorized vendor');
        }
        return $vendor->stores[0]->id ?? $vendor->id;
    }

    public function orders(Request $request)
    {
        $storeId = $this->getStoreId($request);
        $orders = Order::with(['delivery_man'])
            ->where('store_id', $storeId)
            ->whereIn('order_status', ['pending', 'confirmed', 'accepted', 'out_for_delivery'])
            ->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'order_status' => $o->order_status,
                'order_amount' => $o->order_amount,
                'delivery_charge' => $o->delivery_charge,
                'delivery_address' => $o->delivery_address,
                'payment_method' => $o->payment_method,
                'delivery_man' => $o->delivery_man ? $o->delivery_man->f_name . ' ' . $o->delivery_man->l_name : null,
                'created_at' => $o->created_at,
            ]),
            'pagination' => [
                'total' => $orders->total(),
                'count' => $orders->count(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'total_pages' => $orders->lastPage(),
            ],
        ]);
    }

    public function orderDetail($orderId, Request $request)
    {
        $storeId = $this->getStoreId($request);
        $order = Order::with(['delivery_man', 'details'])
            ->where('store_id', $storeId)->findOrFail($orderId);

        $dispatches = AiDispatch::with('deliveryMan')
            ->where('order_id', $order->id)->latest()->get();

        return response()->json(['success' => true, 'data' => ['order' => $order, 'ai_dispatches' => $dispatches]]);
    }

    public function dispatches(Request $request)
    {
        $storeId = $this->getStoreId($request);
        $orderIds = Order::where('store_id', $storeId)->pluck('id');

        $dispatches = AiDispatch::with(['deliveryMan', 'order'])
            ->where('source_type', 'order_anywhere')
            ->whereIn('order_id', $orderIds)->latest()->paginate(50);

        return response()->json(['success' => true, 'data' => $dispatches]);
    }
}
