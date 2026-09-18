<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService;
use Illuminate\Http\Request;

class UrbanGoodzFleetOperationsController extends Controller
{
    public function __construct(private UrbanGoodzDriverNetworkService $driverNetwork) {}

    /**
     * Platform-wide (no vendor filter) command pulse: driver counts by
     * network_dispatch_status and today's order counts by outcome, reusing
     * the same UrbanGoodzDriverNetworkService::fleetOperationsSummary()
     * aggregation the vendor-scoped mobile summary endpoint calls, plus a
     * list of currently active deliveries for the table.
     */
    public function index(Request $request)
    {
        $summary = $this->driverNetwork->fleetOperationsSummary();

        $activeDeliveries = Order::withoutGlobalScopes()
            ->with(['store', 'delivery_man'])
            ->whereNotNull('delivery_man_id')
            ->whereIn('order_status', ['confirmed', 'processing', 'handover', 'picked_up'])
            ->latest('id')
            ->paginate(25);

        return view('admin-views.urban-goodz.fleet-operations.index', compact('summary', 'activeDeliveries'));
    }
}
