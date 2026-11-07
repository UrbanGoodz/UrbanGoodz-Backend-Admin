<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderAiDispatchBusinessController extends Controller
{
    /**
     * The calling company's id. This used to read the business USER's id, which is a
     * different number space and so matched the wrong company's rows.
     */
    private function clientId(): int
    {
        return (int) auth('business')->user()->business_client_id;
    }

    /**
     * Order Anywhere dispatches visible to a company: the ones tagged to it, or run by
     * one of its own drivers. Never "every Order Anywhere dispatch" (customer PII).
     */
    private function visibleDispatches()
    {
        $clientId = $this->clientId();

        return AiDispatch::where('source_type', 'order_anywhere')
            ->where(function ($q) use ($clientId) {
                $q->where('business_client_id', $clientId)
                    ->orWhereHas('deliveryMan', fn ($d) => $d->where('business_client_id', $clientId));
            });
    }

    public function dispatches(Request $request)
    {
        $dispatches = $this->visibleDispatches()
            ->with(['order.deliveryMan', 'deliveryMan'])
            ->latest()->paginate(50);

        return response()->json(['success' => true, 'data' => $dispatches]);
    }

    public function show($id)
    {
        $dispatch = $this->visibleDispatches()
            ->with(['order.store', 'order.deliveryMan', 'order.user'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $dispatch]);
    }
}
