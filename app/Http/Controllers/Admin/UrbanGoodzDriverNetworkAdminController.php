<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrbanGoodzDriverNetworkAdminController extends Controller
{
    public function __construct(
        private readonly UrbanGoodzDriverNetworkService $driverNetwork
    ) {}

    /**
     * Driver Network Capacity and Recruiting Pipeline Overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $zoneId = $request->has('zone_id') ? (int) $request->input('zone_id') : null;
        $capacity = $this->driverNetwork->getNetworkCapacity($zoneId);

        $pendingApprovals = DeliveryMan::where('admin_approval_status', 'pending')
            ->latest('id')
            ->get(['id', 'f_name', 'l_name', 'phone', 'ownership_type', 'vendor_id', 'created_at']);

        return response()->json([
            'success' => true,
            'capacity' => $capacity,
            'pending_approvals' => $pendingApprovals,
        ]);
    }

    /**
     * Admin review and final approval of a driver.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $adminId = auth('admin')->id() ?? 1;
        $res = $this->driverNetwork->approveDriver($id, $adminId);

        return response()->json($res);
    }

    /**
     * Admin suspension authority over a driver.
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $reason = $request->input('reason', 'Administrative suspension');
        $res = $this->driverNetwork->suspendDriver($id, $reason);

        return response()->json($res);
    }

    /**
     * Reactivate an inactive qualified driver.
     */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        $driver = DeliveryMan::findOrFail($id);
        $driver->update([
            'active' => 1,
            'admin_approval_status' => 'approved',
            'network_dispatch_status' => UrbanGoodzDriverNetworkService::STATUS_AVAILABLE,
        ]);

        return response()->json([
            'success' => true,
            'driver_id' => $id,
            'message' => "Driver #{$id} ({$driver->f_name}) reactivated.",
        ]);
    }

    /**
     * Market shortage analysis.
     */
    public function shortageAnalysis(Request $request): JsonResponse
    {
        $market = $request->input('market', 'Houston');
        $shortage = (int) $request->input('shortage', 10);

        $analysis = $this->driverNetwork->analyzeShortageAndRecommend($market, $shortage);
        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }
}
