<?php

namespace App\Services;

use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrbanGoodzPaymentService
{
    public function quoteOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $request->quote_amount = $data['quote_amount'];
            $request->final_amount = $data['final_amount'] ?? $data['quote_amount'];
            $request->payment_status = 'quoted';
            $request->status = $request->status === 'pending_review' ? 'quote_needed' : $request->status;
            $request->admin_notes = $data['admin_notes'] ?? $request->admin_notes;
            $request->save();

            $this->ledger($request, 'quote', 'credit', (float) $request->quote_amount, 'quoted', [
                'reference' => $data['quote_reference'] ?? null,
                'metadata' => ['admin_notes' => $request->admin_notes],
            ]);

            return $request->fresh();
        });
    }

    public function authorizeOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $amount = (float) ($data['authorized_amount'] ?? $request->final_amount ?? $request->quote_amount);
            $reference = $data['authorization_reference'] ?? 'manual-auth-' . Str::uuid();

            $request->authorized_amount = $amount;
            $request->payment_method = $data['payment_method'] ?? $request->payment_method ?? 'manual';
            $request->authorization_reference = $reference;
            $request->payment_status = 'authorized';
            $request->payment_authorized_at = now();
            $request->save();

            $this->ledger($request, 'authorization', 'credit', $amount, 'authorized', [
                'reference' => $reference,
                'payment_method' => $request->payment_method,
                'metadata' => ['source' => $data['source'] ?? 'manual'],
            ]);

            return $request->fresh();
        });
    }

    public function captureOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $amount = (float) ($data['captured_amount'] ?? $request->authorized_amount ?? $request->final_amount);
            $reference = $data['capture_reference'] ?? 'manual-capture-' . Str::uuid();

            $request->captured_amount = $amount;
            $request->final_amount = $data['final_amount'] ?? $request->final_amount ?? $amount;
            $request->capture_reference = $reference;
            $request->payment_method = $data['payment_method'] ?? $request->payment_method ?? 'manual';
            $request->payment_status = 'captured';
            $request->payment_captured_at = now();
            $request->save();

            $ledger = $this->ledger($request, 'capture', 'credit', $amount, 'captured', [
                'reference' => $reference,
                'payment_method' => $request->payment_method,
                'metadata' => ['source' => $data['source'] ?? 'manual_capture'],
            ]);

            $this->captureSplits($ledger, $request, $amount, $data);

            return $request->fresh();
        });
    }

    public function refundOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $amount = (float) ($data['refund_amount'] ?? $request->captured_amount ?? 0);
            $reference = $data['refund_reference'] ?? 'manual-refund-' . Str::uuid();

            $request->refunded_amount = (float) $request->refunded_amount + $amount;
            $request->refund_reference = $reference;
            $request->payment_status = 'refunded';
            $request->payment_refunded_at = now();
            $request->save();

            $ledger = $this->ledger($request, 'refund', 'debit', $amount, 'refunded', [
                'reference' => $reference,
                'payment_method' => $request->payment_method,
                'metadata' => ['reason' => $data['reason'] ?? null],
            ]);

            $this->reversalSplits($ledger, $request, $amount);

            return $request->fresh();
        });
    }

    public function storeReceipt(OrderAnywhereRequest $request, string $path): OrderAnywhereRequest
    {
        $request->receipt_path = $path;
        $request->save();

        return $request->fresh();
    }

    public function readiness(): array
    {
        return [
            'order_anywhere' => 'payment_ready',
            'fashion_fit' => 'payment_partial',
            'earn_money' => 'payment_pending',
            'logistics' => 'payment_pending',
            'load_board' => 'payment_pending',
            'medical_courier' => 'payment_pending',
            'book_anything' => 'payment_pending',
            'rentals' => 'payment_partial',
            'events' => 'payment_pending',
            'creator_commerce' => 'payment_pending',
            'community_marketplace' => 'no_payment_needed',
            'discovery' => 'no_payment_needed',
            'ask_urban_goodz' => 'payment_pending',
            'urban_goodz_plus' => 'payment_pending',
            'spotlight' => 'payment_pending',
        ];
    }

    private function ledger(OrderAnywhereRequest $request, string $event, string $direction, float $amount, string $status, array $options = []): UrbanGoodzPaymentLedger
    {
        $reference = $options['reference'] ?? null;
        $key = implode(':', ['order_anywhere', $request->id, $event, $reference ?: number_format($amount, 2, '.', '')]);

        return UrbanGoodzPaymentLedger::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
                'feature' => 'order_anywhere',
                'payable_type' => OrderAnywhereRequest::class,
                'payable_id' => $request->id,
                'event_type' => $event,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => 'USD',
                'payment_method' => $options['payment_method'] ?? $request->payment_method,
                'payment_status' => $status,
                'reference' => $reference,
                'customer_id' => $request->customer_id,
                'vendor_id' => $request->vendor_id,
                'delivery_man_id' => $request->assigned_delivery_man_id,
                'created_by_admin_id' => auth('admin')->id(),
                'metadata' => $options['metadata'] ?? [],
            ]
        );
    }

    private function captureSplits(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request, float $amount, array $data): void
    {
        $platformFee = (float) ($data['platform_fee'] ?? round($amount * 0.10, 2));
        $driverAmount = (float) ($data['driver_amount'] ?? 0);
        $vendorAmount = (float) ($data['vendor_amount'] ?? max($amount - $platformFee - $driverAmount, 0));

        $this->split($ledger, $request, 'platform', null, 'platform_fee', $platformFee);
        $this->split($ledger, $request, 'vendor', $request->vendor_id, 'vendor_earning', $vendorAmount);

        if ($request->assigned_delivery_man_id && $driverAmount > 0) {
            $this->split($ledger, $request, 'driver', $request->assigned_delivery_man_id, 'driver_earning', $driverAmount);
        }
    }

    private function reversalSplits(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request, float $amount): void
    {
        $this->split($ledger, $request, 'customer', $request->customer_id, 'refund', $amount, 'reversed');
    }

    private function split(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request, string $recipientType, ?int $recipientId, string $splitType, float $amount, string $status = 'pending'): void
    {
        if ($amount <= 0) {
            return;
        }

        UrbanGoodzPaymentSplit::firstOrCreate(
            ['idempotency_key' => implode(':', [$ledger->id, $recipientType, $recipientId ?: 'platform', $splitType])],
            [
                'ledger_id' => $ledger->id,
                'feature' => 'order_anywhere',
                'payable_type' => OrderAnywhereRequest::class,
                'payable_id' => $request->id,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'split_type' => $splitType,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => $status,
                'metadata' => [],
            ]
        );
    }
}
