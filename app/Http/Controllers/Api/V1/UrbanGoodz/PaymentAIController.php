<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\OrderTransaction;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\UrbanGoodzRefundRequest;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentAIController extends Controller
{
    public function __construct(
        private UrbanGoodzAIService $ai,
        private UrbanGoodzPaymentService $paymentService
    ) {}

    // ─── EXPLAIN PAYMENT FLOW ─────────────────────────────────────────────

    public function explainPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'integer'],
            'transaction_id' => ['nullable', 'string'],
        ]);

        $context = [];

        if (!empty($data['order_id'])) {
            $order = Order::with(['details.item', 'transactions', 'customer', 'store'])->find($data['order_id']);
            if ($order) {
                $context['order'] = [
                    'order_number' => $order->order_number,
                    'status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'amount' => $order->order_amount,
                    'items' => $order->details->map(fn($d) => $d->item->name ?? 'Unknown')->toArray(),
                ];
            }
        }

        if (!empty($data['request_id'])) {
            $requestModel = OrderAnywhereRequest::with(['transactions', 'ledgers', 'splits'])->find($data['request_id']);
            if ($requestModel) {
                $context['order_anywhere'] = [
                    'request_number' => $requestModel->request_number,
                    'status' => $requestModel->status,
                    'payment_status' => $requestModel->payment_status,
                    'quote_amount' => $requestModel->quote_amount,
                    'final_amount' => $requestModel->final_amount,
                    'captured_amount' => $requestModel->captured_amount,
                    'refunded_amount' => $requestModel->refunded_amount,
                ];
            }
        }

        if (!empty($data['transaction_id'])) {
            $txn = OrderTransaction::with('order')->where('transaction_id', $data['transaction_id'])->first();
            if ($txn) {
                $context['transaction'] = [
                    'id' => $txn->transaction_id,
                    'amount' => $txn->amount,
                    'status' => $txn->payment_status,
                    'method' => $txn->payment_method,
                    'type' => $txn->type,
                ];
            }
        }

        $prompt = "You are a payment support assistant for Urban Goodz.
A customer is asking about a payment issue. Explain the payment flow in plain language.

Context: " . json_encode($context, JSON_PRETTY_PRINT) . "

Customer query: {$data['query']}

Provide:
1. What happened (plain language)
2. Current status
3. Next steps if any
4. Whether human review is needed";

        $response = $this->ai->chat($prompt, $data['query'], $context);

        return response()->json([
            'success' => true,
            'explanation' => $response,
            'context_used' => !empty($context),
        ]);
    }

    // ─── PAYMENT STATUS WITH AI SUMMARY ────────────────────────────────────

    public function paymentStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'integer'],
            'transaction_id' => ['nullable', 'string'],
        ]);

        $status = [];
        $transactions = [];

        if (!empty($data['order_id'])) {
            $order = Order::with('transactions')->find($data['order_id']);
            if ($order) {
                $status['order'] = [
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                    'order_status' => $order->order_status,
                    'amount' => $order->order_amount,
                ];
                $transactions = $order->transactions->toArray();
            }
        }

        if (!empty($data['request_id'])) {
            $req = OrderAnywhereRequest::with('transactions')->find($data['request_id']);
            if ($req) {
                $status['order_anywhere'] = [
                    'request_number' => $req->request_number,
                    'status' => $req->status,
                    'payment_status' => $req->payment_status,
                    'quote' => $req->quote_amount,
                    'final' => $req->final_amount,
                    'captured' => $req->captured_amount,
                    'refunded' => $req->refunded_amount,
                ];
                $transactions = array_merge($transactions, $req->transactions->toArray());
            }
        }

        if (!empty($data['transaction_id'])) {
            $txn = OrderTransaction::where('transaction_id', $data['transaction_id'])->first();
            if ($txn) {
                $transactions[] = $txn->toArray();
            }
        }

        $summary = 'No payment data found.';
        if (!empty($transactions)) {
            $summary = $this->ai->chat(
                "You are a payment analyst. Summarize these transactions in plain language for a customer.
                Include: total captured, total refunded, net amount, any issues.",
                "Summarize these transactions: " . json_encode($transactions, JSON_PRETTY_PRINT)
            );
        }

        return response()->json([
            'success' => true,
            'status' => $status,
            'transactions' => $transactions,
            'ai_summary' => $summary,
        ]);
    }

    // ─── RECONCILIATION SUGGESTIONS ────────────────────────────────────────

    public function suggestReconciliation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $mismatches = [];

        if (!empty($data['order_id'])) {
            $order = Order::with(['transactions', 'ledgers', 'splits'])->find($data['order_id']);
            if ($order) {
                $mismatches = array_merge($mismatches, $this->checkOrderMismatches($order));
            }
        }

        if (!empty($data['request_id'])) {
            $req = OrderAnywhereRequest::with(['ledgers', 'splits', 'transactions'])->find($data['request_id']);
            if ($req) {
                $mismatches = array_merge($mismatches, $this->checkRequestMismatches($req));
            }
        }

        if (empty($mismatches)) {
            return response()->json([
                'success' => true,
                'message' => 'No payment mismatches found.',
                'recommendations' => [],
            ]);
        }

        $recommendations = $this->ai->chat(
            "You are a payment reconciliation expert. Analyze these payment mismatches and suggest specific corrective actions.
            For each mismatch, provide: action, priority (high/medium/low), and reasoning.",
            "Mismatches found: " . json_encode($mismatches, JSON_PRETTY_PRINT)
        );

        return response()->json([
            'success' => true,
            'mismatches' => $mismatches,
            'recommendations' => $recommendations,
        ]);
    }

    private function checkOrderMismatches(Order $order): array
    {
        $mismatches = [];

        $captured = $order->transactions->where('payment_status', 'captured')->sum('amount');
        if ($captured != $order->order_amount) {
            $mismatches[] = [
                'type' => 'amount_mismatch',
                'entity' => 'order',
                'entity_id' => $order->id,
                'expected' => $order->order_amount,
                'actual' => $captured,
                'description' => "Captured amount (\${$captured}) differs from order amount (\${$order->order_amount})",
            ];
        }

        $refunded = $order->transactions->where('payment_status', 'refunded')->sum('amount');
        $refundRequests = UrbanGoodzRefundRequest::where('order_id', $order->id)
            ->where('status', 'approved')
            ->sum('amount');
        if ($refunded != $refundRequests) {
            $mismatches[] = [
                'type' => 'refund_mismatch',
                'entity' => 'order',
                'entity_id' => $order->id,
                'expected' => $refundRequests,
                'actual' => $refunded,
                'description' => "Refunded amount (\${$refunded}) differs from approved refunds (\${$refundRequests})",
            ];
        }

        $ledgerTotal = UrbanGoodzPaymentLedger::where('payable_type', Order::class)
            ->where('payable_id', $order->id)
            ->where('direction', 'credit')
            ->sum('amount');

        if ($ledgerTotal != $captured) {
            $mismatches[] = [
                'type' => 'ledger_mismatch',
                'entity' => 'order',
                'entity_id' => $order->id,
                'expected' => $captured,
                'actual' => $ledgerTotal,
                'description' => "Ledger total (\${$ledgerTotal}) differs from captured amount (\${$captured})",
            ];
        }

        return $mismatches;
    }

    private function checkRequestMismatches(OrderAnywhereRequest $request): array
    {
        $mismatches = [];

        if ($request->captured_amount != $request->final_amount) {
            $mismatches[] = [
                'type' => 'amount_mismatch',
                'entity' => 'order_anywhere',
                'entity_id' => $request->id,
                'expected' => $request->final_amount,
                'actual' => $request->captured_amount,
                'description' => "Captured amount (\${$request->captured_amount}) differs from final amount (\${$request->final_amount})",
            ];
        }

        $ledgerTotal = UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('direction', 'credit')
            ->sum('amount');

        if ($ledgerTotal != $request->captured_amount) {
            $mismatches[] = [
                'type' => 'ledger_mismatch',
                'entity' => 'order_anywhere',
                'entity_id' => $request->id,
                'expected' => $request->captured_amount,
                'actual' => $ledgerTotal,
                'description' => "Ledger total (\${$ledgerTotal}) differs from captured amount (\${$request->captured_amount})",
            ];
        }

        $splitsTotal = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('status', 'released')
            ->sum('amount');

        if ($splitsTotal != $request->captured_amount) {
            $mismatches[] = [
                'type' => 'splits_mismatch',
                'entity' => 'order_anywhere',
                'entity_id' => $request->id,
                'expected' => $request->captured_amount,
                'actual' => $splitsTotal,
                'description' => "Released splits (\${$splitsTotal}) differ from captured amount (\${$request->captured_amount})",
            ];
        }

        return $mismatches;
    }

    // ─── DISPUTE ASSISTANCE ───────────────────────────────────────────────

    public function disputeAssistance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'string'],
            'reason' => ['required', 'string'],
            'customer_id' => ['required', 'integer'],
        ]);

        $txn = OrderTransaction::with('order')->where('transaction_id', $data['transaction_id'])->first();

        if (!$txn) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        if ($txn->order->user_id != $data['customer_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $context = [
            'transaction' => $txn->toArray(),
            'order' => $txn->order?->toArray(),
            'reason' => $data['reason'],
        ];

        $guidance = $this->ai->chat(
            "You are a dispute resolution specialist for Urban Goodz.
            A customer wants to dispute a charge. Provide:
            1. Whether this is likely a valid dispute
            2. What evidence the customer should provide
            3. What the merchant would need to provide
            4. Estimated timeline",
            "Customer dispute: {$data['reason']}",
            $context
        );

        return response()->json([
            'success' => true,
            'guidance' => $guidance,
            'transaction_id' => $txn->transaction_id,
        ]);
    }

    // ─── MANUAL OVERRIDE (ADMIN ONLY) ─────────────────────────────────────

    public function manualOverride(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:force_capture,force_refund,force_void,release_splits'],
            'entity_type' => ['required', 'string', 'in:order_anywhere,order'],
            'entity_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'admin_id' => ['required', 'integer'],
        ]);

        if (!auth('admin')->check() || auth('admin')->id() != $data['admin_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = match ($data['action']) {
            'force_capture' => $this->forceCapture($data),
            'force_refund' => $this->forceRefund($data),
            'force_void' => $this->forceVoid($data),
            'release_splits' => $this->forceReleaseSplits($data),
            default => ['success' => false, 'message' => 'Unknown action'],
        };

        return response()->json($result);
    }

    private function forceCapture(array $data): array
    {
        $entityType = $data['entity_type'];
        if ($entityType === 'order_anywhere' || $entityType === 'order') {
            $request = OrderAnywhereRequest::findOrFail($data['entity_id']);
            $amount = $data['amount'] ?? $request->authorized_amount;
            $result = $this->paymentService->captureCustomerPayment(
                $request,
                ['captured_amount' => $amount, 'source' => 'manual_override', 'admin_notes' => $data['reason']]
            );
            return ['success' => true, 'message' => 'Force capture executed', 'result' => $result];
        }
        return ['success' => false, 'message' => 'Entity type not supported for force capture'];
    }

    private function forceRefund(array $data): array
    {
        $entityType = $data['entity_type'];
        if ($entityType === 'order_anywhere' || $entityType === 'order') {
            $request = OrderAnywhereRequest::findOrFail($data['entity_id']);
            $amount = $data['amount'] ?? $request->captured_amount;
            $result = $this->paymentService->refundCustomerPayment(
                $request,
                ['refund_amount' => $amount, 'reason' => $data['reason'], 'source' => 'manual_override']
            );
            return ['success' => true, 'message' => 'Force refund executed', 'result' => $result];
        }
        return ['success' => false, 'message' => 'Entity type not supported for force refund'];
    }

    private function forceVoid(array $data): array
    {
        // Void authorization if not yet captured
        return ['success' => true, 'message' => 'Void not yet implemented'];
    }

    private function forceReleaseSplits(array $data): array
    {
        if ($data['entity_type'] === 'order_anywhere') {
            $request = OrderAnywhereRequest::findOrFail($data['entity_id']);
            $this->paymentService->settleSplits($request);
            return ['success' => true, 'message' => 'Splits force-released'];
        }
        return ['success' => false, 'message' => 'Entity type not supported for split release'];
    }
}