<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class OrderAnywhereController extends Controller
{
    public function createFromOption(Request $request, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'merchant_name' => ['required', 'string', 'max:255'],
            'merchant_address_or_website' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'urgency' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->guarded(function () use ($request, $data, $payments) {
            $user = $request->user();
            $price = (float) $data['price'];
            // Fees are the platform's, never the client's (a client-supplied fee of 0
            // meant a customer could quote themselves free delivery).
            $configuredFee = Helpers::get_business_settings('order_anywhere_estimated_delivery_fee');
            $deliveryFee = is_numeric($configuredFee) ? (float) $configuredFee : 7.99;
            $serviceFee = max(5.00, round($price * 0.15, 2));
            $tax = round($price * 0.0825, 2);
            $quoteAmount = round($price + $deliveryFee + $serviceFee + $tax, 2);

            $record = OrderAnywhereRequest::create([
                'request_number' => OrderAnywhereRequest::nextRequestNumber(),
                'customer_id' => $user?->id,
                'customer_name' => $user ? ($user->f_name . ' ' . $user->l_name) : 'Customer',
                'customer_phone' => $data['contact_phone'] ?? $user?->phone,
                'customer_email' => $user?->email,
                'store_vendor_name' => $data['merchant_name'],
                'store_vendor_address_or_website' => $data['merchant_address_or_website'] ?? null,
                'request_details' => $data['title'],
                'item_details' => $data['title'],
                'quantity' => 1,
                'budget_estimate' => $price,
                'item_subtotal' => $price,
                'delivery_fee' => $deliveryFee,
                'service_fee' => $serviceFee,
                'tax' => $tax,
                'quote_amount' => $quoteAmount,
                'authorized_amount' => $quoteAmount,
                'status' => 'quote_ready',
                'payment_status' => 'awaiting_payment',
                'fulfillment_type' => 'external_merchant',
                'dropoff_address' => $data['delivery_address'],
                'delivery_address' => $data['delivery_address'],
                'admin_notes' => 'Created directly from Skylar AI Concierge commerce discovery.',
                'metadata' => [
                    'source' => 'skylar_commerce_discovery',
                    'customer_notes' => $data['customer_notes'] ?? null,
                    'urgency' => $data['urgency'] ?? 'standard',
                ],
            ]);

            try {
                $session = $payments->createPaymentSession($record, ['amount' => $quoteAmount]);
                if (!empty($session['payment_url'])) {
                    $record->update([
                        'payment_url' => $session['payment_url'],
                        'payment_link_id' => $session['payment_link_id'] ?? null,
                        'payment_status' => 'payment_session_created',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not create immediate payment session for Order Anywhere option: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Order Anywhere request created from option.',
                'data' => $record->fresh(),
                'payment_url' => $record->payment_url,
            ], 201);
        });
    }

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
        ]);

        // admin_notes and vendor_id are staff decisions. They used to be accepted
        // from the customer, who could pre-write the reviewer's notes or route
        // their own request to any vendor. metadata no longer stores the raw
        // request body either.
        $record = OrderAnywhereRequest::create(array_merge($data, [
            'customer_id' => $this->customerId($request),
            'request_number' => OrderAnywhereRequest::nextRequestNumber(),
            'status' => 'pending_review',
            'metadata' => ['source' => 'customer_api', 'submitted_fields' => array_keys($data)],
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Order Anywhere request submitted for admin review.',
            'data' => $record,
        ], 201);
    }

    /**
     * A rough delivery-fee preview shown before the customer submits a
     * request. Order Anywhere has no store/distance data yet at this point
     * (the "store" is free text, not a lookup) - admin sets the real
     * `delivery_fee` during review - so this is a configurable base figure,
     * not a computed quote.
     */
    public function estimate(Request $request)
    {
        $fee = Helpers::get_business_settings('order_anywhere_estimated_delivery_fee');

        return response()->json([
            'success' => true,
            'fee' => is_numeric($fee) ? (float) $fee : 8.0,
        ]);
    }

    public function show(Request $request, $record)
    {
        return response()->json([
            'success' => true,
            'data' => $this->findCustomerRecord($request, $record),
        ]);
    }

    public function updateStatus(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(OrderAnywhereRequest::STATUSES)],
            'request_status' => ['nullable', Rule::in(OrderAnywhereRequest::STATUSES)],
            'admin_notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $model = $this->findRecord($record);
        $newStatus = $data['status'] ?? $data['request_status'] ?? 'sourcing';
        $oldStatus = $model->status;

        return $this->guarded(function () use ($model, $oldStatus, $newStatus, $data, $payments) {
            if ($oldStatus !== $newStatus) {
                if ($newStatus === 'cancelled') {
                    // Voids the hold / refunds the capture; a bare status change left the
                    // customer's money where it was.
                    $model = $payments->cancelAndReturnFunds($model, $data['reason'] ?? 'Cancelled by admin');
                } else {
                    $model->transitionTo($newStatus);
                }
            }

            $model->admin_notes = $data['admin_notes'] ?? $model->admin_notes;
            $model->reviewed_at = now();
            $model->save();
            $model->logStatusTransition($oldStatus, $newStatus, $data['reason'] ?? null);

            return $this->updated($model, 'Order Anywhere status updated.');
        });
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

        return $this->guarded(function () use ($model, $data) {
            if (($data['vendor_status'] ?? null) === 'accepted') {
                $model->transitionTo('vendor_accepted');
            } elseif (($data['vendor_status'] ?? null) === 'rejected') {
                $model->transitionTo('rejected');
            } else {
                $model->save();
            }

            return $this->updated($model, 'Order Anywhere vendor response updated.');
        });
    }

    public function authorizePayment(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->guarded(function () use ($request, $record, $payments, $data) {
            $model = $this->findCustomerRecord($request, $record);

            // The customer never chooses what is charged. The amount is the quote
            // (or the final amount once set); a client-supplied authorized_amount
            // let anyone authorize $0.01 against a $60 order. The authorization
            // reference comes from the payment provider, not from the client.
            $amount = (float) ($model->final_amount ?? $model->quote_amount);
            if ($amount <= 0) {
                throw new \InvalidArgumentException('This request has not been quoted yet.');
            }

            $model = $payments->authorizeCustomerPayment($model, [
                'authorized_amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'source' => 'customer_api',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order Anywhere payment authorized.',
                'data' => $model,
            ]);
        });
    }

    public function uploadReceipt(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            // Only a path this endpoint itself produced. An arbitrary string let a
            // customer point their request at any file on the public disk.
            'receipt_path' => ['nullable', 'string', 'max:255', 'starts_with:urban-goodz/order-anywhere/receipts/', 'not_regex:/\.\./'],
        ]);

        $path = $data['receipt_path'] ?? null;

        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('urban-goodz/order-anywhere/receipts', 'public');
        }

        abort_if(! $path, 422, 'Receipt file or receipt_path is required.');

        return $this->guarded(function () use ($request, $record, $payments, $path) {
            $model = $payments->storeReceipt($this->findCustomerRecord($request, $record), $path);

            return response()->json([
                'success' => true,
                'message' => 'Order Anywhere receipt uploaded.',
                'data' => $model,
            ]);
        });
    }

    public function cancelRequest(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $this->findCustomerRecord($request, $record);

        return $this->guarded(function () use ($model, $payments, $data) {
            if (in_array($model->status, ['completed', 'delivered', 'rejected', 'cancelled'])) {
                throw new \InvalidArgumentException('Cannot cancel a request that is already in a terminal state.');
            }

            $payments->cancelOrder($model, $data['reason'] ?? '');

            return response()->json([
                'success' => true,
                'message' => 'Order Anywhere request cancelled.',
                'data' => $model->fresh(),
            ]);
        });
    }

    /**
     * Test-payment endpoint used by development clients (vendor app / QA harness)
     * to exercise the full authorize → capture → split path without a real PSP.
     * Only available outside production, mirroring the staged_test gateway.
     */
    public function testPayment(Request $request, $record, UrbanGoodzPaymentService $payments)
    {
        abort_if(app()->environment('production'), 403, 'Test payments are disabled in production.');

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'capture_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $model = $this->findCustomerRecord($request, $record);

        return $this->guarded(function () use ($model, $payments, $data) {
            if (in_array($model->status, ['completed', 'cancelled', 'rejected'])) {
                throw new \InvalidArgumentException('Cannot test-pay a request that is already in a terminal state.');
            }

            $amount = (float) ($data['amount'] ?? $model->quote_amount ?? $model->final_amount ?? $model->item_subtotal);

            $payments->authorizeCustomerPayment($model, [
                'authorized_amount' => $amount,
                'payment_method' => 'test_payment',
                'authorization_reference' => 'test_payment_' . now()->format('YmdHis'),
                'provider' => 'staged_test',
                'source' => 'test_payment',
            ]);

            $payments->captureCustomerPayment($model->fresh(), [
                'captured_amount' => $amount,
                'capture_reference' => $data['capture_reference'] ?? 'test_payment_' . now()->timestamp,
                'provider' => 'staged_test',
                'source' => 'test_payment',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test payment completed.',
                'data' => $model->fresh(),
            ]);
        });
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

        return $this->guarded(function () use ($model, $payments, $data) {
            $result = $payments->createPaymentSession($model, $data);

            return response()->json([
                'success' => true,
                'message' => 'Payment link created.',
                'data' => $result,
            ]);
        });
    }

    /**
     * Admin override of automatic dispatch: put a specific driver on the job. Works whether
     * the request is still `authorized` or has already been auto-approved, and withdraws any
     * offer already out to an auto-selected driver so two drivers are never both on it.
     */
    public function assignDriver(Request $request, $record)
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:delivery_men,id'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $model = $this->findRecord($record);
        $model->assigned_delivery_man_id = $data['driver_id'];
        $model->driver_task_status = 'assigned';
        $model->admin_notes = $data['admin_notes'] ?? $model->admin_notes;
        $model->reviewed_at = now();

        return $this->guarded(function () use ($model) {
            if ($model->status !== 'approved') {
                $model->transitionTo('approved');
            } else {
                $model->save();
            }

            if ($model->order_id) {
                \App\Models\AiDispatch::where('order_id', $model->order_id)
                    ->whereIn('status', [\App\Models\AiDispatch::STATUS_PENDING_DRIVER, \App\Models\AiDispatch::STATUS_SENT, \App\Models\AiDispatch::STATUS_VIEWED])
                    ->get()
                    ->each(fn ($d) => $d->cancelDispatch('Admin assigned a specific driver'));
            }

            return $this->updated($model, 'Driver assigned to Order Anywhere request.');
        });
    }

    /** Auto-dispatch switches an admin can change at runtime (radius, offer window, timeouts). */
    public function dispatchSettings()
    {
        return response()->json(['success' => true, 'data' => \App\Services\OrderAnywhereDispatchSettings::all()]);
    }

    public function updateDispatchSettings(Request $request)
    {
        $data = $request->validate([
            'auto_dispatch' => ['sometimes', 'boolean'],
            'radius_miles' => ['sometimes', 'numeric', 'min:1', 'max:500'],
            'offer_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'escalate_after_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'cancel_after_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
        ]);

        return response()->json(['success' => true, 'data' => \App\Services\OrderAnywhereDispatchSettings::update($data)]);
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

    /**
     * What each driver step means for each fulfilment path. A driver moves the request
     * ONE legal step at a time; the previous mapping (accepted -> shopping,
     * delivered -> completed) pointed at transitions the state machine forbids, so
     * accepting a job and finishing a delivery both failed with 422.
     *
     * external_merchant: approved -> shopper_assigned -> shopper_accepted -> shopping
     *                    -> purchased -> picked_up -> out_for_delivery -> delivered -> completed
     * participating_vendor: ready_for_pickup -> driver_assigned -> picked_up
     *                    -> out_for_delivery -> delivered -> completed
     */
    private const DRIVER_STEPS = [
        'external_merchant' => [
            'accepted'  => ['approved' => ['shopper_assigned', 'shopper_accepted'], 'shopper_assigned' => ['shopper_accepted']],
            'shopping'  => ['shopper_accepted' => ['shopping']],
            'purchased' => ['shopping' => ['purchased']],
            'picked_up' => ['purchased' => ['picked_up']],
            'en_route'  => ['picked_up' => ['out_for_delivery']],
            'delivered' => ['out_for_delivery' => ['delivered', 'completed']],
        ],
        'participating_vendor' => [
            'accepted'  => ['ready_for_pickup' => ['driver_assigned']],
            'picked_up' => ['driver_assigned' => ['picked_up']],
            'en_route'  => ['picked_up' => ['out_for_delivery']],
            'delivered' => ['out_for_delivery' => ['delivered', 'completed']],
        ],
    ];

    public function driverStatus(Request $request, $record)
    {
        $data = $request->validate([
            'driver_task_status' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'driver_notes' => ['nullable', 'string'],
        ]);

        $driverStatus = $data['driver_task_status'] ?? $data['status'] ?? 'in_progress';

        $model = $this->findDriverRecord($record);
        $model->driver_notes = $data['driver_notes'] ?? $model->driver_notes;

        return $this->guarded(function () use ($model, $driverStatus) {
            if ($driverStatus === 'issue_reported') {
                $model->driver_task_status = $driverStatus;
                $model->transitionTo('sourcing');

                return $this->updated($model, 'Driver issue reported.');
            }

            $path = self::DRIVER_STEPS[$model->fulfillment_type ?: 'external_merchant'] ?? [];
            $steps = $path[$driverStatus][$model->status] ?? null;

            if ($steps === null) {
                throw new \InvalidArgumentException("Cannot move to '{$driverStatus}' from '{$model->status}'.");
            }

            // The driver pays with the temporary purchase card the platform issued. When
            // a card exists for this order, "purchased" needs a recorded card purchase;
            // otherwise a driver could claim the purchase without spending the funds.
            if ($driverStatus === 'purchased' && $model->card_issued) {
                $spent = \App\Models\UrbanGoodzOrderAnywhereCardRequest::where('id', $model->card_request_id)
                    ->where('captured_amount', '>', 0)->exists();
                if (! $spent) {
                    throw new \InvalidArgumentException('Complete the purchase with the issued card before marking it purchased.');
                }
            }

            $model->driver_task_status = $driverStatus;
            foreach ($steps as $next) {
                $model->transitionTo($next);
            }

            return $this->updated($model, 'Driver task status updated.');
        });
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

        return $this->guarded(function () use ($model) {
            $model->transitionTo('sourcing');

            return $this->updated($model, 'Driver issue reported.');
        });
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

    /**
     * The state machine (Model::transitionTo) and UrbanGoodzPaymentService
     * both throw InvalidArgumentException for legitimate rule violations
     * (wrong status, payment not authorized, etc.) - not implementation
     * bugs. Uncaught, those surfaced as opaque 500s on a payment flow.
     * Translate them into a real 422 the client can act on.
     */
    private function guarded(\Closure $action)
    {
        try {
            return $action();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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