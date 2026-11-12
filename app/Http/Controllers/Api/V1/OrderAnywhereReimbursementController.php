<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereReimbursement;
use App\Services\OrderAnywhereReimbursementService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderAnywhereReimbursementController extends Controller
{
    /** Driver: is reimbursement mode on, and what is my claim? */
    public function driverShow(Request $request, int $requestId, OrderAnywhereReimbursementService $svc)
    {
        $driverId = (int) auth('delivery_men')->id();
        $order = OrderAnywhereRequest::find($requestId);
        if (! $order || (int) $order->assigned_delivery_man_id !== $driverId) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        [$authorized, $cap] = $svc->cap($order);
        $claim = UrbanGoodzOrderAnywhereReimbursement::where('order_anywhere_request_id', $requestId)
            ->latest('id')->first();

        return response()->json(['success' => true, 'data' => [
            'enabled' => OrderAnywhereReimbursementService::enabled(),
            'authorized_total' => $authorized,
            'max_total' => $cap,
            'claim' => $claim,
        ]]);
    }

    public function driverSubmit(Request $request, int $requestId, OrderAnywhereReimbursementService $svc)
    {
        $driverId = (int) auth('delivery_men')->id();
        if (! OrderAnywhereReimbursementService::enabled()) {
            return response()->json(['success' => false, 'message' => 'Reimbursement purchasing is not enabled.'], 409);
        }
        $order = OrderAnywhereRequest::find($requestId);
        if (! $order || (int) $order->assigned_delivery_man_id !== $driverId) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        $data = $request->validate([
            'total' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $claim = $svc->submit($order, $driverId, (float) $data['total'], $request->file('receipt'), $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Receipt submitted. You will be reimbursed after approval.', 'data' => $claim]);
    }

    public function adminIndex(Request $request)
    {
        $status = $request->query('status', 'pending');
        $q = UrbanGoodzOrderAnywhereReimbursement::query()->latest('id');
        if ($status !== 'all') {
            $q->where('status', $status);
        }

        return response()->json(['success' => true, 'data' => $q->limit(200)->get()]);
    }

    public function adminReceipt(int $id)
    {
        $claim = UrbanGoodzOrderAnywhereReimbursement::findOrFail($id);
        $path = $claim->getRawOriginal('receipt_path');
        abort_if(! $path || ! \Storage::disk('local')->exists($path), 404);

        return \Storage::disk('local')->response($path);
    }

    public function adminApprove(Request $request, int $id, OrderAnywhereReimbursementService $svc)
    {
        $data = $request->validate([
            'approved_total' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $claim = $svc->approve($id, (int) auth('admin')->id(), isset($data['approved_total']) ? (float) $data['approved_total'] : null, $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $claim]);
    }

    public function adminReject(Request $request, int $id, OrderAnywhereReimbursementService $svc)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        try {
            $claim = $svc->reject($id, (int) auth('admin')->id(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $claim]);
    }
}
