<?php

namespace App\Services;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Services\Payments\CardIssuingProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAnywhereCardService
{
    private CardIssuingGatewayInterface $issuing;
    private CardIssuingProviderManager $manager;

    public function __construct(?CardIssuingProviderManager $manager = null)
    {
        $this->manager = $manager ?? app(CardIssuingProviderManager::class);
        $this->issuing = $this->manager->activeProvider();
    }

    public function createCardRequest(OrderAnywhereRequest $request, array $data = []): UrbanGoodzOrderAnywhereCardRequest
    {
        if (! $this->manager->isAvailable()) {
            abort(403, 'Card issuing is currently disabled.');
        }

        if (OrderAnywhereRequest::isPaymentDisabled()) {
            abort(403, 'Payments are currently disabled.');
        }

        if (! in_array($request->status, ['approved', 'shopping', 'picked_up', 'out_for_delivery'], true)) {
            abort(422, 'Order must be approved or in-progress to request a driver card.');
        }

        if (! $request->assigned_delivery_man_id) {
            abort(422, 'A driver must be assigned before requesting a card.');
        }

        if ($request->payment_status !== 'captured' && $request->payment_status !== 'authorized') {
            abort(422, 'Order must be paid or authorized before requesting a driver card.');
        }

        $existing = UrbanGoodzOrderAnywhereCardRequest::activeForRequest($request->id);
        if ($existing) {
            abort(409, 'An active card already exists for this order.');
        }

        $spendingLimit = (float) ($data['spending_limit'] ?? $request->final_amount ?? $request->quote_amount ?? 0);
        $maxAmount = $this->manager->maxCardAmount();

        if (OrderAnywhereRequest::isLiveMode() && $spendingLimit > $maxAmount) {
            Log::critical('DRIVER CARD BLOCKED: amount exceeds cap', [
                'amount' => $spendingLimit,
                'max' => $maxAmount,
                'request_id' => $request->id,
            ]);
            abort(403, "Card spending limit of \${$spendingLimit} exceeds maximum allowed of \${$maxAmount}.");
        }

        $bufferPercent = $this->manager->bufferPercent();
        $bufferAmount = round($spendingLimit * ($bufferPercent / 100), 2);

        $expiryMinutes = $this->manager->defaultExpiryMinutes();

        return DB::transaction(function () use ($request, $data, $spendingLimit, $bufferAmount, $expiryMinutes) {
            $cardRequest = UrbanGoodzOrderAnywhereCardRequest::create([
                'order_anywhere_request_id' => $request->id,
                'delivery_man_id' => $request->assigned_delivery_man_id,
                'provider' => $this->issuing->providerName(),
                'card_status' => 'requested',
                'card_type' => $data['card_type'] ?? 'virtual',
                'spending_limit' => $spendingLimit,
                'buffer_amount' => $bufferAmount,
                'currency' => config('urban_goodz_payments.currency', 'USD'),
                'single_use' => $data['single_use'] ?? true,
                'usable_from' => $data['usable_from'] ?? now(),
                'expires_at' => now()->addMinutes($data['expiry_minutes'] ?? $expiryMinutes),
                'allowed_merchant' => $data['allowed_merchant'] ?? $request->store_vendor_name,
                'allowed_mccs' => $data['allowed_mccs'] ?? null,
                'created_by' => auth('admin')->id() ?? null,
            ]);

            $result = $this->issuing->createVirtualCard([
                'cardholder_id' => null,
                'spending_limit' => $spendingLimit,
                'currency' => $cardRequest->currency,
                'single_use' => $cardRequest->single_use,
                'metadata' => [
                    'order_anywhere_request_id' => $request->id,
                    'request_number' => $request->request_number,
                ],
            ]);

            $cardRequest->update([
                'provider_card_id' => $result['card_id'] ?? null,
                'card_status' => $result['status'] ?? 'provider_pending',
                'last4' => $result['last4'] ?? null,
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'creation_result' => $result,
                ]),
            ]);

            if (isset($result['status']) && $result['status'] !== 'provider_pending') {
                $cardRequest->update([
                    'issued_at' => now(),
                ]);
            }

            $request->logActivity(
                'driver_card_requested',
                "Driver card requested: \${$spendingLimit} spending limit",
                [],
                [
                    'card_request_id' => $cardRequest->id,
                    'spending_limit' => $spendingLimit,
                    'provider' => $this->issuing->providerName(),
                    'card_status' => $cardRequest->fresh()->card_status,
                ]
            );

            Log::channel('daily')->info('DRIVER CARD REQUESTED', [
                'card_request_id' => $cardRequest->id,
                'order_anywhere_request_id' => $request->id,
                'driver_id' => $request->assigned_delivery_man_id,
                'spending_limit' => $spendingLimit,
                'provider' => $this->issuing->providerName(),
                'card_status' => $cardRequest->fresh()->card_status,
                'admin_id' => auth('admin')->id(),
            ]);

            return $cardRequest->fresh();
        });
    }

    public function freezeCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest): UrbanGoodzOrderAnywhereCardRequest
    {
        if (in_array($cardRequest->card_status, ['frozen', 'cancelled', 'used', 'expired', 'reconciled'], true)) {
            abort(422, "Cannot freeze card in [{$cardRequest->card_status}] status.");
        }

        return DB::transaction(function () use ($cardRequest) {
            $result = $this->issuing->freezeCard($cardRequest->provider_card_id ?? '');

            $cardRequest->update([
                'card_status' => 'frozen',
                'frozen_at' => now(),
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'freeze_result' => $result,
                ]),
            ]);

            $cardRequest->orderAnywhereRequest()->first()?->logActivity(
                'driver_card_frozen',
                'Driver card frozen',
                ['card_status' => 'active'],
                ['card_status' => 'frozen'],
                ['card_request_id' => $cardRequest->id]
            );

            return $cardRequest->fresh();
        });
    }

    public function cancelCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest): UrbanGoodzOrderAnywhereCardRequest
    {
        if (in_array($cardRequest->card_status, ['cancelled', 'used', 'reconciled'], true)) {
            abort(422, "Cannot cancel card in [{$cardRequest->card_status}] status.");
        }

        return DB::transaction(function () use ($cardRequest) {
            $result = $this->issuing->closeCard($cardRequest->provider_card_id ?? '');

            $cardRequest->update([
                'card_status' => 'cancelled',
                'cancelled_at' => now(),
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'cancel_result' => $result,
                ]),
            ]);

            $cardRequest->orderAnywhereRequest()->first()?->logActivity(
                'driver_card_cancelled',
                'Driver card cancelled',
                ['card_status' => $cardRequest->card_status],
                ['card_status' => 'cancelled'],
                ['card_request_id' => $cardRequest->id]
            );

            return $cardRequest->fresh();
        });
    }

    public function reconcileCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest, array $data): UrbanGoodzOrderAnywhereCardRequest
    {
        if ($cardRequest->card_status !== 'used' && $cardRequest->card_status !== 'frozen') {
            abort(422, 'Card must be used or frozen before reconciliation.');
        }

        return DB::transaction(function () use ($cardRequest, $data) {
            $cardRequest->update([
                'card_status' => 'reconciled',
                'captured_amount' => $data['captured_amount'] ?? $cardRequest->captured_amount,
                'refunded_amount' => $data['refunded_amount'] ?? $cardRequest->refunded_amount,
                'merchant_name' => $data['merchant_name'] ?? $cardRequest->merchant_name,
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'reconciliation' => [
                        'receipt_total' => $data['receipt_total'] ?? null,
                        'captured_amount' => $data['captured_amount'] ?? null,
                        'refunded_amount' => $data['refunded_amount'] ?? null,
                        'merchant_name' => $data['merchant_name'] ?? null,
                        'reconciled_by' => auth('admin')->id(),
                        'reconciled_at' => now()->toISOString(),
                    ],
                ]),
            ]);

            $cardRequest->orderAnywhereRequest()->first()?->logActivity(
                'driver_card_reconciled',
                'Driver card reconciled',
                ['card_status' => 'used'],
                ['card_status' => 'reconciled'],
                [
                    'card_request_id' => $cardRequest->id,
                    'captured_amount' => $cardRequest->captured_amount,
                ]
            );

            return $cardRequest->fresh();
        });
    }

    public function getCardForDriver(int $driverId, int $requestId): ?UrbanGoodzOrderAnywhereCardRequest
    {
        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::findUsableForDriver($driverId, $requestId);

        if (! $cardRequest) {
            return null;
        }

        if ($cardRequest->isExpired()) {
            $cardRequest->update(['card_status' => 'expired']);
            return null;
        }

        return $cardRequest;
    }

    public function authorizeCardPurchase(UrbanGoodzOrderAnywhereCardRequest $cardRequest, array $data): UrbanGoodzOrderAnywhereCardRequest
    {
        if (! $cardRequest->isUsable()) {
            abort(422, 'Card is not usable for purchases.');
        }

        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            abort(422, 'Purchase amount must be greater than zero.');
        }

        if ($amount > $cardRequest->remainingBalance()) {
            abort(422, "Purchase amount \${$amount} exceeds card remaining balance of \${$cardRequest->remainingBalance()}.");
        }

        if ($this->manager->isLiveMode() && $amount > $this->manager->maxCardAmount()) {
            Log::critical('DRIVER CARD PURCHASE BLOCKED: amount exceeds cap', [
                'amount' => $amount,
                'max' => $this->manager->maxCardAmount(),
                'card_request_id' => $cardRequest->id,
            ]);
            abort(403, "Card purchase of \${$amount} exceeds maximum allowed.");
        }

        return DB::transaction(function () use ($cardRequest, $data, $amount) {
            $result = $this->issuing->authorizeTransaction([
                'card_id' => $cardRequest->provider_card_id,
                'amount' => $amount,
                'currency' => $cardRequest->currency,
                'merchant_name' => $data['merchant_name'] ?? null,
                'mcc' => $data['merchant_category_code'] ?? null,
            ]);

            $cardRequest->update([
                'card_status' => 'authorized',
                'authorized_amount' => $amount,
                'merchant_name' => $data['merchant_name'] ?? $cardRequest->merchant_name,
                'merchant_category_code' => $data['merchant_category_code'] ?? $cardRequest->merchant_category_code,
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'authorization_result' => $result,
                ]),
            ]);

            $cardRequest->orderAnywhereRequest()->first()?->logActivity(
                'driver_card_purchase_authorized',
                "Driver card purchase authorized: \${$amount}",
                ['card_status' => 'active'],
                ['card_status' => 'authorized', 'authorized_amount' => $amount],
                [
                    'card_request_id' => $cardRequest->id,
                    'merchant_name' => $data['merchant_name'] ?? null,
                ]
            );

            return $cardRequest->fresh();
        });
    }

    public function completeCardPurchase(UrbanGoodzOrderAnywhereCardRequest $cardRequest, float $capturedAmount): UrbanGoodzOrderAnywhereCardRequest
    {
        if ($cardRequest->card_status !== 'authorized') {
            abort(422, 'Card must be authorized before completing purchase.');
        }

        return DB::transaction(function () use ($cardRequest, $capturedAmount) {
            $newStatus = $cardRequest->single_use ? 'used' : 'active';

            $cardRequest->update([
                'card_status' => $newStatus,
                'captured_amount' => $capturedAmount,
                'used_at' => $newStatus === 'used' ? now() : null,
            ]);

            if ($newStatus === 'used' && $this->issuing->isEnabled()) {
                $this->issuing->freezeCard($cardRequest->provider_card_id ?? '');
            }

            $cardRequest->orderAnywhereRequest()->first()?->logActivity(
                'driver_card_purchase_completed',
                "Driver card purchase completed: \${$capturedAmount}",
                ['card_status' => 'authorized'],
                ['card_status' => $newStatus, 'captured_amount' => $capturedAmount],
                ['card_request_id' => $cardRequest->id]
            );

            return $cardRequest->fresh();
        });
    }

    public function getManager(): CardIssuingProviderManager
    {
        return $this->manager;
    }
}
