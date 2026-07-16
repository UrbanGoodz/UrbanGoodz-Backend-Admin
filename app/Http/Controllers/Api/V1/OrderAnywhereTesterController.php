<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderAnywhereTesterController extends Controller
{
    public function customerRequests(Request $request)
    {
        $customerId = $this->customerId($request);

        $records = OrderAnywhereRequest::query()
            ->where('customer_id', $customerId)
            ->when($request->input('vendor_id'), fn ($query, $vendorId) => $query->where('vendor_id', $vendorId))
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function adminRequests(Request $request)
    {
        $records = OrderAnywhereRequest::query()
            ->when($request->input('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($request->input('vendor_id'), fn ($query, $vendorId) => $query->where('vendor_id', $vendorId))
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'store_vendor_name' => ['nullable', 'string', 'max:255'],
            'store_vendor_address_or_website' => ['nullable', 'string'],
            'request_details' => ['nullable', 'string'],
            'item_details' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'budget_estimate' => ['nullable', 'numeric', 'min:0'],
            'admin_notes' => ['nullable', 'string'],
            'vendor_id' => ['nullable', 'integer'],
        ]);

        $record = OrderAnywhereRequest::create(array_merge($data, [
            'customer_id' => $this->customerId($request),
            'request_number' => OrderAnywhereRequest::nextRequestNumber(),
            'status' => 'pending_review',
            'metadata' => $request->all(),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Order Anywhere request submitted for admin review.',
            'data' => $record,
        ], 201);
    }

    public function show(Request $request, $record)
    {
        return response()->json([
            'success' => true,
            'data' => $this->findCustomerRecord($request, $record),
        ]);
    }

    public function updateStatus(Request $request, $record)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(OrderAnywhereRequest::STATUSES)],
            'request_status' => ['nullable', Rule::in(OrderAnywhereRequest::STATUSES)],
            'admin_notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $model = $this->findRecord($record);
        $newStatus = $data['status'] ?? $data['request_status'] ?? 'reviewing';
        $oldStatus = $model->status;

        if ($oldStatus !== $newStatus) {
            $model->transitionTo($newStatus);
        }

        $model->admin_notes = $data['admin_notes'] ?? $model->admin_notes;
        $model->reviewed_at = now();
        $model->save();
        $model->logStatusTransition($oldStatus, $newStatus, $data['reason'] ?? null);

        return $this->updated($model, 'Order Anywhere status updated.');
    }

    public function vendorUpdate(Request $request, $record)
    {
        $data = $request->validate([
            'vendor_status' => ['nullable', 'string', 'max:255'],
            'vendor_notes' => ['nullable', 'string'],
            'vendor_quote_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $model = $this->findVendorRecord($request, $record);
        $model->vendor_status = $data['vendor_status'] ?? $model->vendor_status;
        $model->vendor_notes = $data['vendor_notes'] ?? $model->vendor_notes;
        $model->vendor_quote_amount = $data['vendor_quote_amount'] ?? $model->vendor_quote_amount;

        if (($data['vendor_status'] ?? null) === 'accepted') {
            $model->transitionTo('vendor_accepted');
        } elseif (($data['vendor_status'] ?? null) === 'rejected') {
            $model->transitionTo('rejected');
        } else {
            $model->save();
        }

        return $this->updated($model, 'Order Anywhere vendor response updated.');
    }

    public function authorizePayment(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'authorized_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'authorization_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $model = $payments->authorizeOrderAnywhere($this->findCustomerRecord($request, $record), array_merge($data, [
            'source' => 'customer_api',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Order Anywhere payment authorized.',
            'data' => $model,
        ]);
    }

    public function uploadReceipt(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'receipt_path' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $data['receipt_path'] ?? null;

        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('urban-goodz/order-anywhere/receipts', 'public');
        }

        abort_if(! $path, 422, 'Receipt file or receipt_path is required.');

        $model = $payments->storeReceipt($this->findCustomerRecord($request, $record), $path);

        return response()->json([
            'success' => true,
            'message' => 'Order Anywhere receipt uploaded.',
            'data' => $model,
        ]);
    }

    public function addNotes(Request $request, $record)
    {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $model = $this->findRecord($record);
        $model->admin_notes = $data['admin_notes'] ?? $model->admin_notes;
        $model->reviewed_at = now();
        $model->save();

        return $this->updated($model, 'Order Anywhere notes updated.');
    }

    public function createPaymentLink(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $this->findRecord($record);
        $result = $payments->createPaymentLink($model, $data);

        return response()->json([
            'success' => true,
            'message' => 'Payment link created.',
            'data' => $result,
        ]);
    }

    public function assignDriver(Request $request, $record)
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $model = $this->findRecord($record);
        $model->assigned_delivery_man_id = $data['driver_id'];
        $model->driver_task_status = 'assigned';
        $model->admin_notes = $data['admin_notes'] ?? $model->admin_notes;
        $model->reviewed_at = now();
        $model->transitionTo('approved');

        return $this->updated($model, 'Driver assigned to Order Anywhere request.');
    }

    public function driverAvailable(Request $request)
    {
        $records = OrderAnywhereRequest::query()
            ->where('assigned_delivery_man_id', $this->driverId())
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->latest()
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $records]);
    }

    public function driverAccept(Request $request, $record)
    {
        $request->merge(['driver_task_status' => 'accepted']);

        return $this->driverStatus($request, $record);
    }

    public function driverStatus(Request $request, $record)
    {
        $data = $request->validate([
            'driver_task_status' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'driver_notes' => ['nullable', 'string'],
        ]);

        $driverStatus = $data['driver_task_status'] ?? $data['status'] ?? 'in_progress';
        $status = match ($driverStatus) {
            'picked_up' => 'picked_up',
            'en_route' => 'out_for_delivery',
            'delivered' => 'completed',
            'issue_reported' => 'reviewing',
            default => 'shopping',
        };

        $model = $this->findDriverRecord($record);
        $model->driver_task_status = $driverStatus;
        $model->driver_notes = $data['driver_notes'] ?? $model->driver_notes;
        $model->transitionTo($status);

        return $this->updated($model, 'Driver task status updated.');
    }

    public function driverIssue(Request $request, $record)
    {
        $data = $request->validate([
            'driver_notes' => ['nullable', 'string'],
            'issue' => ['nullable', 'string'],
        ]);

        $model = $this->findDriverRecord($record);
        $model->driver_task_status = 'issue_reported';
        $model->driver_notes = $data['driver_notes'] ?? $data['issue'] ?? $model->driver_notes;
        $model->transitionTo('reviewing');

        return $this->updated($model, 'Driver issue reported.');
    }

    private function findRecord($record): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::query()
            ->where('id', $record)
            ->orWhere('request_number', $record)
            ->firstOrFail();
    }

    private function findCustomerRecord(Request $request, $record): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::query()
            ->where('customer_id', $this->customerId($request))
            ->where(function ($query) use ($record) {
                $query->where('id', $record)
                    ->orWhere('request_number', $record);
            })
            ->firstOrFail();
    }

    private function findVendorRecord(Request $request, $record): OrderAnywhereRequest
    {
        $vendor = $request->get('vendor');
        abort_unless($vendor, 401, 'Unauthorized.');

        return OrderAnywhereRequest::query()
            ->where('vendor_id', $vendor->id)
            ->where(function ($query) use ($record) {
                $query->where('id', $record)
                    ->orWhere('request_number', $record);
            })
            ->firstOrFail();
    }

    private function findDriverRecord($record): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::query()
            ->where('assigned_delivery_man_id', $this->driverId())
            ->where(function ($query) use ($record) {
                $query->where('id', $record)
                    ->orWhere('request_number', $record);
            })
            ->firstOrFail();
    }

    private function customerId(Request $request): int
    {
        $customer = $request->user('api') ?? $request->user();
        abort_unless($customer, 401, 'Unauthorized.');

        return (int) $customer->id;
    }

    private function driverId(): int
    {
        $driverId = auth('delivery_men')->id();
        abort_unless($driverId, 401, 'Unauthorized.');

        return (int) $driverId;
    }

    private function updated(OrderAnywhereRequest $record, string $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $record->fresh(),
        ]);
    }
}
