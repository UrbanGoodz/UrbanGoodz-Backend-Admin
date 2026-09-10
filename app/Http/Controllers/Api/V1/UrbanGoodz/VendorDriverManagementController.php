<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorDriverManagementController extends Controller
{
    public function __construct(
        private readonly UrbanGoodzDriverNetworkService $driverNetwork
    ) {}

    /**
     * List all drivers associated with the authenticated vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $drivers = DeliveryMan::where('vendor_id', $vendorId)
            ->latest('id')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => trim("{$d->f_name} {$d->l_name}"),
                'phone' => $d->phone,
                'email' => $d->email,
                'approval_status' => $d->admin_approval_status,
                'network_dispatch_status' => $d->network_dispatch_status,
                'active' => (bool) $d->active,
                'available_for_marketplace' => (bool) $d->available_for_marketplace,
                'pay_model' => $d->pay_model,
                'pay_rate' => (float) $d->pay_rate,
                'current_orders' => (int) $d->current_orders,
            ]);

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }

    /**
     * Add a driver to the vendor fleet (awaits Urban Goodz approval).
     */
    public function store(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $data = $request->validate([
            'f_name' => ['required', 'string', 'max:100'],
            'l_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'unique:delivery_men,phone'],
            'email' => ['nullable', 'email', 'max:100'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'pay_model' => ['nullable', 'string', 'in:per_order,per_mile,flat_route,hourly,percentage'],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'available_for_marketplace' => ['nullable', 'boolean'],
        ]);

        $driver = $this->driverNetwork->addVendorDriver($vendorId, $data);

        return response()->json([
            'success' => true,
            'message' => 'Driver registered successfully and submitted for Urban Goodz verification.',
            'data' => [
                'id' => $driver->id,
                'name' => trim("{$driver->f_name} {$driver->l_name}"),
                'approval_status' => $driver->admin_approval_status,
                'network_dispatch_status' => $driver->network_dispatch_status,
            ],
        ], 201);
    }

    /**
     * Configure compensation and marketplace availability for a vendor driver.
     */
    public function updateCompensation(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        $driver = DeliveryMan::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found for your business.'], 404);
        }

        $data = $request->validate([
            'pay_model' => ['nullable', 'string', 'in:per_order,per_mile,flat_route,hourly,percentage'],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'available_for_marketplace' => ['nullable', 'boolean'],
        ]);

        $res = $this->driverNetwork->configureCompensation($id, $data);
        return response()->json($res);
    }

    /**
     * Remove driver association from the vendor.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        $driver = DeliveryMan::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found for your business.'], 404);
        }

        $driver->update([
            'vendor_id' => null,
            'ownership_type' => UrbanGoodzDriverNetworkService::OWNERSHIP_UG,
            'available_for_marketplace' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Driver association removed.',
        ]);
    }

    /**
     * Assign vendor driver to a business order (blocks UG dispatch).
     */
    public function assignOrder(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        $driver = DeliveryMan::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $orderId = (int) $request->input('order_id');
        $res = $this->driverNetwork->assignToBusinessOrder($id, $orderId);

        return response()->json($res, $res['success'] ? 200 : 422);
    }

    /**
     * Release vendor driver from business order (makes available for UG if opted in).
     */
    public function releaseDriver(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        $driver = DeliveryMan::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $orderId = (int) $request->input('order_id');
        $res = $this->driverNetwork->releaseFromBusinessOrder($id, $orderId);

        return response()->json($res, $res['success'] ? 200 : 422);
    }

    private function authenticatedVendorId(Request $request): ?int
    {
        $user = $request->user();
        if ($user && isset($user->vendor_id)) {
            return (int) $user->vendor_id;
        }

        if ($user && isset($user->id)) {
            return (int) $user->id;
        }

        return null;
    }
}
