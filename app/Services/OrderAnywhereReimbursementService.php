<?php

namespace App\Services;

use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzOrderAnywhereReimbursement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Driver pays, platform reimburses" purchasing for Order Anywhere.
 *
 * Used instead of a purchase card (URBAN_GOODZ_PURCHASE_METHOD=reimburse). The driver buys the
 * goods with their own money, submits the receipt total, and once an admin approves it the
 * amount is added to the driver's earnings so it leaves with their normal payout.
 *
 * Safety rules: the claim is capped at the customer-funded purchase authorisation plus a
 * buffer; only the assigned driver can claim; one live claim per order; approval is atomic and
 * idempotent so a double click can never pay twice.
 */
class OrderAnywhereReimbursementService
{
    public static function enabled(): bool
    {
        return config('urban_goodz_payments.issuing.purchase_method', 'card') === 'reimburse';
    }

    public function cap(OrderAnywhereRequest $request): array
    {
        $authorized = app(UrbanGoodzPaymentService::class)->substitutionAdjustedPurchaseAuthorization($request);
        $buffer = max(0, (float) config('urban_goodz_payments.issuing.driver_card_buffer_percent', 10));

        return [$authorized, round($authorized * (1 + $buffer / 100), 2)];
    }

    public function submit(
        OrderAnywhereRequest $request,
        int $driverId,
        float $total,
        ?UploadedFile $receipt,
        ?string $notes = null
    ): UrbanGoodzOrderAnywhereReimbursement {
        if ((int) $request->assigned_delivery_man_id !== $driverId) {
            throw new InvalidArgumentException('You are not the assigned driver for this order.');
        }
        if (in_array($request->status, ['cancelled', 'rejected', 'completed', 'delivered'], true)
            || ! in_array($request->status, ['shopper_accepted', 'shopping', 'purchased', 'picked_up', 'out_for_delivery'], true)) {
            throw new InvalidArgumentException('This order is not at a stage where a purchase can be claimed.');
        }
        if (! $receipt) {
            throw new InvalidArgumentException('A receipt photo or PDF is required.');
        }
        [$authorized, $cap] = $this->cap($request);
        if ($authorized <= 0) {
            throw new InvalidArgumentException('This order has no approved purchase budget.');
        }
        if ($total <= 0 || $total > $cap + 0.00001) {
            throw new InvalidArgumentException(
                'The receipt total is above the approved purchase limit of $' . number_format($cap, 2)
                . '. Contact support before buying more.'
            );
        }

        return DB::transaction(function () use ($request, $driverId, $total, $receipt, $notes, $authorized, $cap) {
            OrderAnywhereRequest::whereKey($request->id)->lockForUpdate()->first();

            $live = UrbanGoodzOrderAnywhereReimbursement::where('order_anywhere_request_id', $request->id)
                ->whereIn('status', ['pending', 'approved'])->lockForUpdate()->first();
            if ($live) {
                throw new InvalidArgumentException('A reimbursement claim already exists for this order.');
            }

            $path = $receipt->store("private/order-anywhere-reimbursements/{$request->id}", 'local');
            $claim = UrbanGoodzOrderAnywhereReimbursement::create([
                'order_anywhere_request_id' => $request->id,
                'delivery_man_id' => $driverId,
                'claimed_total' => round($total, 2),
                'authorized_total' => $authorized,
                'cap_total' => $cap,
                'status' => 'pending',
                'receipt_path' => $path,
                'receipt_mime' => $receipt->getMimeType(),
                'driver_notes' => $notes,
            ]);

            app(UrbanGoodzPaymentService::class)->reconcileReceipt($request->fresh(), [
                'receipt_amount' => $total,
                'receipt_notes' => $notes,
            ]);
            $request->logActivity('driver_reimbursement_claimed', 'Driver submitted a purchase receipt for reimbursement.', [], [
                'claimed_total' => round($total, 2),
            ]);

            return $claim;
        });
    }

    public function approve(int $claimId, int $adminId, ?float $approvedTotal = null, ?string $notes = null): UrbanGoodzOrderAnywhereReimbursement
    {
        return DB::transaction(function () use ($claimId, $adminId, $approvedTotal, $notes) {
            $claim = UrbanGoodzOrderAnywhereReimbursement::lockForUpdate()->findOrFail($claimId);
            if ($claim->status === 'approved') {
                return $claim; // idempotent: never pays twice
            }
            if ($claim->status !== 'pending') {
                throw new InvalidArgumentException('Only a pending claim can be approved.');
            }
            $amount = round($approvedTotal ?? (float) $claim->claimed_total, 2);
            if ($amount <= 0 || $amount > (float) $claim->claimed_total + 0.00001) {
                throw new InvalidArgumentException('Approved amount must be above zero and no more than the claimed total.');
            }

            $earning = UrbanGoodzDriverEarning::firstOrCreate(
                ['idempotency_key' => 'oa-reimbursement-' . $claim->id],
                [
                    'delivery_man_id' => $claim->delivery_man_id,
                    'earning_type' => 'order_anywhere_reimbursement',
                    'amount' => $amount,
                    'currency' => 'USD',
                    'status' => 'pending',
                    'description' => 'Purchase reimbursement for Order Anywhere #' . $claim->order_anywhere_request_id,
                    'approved_by' => $adminId,
                    'approved_at' => now(),
                ]
            );

            $claim->update([
                'status' => 'approved',
                'approved_total' => $amount,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'driver_earning_id' => $earning->id,
            ]);
            $claim->orderAnywhereRequest?->logActivity('driver_reimbursement_approved', 'Reimbursement approved.', [], [
                'approved_total' => $amount,
            ]);

            return $claim->fresh();
        });
    }

    public function reject(int $claimId, int $adminId, ?string $notes = null): UrbanGoodzOrderAnywhereReimbursement
    {
        return DB::transaction(function () use ($claimId, $adminId, $notes) {
            $claim = UrbanGoodzOrderAnywhereReimbursement::lockForUpdate()->findOrFail($claimId);
            if ($claim->status !== 'pending') {
                throw new InvalidArgumentException('Only a pending claim can be rejected.');
            }
            $claim->update([
                'status' => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
            $claim->orderAnywhereRequest?->logActivity('driver_reimbursement_rejected', 'Reimbursement rejected.', [], []);

            return $claim->fresh();
        });
    }

    public function hasLiveClaim(int $requestId): bool
    {
        return UrbanGoodzOrderAnywhereReimbursement::where('order_anywhere_request_id', $requestId)
            ->whereIn('status', ['pending', 'approved'])->exists();
    }
}
