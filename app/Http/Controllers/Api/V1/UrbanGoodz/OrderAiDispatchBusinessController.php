<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderAiDispatchBusinessController extends Controller
{
    public function dispatches(Request $request)
    {
        $clientId = auth('business')->user()->id ?? auth('business')->id();

        $dispatches = AiDispatch::with(['order.deliveryMan', 'deliveryMan'])
            ->where('source_type', 'order_anywhere')
            ->orWhere('business_client_id', $clientId)
            ->latest()->paginate(50);

        return response()->json(['success' => true, 'data' => $dispatches]);
    }

    public function show($id)
    {
        $dispatch = AiDispatch::with(['order.store', 'order.deliveryMan', 'order.user'])
            ->where('source_type', 'order_anywhere')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $dispatch]);
    }
}
