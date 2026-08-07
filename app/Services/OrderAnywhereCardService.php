<?php

namespace App\Services;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use App\Models\OrderAnywhereRequest;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzIssuingCardholder;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Models\UrbanGoodzOrderAnywhereCardReconciliation;
use App\Models\UrbanGoodzOrderAnywhereCardRevealSession;
use App\Services\Payments\CardIssuingProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class OrderAnywhereCardService
{
    private CardIssuingGatewayInterface $issuing;
    private CardIssuingProviderManager $manager;

    public function __construct(?CardIssuingProviderManager $manager = null)
    {
        $this->manager = $manager ?? app(CardIssuingProviderManager::class);
        $this->issuing = $this->manager->activeProvider();
    }

    /**
     * Issue a controlled virtual purchase card for the assigned shopper/driver.
     * This is SEPARATE from customer payment acceptance.
     * The card is used at external merchants to purchase items on behalf of the customer.
     */
    public function createCardRequest(
        OrderAnywhereRequest $request,
        array $data = [],
        bool $performProviderCall = false
    ): UrbanGoodzOrderAnywhereCardRequest {
        $card = $this->prepareEligibleCardRequest($request, $data);

        if ($performProviderCall && $card->card_status === 'issuance_pending') {
            return $this->issuePreparedCard($card->id);
        }

        if ($card->card_status === 'issuance_pending') {
            \App\Jobs\IssueOrderAnywherePurchaseCard::dispatch($card->id)->afterCommit();
        }

        return $card;
    }

    public function prepareEligibleCardRequest(
        OrderAnywhereRequest $request,
        array $data = []
    ): UrbanGoodzOrderAnywhereCardRequest {
        return DB::transaction(function () use ($request, $data) {
            $locked = OrderAnywhereRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertEligible($locked);

            $driver = DeliveryMan::withoutGlobalScopes()->findOrFail($locked->assigned_delivery_man_id);
            if (! $driver->available_for_order_anywhere) {
                abort(422, 'The assigned driver is not eligible for Order Anywhere work.');
            }

            $approvedBudget = (float) ($locked->merchant_purchase_amount ?: $locked->item_subtotal ?: 0);
            if ($approvedBudget <= 0) {
                abort(422, 'An approved merchant purchasing budget is required before issuing a card.');
            }
            if (isset($data['spending_limit'])
                && abs((float) $data['spending_limit'] - $approvedBudget) > 0.01) {
                abort(422, 'Card spending limit must equal the approved merchant purchasing budget.');
            }
            if ($approvedBudget > $this->manager->maxCardAmount()) {
                abort(422, 'The approved merchant purchasing budget exceeds the configured card limit.');
            }

            $provider = $this->manager->configuredProviderName();
            $quoteVersion = $this->approvedQuoteVersion($locked);
            $identity = $this->issuanceIdentity($locked, $provider, $quoteVersion);
            $existing = UrbanGoodzOrderAnywhereCardRequest::where('issuance_key', $identity)->first()
                ?? UrbanGoodzOrderAnywhereCardRequest::activeForRequest($locked->id);

            $emergencyDisabled = $this->manager->isEmergencyDisabled();
            $status = $this->manager->isAvailable()
                ? 'issuance_pending'
                : 'awaiting_provider_configuration';
            $pendingCategory = $emergencyDisabled
                ? 'emergency_disabled'
                : 'provider_not_configured';
            $pendingReason = $emergencyDisabled
                ? 'Automatic card issuance is emergency-disabled by the owner.'
                : 'Card issuance will resume automatically after provider configuration.';
            $attributes = [
                'issuance_key' => $identity,
                'customer_payment_intent_id' => $this->customerPaymentIntentId($locked),
                'order_anywhere_request_id' => $locked->id,
                'delivery_man_id' => $locked->assigned_delivery_man_id,
                'provider' => $provider,
                'provider_configuration_status' => $this->manager->configurationStatus(),
                'card_status' => $status,
                'card_type' => 'virtual',
                'spending_limit' => $approvedBudget,
                'approved_purchase_budget' => $approvedBudget,
                'approved_quote_version' => $quoteVersion,
                'market_zone_reference' => $this->marketZoneReference($locked),
                'payment_count_limit' => 1,
                'buffer_amount' => 0,
                'currency' => 'USD',
                'usable_from' => now(),
                'expires_at' => now()->addMinutes(
                    $data['expiry_minutes'] ?? $this->manager->defaultExpiryMinutes()
                ),
                'allowed_merchant' => $data['allowed_merchant'] ?? $locked->store_vendor_name,
                'allowed_mccs' => $data['allowed_mccs'] ?? null,
                'eligible_at' => $existing?->eligible_at ?? now(),
                'retry_eligible_at' => $status === 'awaiting_provider_configuration' ? now() : null,
                'failure_category' => $status === 'awaiting_provider_configuration'
                    ? $pendingCategory
                    : null,
                'failure_reason' => $status === 'awaiting_provider_configuration'
                    ? $pendingReason
                    : null,
                'created_by' => auth('admin')->id(),
            ];

            if ($existing) {
                if (in_array($existing->card_status, ['issued', 'active', 'authorized'], true)) {
                    return $existing;
                }
                $existing->update($attributes);
                $card = $existing->fresh();
            } else {
                $card = UrbanGoodzOrderAnywhereCardRequest::create($attributes);
            }

            $locked->logActivity(
                'driver_card_eligibility_evaluated',
                $status === 'awaiting_provider_configuration'
                    ? 'Purchase card is awaiting provider configuration.'
                    : 'Purchase card issuance queued.',
                [],
                [
                    'card_request_id' => $card->id,
                    'spending_limit' => $approvedBudget,
                    'provider_status' => $card->provider_configuration_status,
                ]
            );

            return $card;
        });
    }

    public function issuePreparedCard(int $cardRequestId): UrbanGoodzOrderAnywhereCardRequest
    {
        if (! $this->manager->isAvailable()) {
            $emergencyDisabled = $this->manager->isEmergencyDisabled();
            $card = UrbanGoodzOrderAnywhereCardRequest::findOrFail($cardRequestId);
            $card->update([
                'card_status' => 'awaiting_provider_configuration',
                'provider' => 'unconfigured',
                'provider_configuration_status' => $this->manager->configurationStatus(),
                'failure_category' => $emergencyDisabled
                    ? 'emergency_disabled'
                    : 'provider_not_configured',
                'failure_reason' => $emergencyDisabled
                    ? 'Automatic card issuance is emergency-disabled by the owner.'
                    : 'Card issuance will resume automatically after provider configuration.',
                'retry_eligible_at' => now(),
            ]);
            return $card->fresh();
        }

        return DB::transaction(function () use ($cardRequestId) {
            $card = UrbanGoodzOrderAnywhereCardRequest::lockForUpdate()->findOrFail($cardRequestId);
            if (in_array($card->card_status, ['issued', 'active', 'authorized'], true)) {
                return $card;
            }
            $request = OrderAnywhereRequest::lockForUpdate()->findOrFail($card->order_anywhere_request_id);
            $this->assertEligible($request);
            if ((int) $request->assigned_delivery_man_id !== (int) $card->delivery_man_id) {
                abort(409, 'The assigned driver changed before card issuance.');
            }

            $providerName = $this->manager->configuredProviderName();
            $identity = $this->issuanceIdentity(
                $request,
                $providerName,
                $this->approvedQuoteVersion($request)
            );
            $duplicate = UrbanGoodzOrderAnywhereCardRequest::where('issuance_key', $identity)
                ->where('id', '!=', $card->id)
                ->first();
            if ($duplicate) {
                return $duplicate;
            }

            $card->update([
                'issuance_key' => $identity,
                'provider' => $this->issuing->providerName(),
                'provider_configuration_status' => 'configured',
                'card_status' => 'issuance_pending',
                'issuance_attempts' => (int) $card->issuance_attempts + 1,
                'failure_category' => null,
                'failure_reason' => null,
            ]);

            $driver = DeliveryMan::withoutGlobalScopes()->findOrFail($card->delivery_man_id);
            $mapping = UrbanGoodzIssuingCardholder::where([
                'delivery_man_id' => $driver->id,
                'provider' => $this->issuing->providerName(),
                'verification_status' => 'verified',
            ])->first();
            $cardholder = $this->issuing->createCardholder([
                'existing_cardholder_id' => $mapping?->provider_cardholder_id,
                'driver_id' => $driver->id,
            ]);
            if (($cardholder['success'] ?? false) !== true || empty($cardholder['cardholder_id'])) {
                return $this->markIssuanceRetry(
                    $card,
                    $cardholder['error_code'] ?? 'cardholder_resolution_failed'
                );
            }

            $recovered = $this->issuing->findCardByIdempotencyIdentity($identity);
            $result = ($recovered['success'] ?? false) && ! empty($recovered['card_id'])
                ? $recovered
                : $this->issuing->createVirtualCard([
                    'cardholder_id' => $cardholder['cardholder_id'],
                    'spending_limit' => (float) $card->approved_purchase_budget,
                    'currency' => 'USD',
                    'allowed_mccs' => $card->allowed_mccs ?? [],
                    'allowed_card_presences' => config(
                        'urban_goodz_payments.issuing.allowed_card_presences',
                        ['not_present']
                    ),
                    'allowed_merchant_countries' => config(
                        'urban_goodz_payments.issuing.allowed_merchant_countries',
                        ['US']
                    ),
                    'payment_count_limit' => 1,
                    'idempotency_key' => "oa-card-{$identity}",
                    'metadata' => [
                        'order_anywhere_request_reference' => (string) $request->request_number,
                        'urban_goodz_card_request_id' => (string) $card->id,
                        'assigned_driver_id' => (string) $driver->id,
                        'approved_quote_version' => (string) $card->approved_quote_version,
                        'market_zone' => (string) ($card->market_zone_reference ?? 'unassigned'),
                        'environment' => (string) config('urban_goodz_payments.issuing.mode', 'sandbox'),
                        'idempotency_identity' => $identity,
                    ],
                ]);

            if (($result['success'] ?? false) !== true || empty($result['card_id'])) {
                return $this->markIssuanceRetry(
                    $card,
                    $result['error_code'] ?? 'provider_card_creation_failed'
                );
            }

            $card->update([
                'provider_card_id' => $result['card_id'],
                'provider_cardholder_id' => $cardholder['cardholder_id'],
                'card_status' => ($result['status'] ?? null) === 'active' ? 'active' : 'issued',
                'last4' => $result['last4'] ?? null,
                'issued_at' => now(),
                'activated_at' => ($result['status'] ?? null) === 'active' ? now() : null,
                'retry_eligible_at' => null,
            ]);
            $request->forceFill([
                'card_issued' => true,
                'card_request_id' => $card->id,
            ])->saveQuietly();
            $request->logActivity(
                'driver_card_issued',
                'Automatic purchase card issuance completed.',
                [],
                [
                    'card_request_id' => $card->id,
                    'provider' => $card->provider,
                    'spending_limit' => $card->spending_limit,
                ]
            );
            $driverId = (int) $card->delivery_man_id;
            $requestId = (int) $request->id;
            DB::afterCommit(function () use ($driverId, $requestId) {
                app(UrbanGoodzNotificationService::class)->notifyDriver(
                    $driverId,
                    'Purchase card available',
                    'Your Order Anywhere purchase card is ready.',
                    [
                        'type' => 'order_anywhere_purchase_card',
                        'order_anywhere_request_id' => $requestId,
                    ]
                );
            });

            return $card->fresh();
        });
    }

    public function freezeCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest): UrbanGoodzOrderAnywhereCardRequest
    {
        if ($cardRequest->card_status === 'frozen') {
            return $cardRequest;
        }
        if (in_array($cardRequest->card_status, ['cancelled', 'used', 'expired', 'reconciled'], true)) {
            abort(422, "Cannot freeze card in [{$cardRequest->card_status}] status.");
        }

        return DB::transaction(function () use ($cardRequest) {
            // A card that never reached the provider (awaiting configuration, retry
            // pending) has no remote object to freeze. Freeze it locally instead of
            // failing the owner's control.
            $result = $this->hasProviderCard($cardRequest)
                ? $this->providerFor($cardRequest)->freezeCard($cardRequest->provider_card_id)
                : ['success' => true, 'status' => 'local_only'];

            if (isset($result['success']) && $result['success'] === false) {
                throw new \RuntimeException('Card freeze failed at provider: ' . ($result['error'] ?? 'Unknown'));
            }

            $cardRequest->update([
                'card_status' => 'frozen',
                'frozen_at' => now(),
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'provider_freeze_status' => $result['status'] ?? 'unknown',
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
        if ($cardRequest->card_status === 'cancelled') {
            return $cardRequest;
        }
        if (in_array($cardRequest->card_status, ['used', 'reconciled'], true)) {
            abort(422, "Cannot cancel card in [{$cardRequest->card_status}] status.");
        }

        return DB::transaction(function () use ($cardRequest) {
            // Cards revoked before provider creation (awaiting configuration, retry
            // pending) have nothing to close remotely.
            $result = $this->hasProviderCard($cardRequest)
                ? $this->providerFor($cardRequest)->closeCard($cardRequest->provider_card_id)
                : ['success' => true, 'status' => 'local_only'];

            if (isset($result['success']) && $result['success'] === false) {
                throw new \RuntimeException('Card cancel failed at provider: ' . ($result['error'] ?? 'Unknown'));
            }

            UrbanGoodzOrderAnywhereCardRevealSession::where('card_request_id', $cardRequest->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $cardRequest->update([
                'card_status' => 'cancelled',
                'cancelled_at' => now(),
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'provider_cancel_status' => $result['status'] ?? 'unknown',
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

    /**
     * Close a card whose approved usable window lapsed before it was spent.
     */
    public function expireCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest): UrbanGoodzOrderAnywhereCardRequest
    {
        if (! in_array($cardRequest->card_status, ['issued', 'active'], true)) {
            return $cardRequest;
        }

        return DB::transaction(function () use ($cardRequest) {
            if ($this->hasProviderCard($cardRequest)) {
                $this->providerFor($cardRequest)->closeCard($cardRequest->provider_card_id);
            }
            UrbanGoodzOrderAnywhereCardRevealSession::where('card_request_id', $cardRequest->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $cardRequest->update([
                'card_status' => 'expired',
                'cancelled_at' => $cardRequest->cancelled_at ?? now(),
                'failure_category' => 'card_window_expired',
                'failure_reason' => 'The approved purchase window closed before the card was used.',
            ]);
            $cardRequest->orderAnywhereRequest?->logActivity(
                'driver_card_expired',
                'Purchase card expired before use and was closed.',
                [],
                ['card_status' => 'expired'],
                ['card_request_id' => $cardRequest->id]
            );

            return $cardRequest->fresh();
        });
    }

    public function reconcileCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest, array $data): UrbanGoodzOrderAnywhereCardRequest
    {
        if ($cardRequest->card_status === 'reconciled') {
            return $cardRequest;
        }
        if ($cardRequest->card_status !== 'used' && $cardRequest->card_status !== 'frozen') {
            abort(422, 'Card must be used or frozen before reconciliation.');
        }

        $captured = (float) ($data['captured_amount'] ?? $cardRequest->captured_amount);
        $refunded = (float) ($data['refunded_amount'] ?? $cardRequest->refunded_amount);
        $receiptTotal = (float) ($data['receipt_total'] ?? $cardRequest->receipt_total ?? 0);
        if (! $cardRequest->receipt_path || $receiptTotal <= 0) {
            abort(422, 'A driver receipt and receipt total are required before reconciliation.');
        }
        if ($captured < 0 || $captured > (float) $cardRequest->spending_limit || $refunded < 0 || $refunded > $captured) {
            abort(422, 'Reconciliation amounts are outside the approved card limits.');
        }
        if (abs($receiptTotal - ($captured - $refunded)) > 0.01) {
            abort(422, 'Receipt total must equal captured amount minus refunds.');
        }

        return DB::transaction(function () use ($cardRequest, $data, $captured, $refunded, $receiptTotal) {
            $cardRequest->update([
                'card_status' => 'reconciled',
                'captured_amount' => $captured,
                'refunded_amount' => $refunded,
                'receipt_total' => $receiptTotal,
                'merchant_name' => $data['merchant_name'] ?? $cardRequest->merchant_name,
                'reconciled_at' => now(),
                'reconciled_by' => auth('admin')->id(),
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

    public function getCardStatusForDriver(int $driverId, int $requestId): ?UrbanGoodzOrderAnywhereCardRequest
    {
        return UrbanGoodzOrderAnywhereCardRequest::where('delivery_man_id', $driverId)
            ->where('order_anywhere_request_id', $requestId)
            ->latest()
            ->first();
    }

    public function attachReceipt(
        UrbanGoodzOrderAnywhereCardRequest $cardRequest,
        UploadedFile $receipt,
        float $total,
        ?string $notes = null
    ): UrbanGoodzOrderAnywhereCardRequest {
        if (! in_array($cardRequest->card_status, ['authorized', 'used', 'frozen'], true)) {
            abort(422, 'Receipt upload is only available for an authorized or completed purchase.');
        }
        if ($total <= 0 || $total > (float) $cardRequest->spending_limit) {
            abort(422, 'Receipt total is outside the approved spending limit.');
        }

        $path = $receipt->store("private/order-anywhere-receipts/{$cardRequest->id}", 'local');
        if ($cardRequest->receipt_path) {
            Storage::disk('local')->delete($cardRequest->receipt_path);
        }
        $cardRequest->update([
            'receipt_path' => $path,
            'receipt_original_name' => $receipt->getClientOriginalName(),
            'receipt_mime' => $receipt->getMimeType(),
            'receipt_size' => $receipt->getSize(),
            'receipt_total' => $total,
            'receipt_notes' => $notes,
            'receipt_submitted_at' => now(),
        ]);
        $cardRequest->orderAnywhereRequest?->logActivity(
            'driver_card_receipt_submitted',
            'Driver submitted the purchase receipt.',
            [],
            ['receipt_total' => $total],
            ['card_request_id' => $cardRequest->id]
        );
        $this->syncReconciliation($cardRequest->fresh());

        return $cardRequest->fresh();
    }

    public function reportFailure(
        UrbanGoodzOrderAnywhereCardRequest $cardRequest,
        string $category,
        ?string $details
    ): UrbanGoodzOrderAnywhereCardRequest {
        $cardRequest->update([
            'failure_category' => $category,
            'failure_reason' => $details,
            'failure_reported_at' => now(),
        ]);
        $cardRequest->orderAnywhereRequest?->logActivity(
            'driver_card_failure_reported',
            'Driver reported a purchase-card failure.',
            [],
            ['failure_category' => $category],
            ['card_request_id' => $cardRequest->id]
        );
        return $cardRequest->fresh();
    }

    public function createRevealSession(UrbanGoodzOrderAnywhereCardRequest $cardRequest): array
    {
        if ($cardRequest->provider !== 'stripe_issuing' || ! in_array($cardRequest->card_status, ['issued', 'active', 'authorized'], true)) {
            abort(422, 'Secure card reveal is unavailable for this card.');
        }

        $plainToken = Str::random(64);
        UrbanGoodzOrderAnywhereCardRevealSession::create([
            'card_request_id' => $cardRequest->id,
            'delivery_man_id' => $cardRequest->delivery_man_id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(5),
        ]);

        return [
            'reveal_url' => url("/order-anywhere/card-reveal/{$plainToken}"),
            'expires_at' => now()->addMinutes(5)->toISOString(),
        ];
    }

    /**
     * Apply a lifecycle observer's decision synchronously. Revocation runs before
     * reevaluation so a reassignment closes the old driver's access before the new
     * driver's card is considered. Never throws into the caller's flow: a request
     * that is simply not eligible yet is a truthful outcome, not an error.
     */
    public function applyLifecyclePlan(int $requestId, ?string $reason, bool $evaluate): void
    {
        try {
            $request = OrderAnywhereRequest::find($requestId);
            if (! $request) {
                return;
            }

            if ($reason) {
                $this->revokeForRequest($request, $reason);
            }

            if ($evaluate) {
                try {
                    $this->createCardRequest($request->refresh());
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                    // Not yet eligible. The Driver API reports the truthful state.
                }
            }
        } catch (\Throwable $exception) {
            Log::critical('ORDER ANYWHERE CARD LIFECYCLE EVALUATION FAILED', [
                'request_id' => $requestId,
                'reason' => $reason,
                'exception' => class_basename($exception),
            ]);
        }
    }

    public function revokeForRequest(OrderAnywhereRequest $request, string $reason): void
    {
        $cards = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $request->id)
            ->whereNotIn('card_status', ['cancelled', 'used', 'expired', 'reconciled'])
            ->get();
        foreach ($cards as $card) {
            $card->update(['card_status' => 'revocation_pending', 'failure_category' => $reason]);
            if ($card->provider === 'unconfigured' || ! $card->provider_card_id) {
                $card->update([
                    'card_status' => 'cancelled',
                    'cancelled_at' => now(),
                    'failure_reason' => 'Issuance was revoked before provider card creation.',
                ]);
            } else {
                $this->cancelCard($card);
            }
            UrbanGoodzOrderAnywhereCardRevealSession::where('card_request_id', $card->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }
    }

    public function authorizeCardPurchase(UrbanGoodzOrderAnywhereCardRequest $cardRequest, array $data): UrbanGoodzOrderAnywhereCardRequest
    {
        $amount = (float) ($data['amount'] ?? 0);

        if ($cardRequest->card_status === 'authorized') {
            if (abs((float)$cardRequest->authorized_amount - $amount) < 0.01) {
                return $cardRequest;
            }
            abort(422, 'This card is already authorized with a different amount.');
        }

        if (! $cardRequest->isUsable()) {
            abort(422, 'Card is not usable for purchases.');
        }

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
            $result = $this->providerFor($cardRequest)->authorizeTransaction([
                'card_id' => $cardRequest->provider_card_id,
                'amount' => $amount,
                'currency' => $cardRequest->currency,
                'merchant_name' => $data['merchant_name'] ?? null,
                'mcc' => $data['merchant_category_code'] ?? null,
            ]);

            if (isset($result['success']) && $result['success'] === false) {
                throw new \RuntimeException('Card authorization failed at provider: ' . ($result['error'] ?? 'Unknown'));
            }

            $cardRequest->update([
                'card_status' => 'authorized',
                'authorized_amount' => $amount,
                'merchant_name' => $data['merchant_name'] ?? $cardRequest->merchant_name,
                'merchant_category_code' => $data['merchant_category_code'] ?? $cardRequest->merchant_category_code,
                'metadata' => array_merge($cardRequest->metadata ?? [], [
                    'provider_authorization_status' => $result['status'] ?? 'unknown',
                    'provider_transaction_reference' => $result['transaction_id'] ?? null,
                ]),
            ]);
            $this->syncReconciliation($cardRequest->fresh(), [
                'provider_status' => $result['status'] ?? null,
                'transaction_type' => 'authorization',
            ]);

            $orderRequest = $cardRequest->orderAnywhereRequest()->first();
            if ($orderRequest) {
                $orderRequest->logActivity(
                    'driver_card_purchase_authorized',
                    "Driver card purchase authorized: \${$amount}",
                    ['card_status' => 'active'],
                    ['card_status' => 'authorized', 'authorized_amount' => $amount],
                    [
                        'card_request_id' => $cardRequest->id,
                        'merchant_name' => $data['merchant_name'] ?? null,
                    ]
                );

                \App\Models\UrbanGoodzPaymentLedger::firstOrCreate(
                    ['idempotency_key' => 'driver_card_authorize:' . $cardRequest->id],
                    [
                        'ledger_number' => \App\Models\UrbanGoodzPaymentLedger::nextLedgerNumber(),
                        'feature' => 'order_anywhere',
                        'payable_type' => OrderAnywhereRequest::class,
                        'payable_id' => $cardRequest->order_anywhere_request_id,
                        'event_type' => 'driver_card_authorization',
                        'direction' => 'debit',
                        'amount' => $amount,
                        'currency' => $cardRequest->currency ?? 'USD',
                        'payment_method' => 'purchase_card',
                        'payment_status' => 'authorized',
                        'reference' => $cardRequest->provider_card_id,
                        'customer_id' => $orderRequest->customer_id ?? null,
                        'vendor_id' => $orderRequest->vendor_id ?? null,
                        'delivery_man_id' => $cardRequest->delivery_man_id,
                        'created_by_admin_id' => null,
                        'metadata' => ['merchant' => $data['merchant_name'] ?? null],
                    ]
                );
            }

            return $cardRequest->fresh();
        });
    }

    public function completeCardPurchase(UrbanGoodzOrderAnywhereCardRequest $cardRequest, float $capturedAmount): UrbanGoodzOrderAnywhereCardRequest
    {
        if (in_array($cardRequest->card_status, ['used', 'reconciled'], true)) {
            if (abs((float)$cardRequest->captured_amount - $capturedAmount) < 0.01) {
                return $cardRequest;
            }
            abort(422, 'This card purchase is already completed with a different amount.');
        }

        if ($cardRequest->card_status !== 'authorized') {
            abort(422, 'Card must be authorized before completing purchase.');
        }

        if ($capturedAmount > (float) $cardRequest->authorized_amount) {
            abort(422, "Captured amount \${$capturedAmount} cannot exceed authorized amount of \${$cardRequest->authorized_amount}.");
        }

        return DB::transaction(function () use ($cardRequest, $capturedAmount) {
            $newStatus = 'used';

            $cardRequest->update([
                'card_status' => $newStatus,
                'captured_amount' => $capturedAmount,
                'authorized_amount' => 0,
                'used_at' => $newStatus === 'used' ? now() : null,
            ]);

            if ($this->hasProviderCard($cardRequest)) {
                $provider = $this->providerFor($cardRequest);
                $closeResult = $provider->closeCard($cardRequest->provider_card_id);
                $providerState = $provider->retrieveCardStatus($cardRequest->provider_card_id);
                $cardRequest->update([
                    'cancelled_at' => now(),
                    'metadata' => array_merge($cardRequest->metadata ?? [], [
                        'provider_card_status_after_payment' => $providerState['status'] ?? $closeResult['status'] ?? 'unknown',
                    ]),
                ]);
            }

            $orderRequest = $cardRequest->orderAnywhereRequest()->first();
            if ($orderRequest) {
                $orderRequest->logActivity(
                    'driver_card_purchase_completed',
                    "Driver card purchase completed: \${$capturedAmount}",
                    ['card_status' => 'authorized'],
                    ['card_status' => $newStatus, 'captured_amount' => $capturedAmount],
                    ['card_request_id' => $cardRequest->id]
                );

                \App\Models\UrbanGoodzPaymentLedger::firstOrCreate(
                    ['idempotency_key' => 'driver_card_authorization_release:' . $cardRequest->id],
                    [
                        'ledger_number' => \App\Models\UrbanGoodzPaymentLedger::nextLedgerNumber(),
                        'feature' => 'order_anywhere',
                        'payable_type' => OrderAnywhereRequest::class,
                        'payable_id' => $cardRequest->order_anywhere_request_id,
                        'event_type' => 'driver_card_authorization_release',
                        'direction' => 'credit',
                        'amount' => (float) $cardRequest->getOriginal('authorized_amount'),
                        'currency' => $cardRequest->currency ?? 'USD',
                        'payment_method' => 'purchase_card',
                        'payment_status' => 'released',
                        'reference' => $cardRequest->provider_authorization_id,
                        'customer_id' => $orderRequest->customer_id ?? null,
                        'vendor_id' => $orderRequest->vendor_id ?? null,
                        'delivery_man_id' => $cardRequest->delivery_man_id,
                        'created_by_admin_id' => null,
                        'metadata' => ['card_request_id' => $cardRequest->id],
                    ]
                );

                \App\Models\UrbanGoodzPaymentLedger::firstOrCreate(
                    ['idempotency_key' => 'driver_card_complete:' . $cardRequest->id],
                    [
                        'ledger_number' => \App\Models\UrbanGoodzPaymentLedger::nextLedgerNumber(),
                        'feature' => 'order_anywhere',
                        'payable_type' => OrderAnywhereRequest::class,
                        'payable_id' => $cardRequest->order_anywhere_request_id,
                        'event_type' => 'driver_card_capture',
                        'direction' => 'debit',
                        'amount' => $capturedAmount,
                        'currency' => $cardRequest->currency ?? 'USD',
                        'payment_method' => 'purchase_card',
                        'payment_status' => 'completed',
                        'reference' => $cardRequest->provider_card_id,
                        'customer_id' => $orderRequest->customer_id ?? null,
                        'vendor_id' => $orderRequest->vendor_id ?? null,
                        'delivery_man_id' => $cardRequest->delivery_man_id,
                        'created_by_admin_id' => null,
                        'metadata' => [
                            'card_request_id' => $cardRequest->id,
                            'provider_transaction_id' => $cardRequest->provider_transaction_id,
                        ],
                    ]
                );
            }

            $this->syncReconciliation($cardRequest->fresh());
            return $cardRequest->fresh();
        });
    }

    public function recordProviderAuthorization(
        UrbanGoodzOrderAnywhereCardRequest $card,
        string $authorizationId,
        float $amount,
        bool $approved,
        ?string $merchantName,
        ?string $merchantCategory,
        string $providerStatus
    ): UrbanGoodzOrderAnywhereCardRequest {
        return DB::transaction(function () use (
            $card,
            $authorizationId,
            $amount,
            $approved,
            $merchantName,
            $merchantCategory,
            $providerStatus
        ) {
            $card = UrbanGoodzOrderAnywhereCardRequest::lockForUpdate()->findOrFail($card->id);
            if ($approved && ! in_array($card->card_status, ['used', 'reconciled', 'cancelled'], true)) {
                $card->update([
                    'provider_authorization_id' => $authorizationId,
                    'card_status' => 'authorized',
                    'authorized_amount' => $amount,
                    'merchant_name' => $merchantName,
                    'merchant_category_code' => $merchantCategory,
                ]);
                $this->cardLedger(
                    $card,
                    "driver_card_authorization:{$authorizationId}",
                    'driver_card_authorization',
                    'debit',
                    $amount,
                    'authorized',
                    $authorizationId
                );
            }

            $this->syncReconciliation($card->fresh(), [
                'provider_status' => $providerStatus,
            ]);

            return $card->fresh();
        });
    }

    public function recordProviderTransaction(
        UrbanGoodzOrderAnywhereCardRequest $card,
        string $transactionId,
        string $transactionType,
        float $amount,
        ?string $authorizationId
    ): UrbanGoodzOrderAnywhereCardRequest {
        return DB::transaction(function () use (
            $card,
            $transactionId,
            $transactionType,
            $amount,
            $authorizationId
        ) {
            $card = UrbanGoodzOrderAnywhereCardRequest::lockForUpdate()->findOrFail($card->id);
            $forcePost = $authorizationId === null || $authorizationId === '';

            if ($transactionType === 'refund') {
                $card->update([
                    'refunded_amount' => (float) $card->refunded_amount + $amount,
                    'provider_transaction_id' => $transactionId,
                ]);
                $this->cardLedger(
                    $card,
                    "driver_card_refund:{$transactionId}",
                    'driver_card_refund',
                    'credit',
                    $amount,
                    'refunded',
                    $transactionId
                );
            } else {
                $heldAmount = (float) $card->authorized_amount;
                $card->update([
                    'provider_authorization_id' => $authorizationId ?: $card->provider_authorization_id,
                    'provider_transaction_id' => $transactionId,
                    'captured_amount' => (float) $card->captured_amount + $amount,
                    'authorized_amount' => 0,
                    'card_status' => 'used',
                    'used_at' => now(),
                    'cancelled_at' => now(),
                ]);
                if ($heldAmount > 0) {
                    $this->cardLedger(
                        $card,
                        "driver_card_authorization_release:{$authorizationId}",
                        'driver_card_authorization_release',
                        'credit',
                        $heldAmount,
                        'released',
                        $authorizationId
                    );
                }
                $this->cardLedger(
                    $card,
                    "driver_card_capture:{$transactionId}",
                    'driver_card_capture',
                    'debit',
                    $amount,
                    'completed',
                    $transactionId
                );

                if ($this->hasProviderCard($card)) {
                    $provider = $this->providerFor($card);
                    $close = $provider->closeCard($card->provider_card_id);
                    $providerState = $provider->retrieveCardStatus($card->provider_card_id);
                    $card->update([
                        'metadata' => array_merge($card->metadata ?? [], [
                            'provider_card_status_after_payment' => $providerState['status'] ?? $close['status'] ?? 'unknown',
                        ]),
                    ]);
                }
            }

            $this->syncReconciliation($card->fresh(), [
                'force_post' => $forcePost,
                'transaction_type' => $transactionType,
            ]);

            return $card->fresh();
        });
    }

    public function getManager(): CardIssuingProviderManager
    {
        return $this->manager;
    }

    /**
     * True only when the card actually exists at a real provider. Requests that are
     * awaiting provider configuration never hold a provider card id.
     */
    private function hasProviderCard(UrbanGoodzOrderAnywhereCardRequest $cardRequest): bool
    {
        return ! in_array($cardRequest->provider, [null, '', 'unconfigured', 'manual'], true)
            && ! empty($cardRequest->provider_card_id);
    }

    private function providerFor(UrbanGoodzOrderAnywhereCardRequest $cardRequest): CardIssuingGatewayInterface
    {
        return match ($cardRequest->provider) {
            'stripe_issuing' => $this->manager->resolve('stripe'),
            'staged_test_issuing' => $this->manager->resolve('staged_test'),
            default => $this->manager->resolve('manual'),
        };
    }

    private function syncReconciliation(
        UrbanGoodzOrderAnywhereCardRequest $card,
        array $context = []
    ): UrbanGoodzOrderAnywhereCardReconciliation {
        $approved = (float) ($card->approved_purchase_budget ?: $card->spending_limit);
        $captured = (float) $card->captured_amount;
        $refunded = (float) $card->refunded_amount;
        $receipt = $card->receipt_total === null ? null : (float) $card->receipt_total;
        $net = $captured - $refunded;
        $forcePost = (bool) ($context['force_post'] ?? false);
        $mismatch = null;

        if ($forcePost) {
            $mismatch = 'force_post';
        } elseif ($net > $approved + 0.01) {
            $mismatch = 'overage';
        } elseif ($receipt !== null && abs($receipt - $net) > 0.01) {
            $mismatch = 'receipt_transaction_mismatch';
        }

        $status = $mismatch
            ? 'exception'
            : ($receipt === null
                ? 'pending_receipt'
                : ($captured > 0 ? 'ready_for_review' : 'pending_transaction'));

        $reconciliation = UrbanGoodzOrderAnywhereCardReconciliation::updateOrCreate(
            ['card_request_id' => $card->id],
            [
                'order_anywhere_request_id' => $card->order_anywhere_request_id,
                'customer_payment_intent_id' => $card->customer_payment_intent_id,
                'provider_authorization_id' => $card->provider_authorization_id,
                'provider_transaction_id' => $card->provider_transaction_id,
                'approved_budget' => $approved,
                'authorized_amount' => $card->authorized_amount,
                'transaction_amount' => $captured,
                'receipt_amount' => $receipt,
                'refunded_amount' => $refunded,
                'unused_amount' => max($approved - $net, 0),
                'overage_amount' => max($net - $approved, 0),
                'partial_capture' => $captured > 0
                    && (float) ($card->authorized_amount ?: $approved) > $captured,
                'force_post' => $forcePost,
                'status' => $status,
                'mismatch_category' => $mismatch,
                'safe_metadata' => array_filter([
                    'provider_status' => $context['provider_status'] ?? null,
                    'transaction_type' => $context['transaction_type'] ?? null,
                ], static fn ($value) => $value !== null),
            ]
        );

        if ($mismatch) {
            Log::critical('ORDER ANYWHERE CARD RECONCILIATION EXCEPTION', [
                'card_request_id' => $card->id,
                'order_anywhere_request_id' => $card->order_anywhere_request_id,
                'category' => $mismatch,
            ]);
            $card->orderAnywhereRequest?->logActivity(
                'driver_card_reconciliation_exception',
                'Purchase-card reconciliation requires admin review.',
                [],
                ['failure_category' => $mismatch],
                ['card_request_id' => $card->id]
            );
        }

        return $reconciliation;
    }

    private function cardLedger(
        UrbanGoodzOrderAnywhereCardRequest $card,
        string $idempotencyKey,
        string $eventType,
        string $direction,
        float $amount,
        string $status,
        ?string $reference
    ): void {
        if ($amount <= 0) {
            return;
        }
        $order = $card->orderAnywhereRequest;
        \App\Models\UrbanGoodzPaymentLedger::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'ledger_number' => \App\Models\UrbanGoodzPaymentLedger::nextLedgerNumber(),
                'feature' => 'order_anywhere_card',
                'payable_type' => OrderAnywhereRequest::class,
                'payable_id' => $card->order_anywhere_request_id,
                'event_type' => $eventType,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $card->currency ?? 'USD',
                'payment_method' => 'purchase_card',
                'payment_status' => $status,
                'reference' => $reference,
                'customer_id' => $order?->customer_id,
                'vendor_id' => $order?->vendor_id,
                'delivery_man_id' => $card->delivery_man_id,
                'created_by_admin_id' => null,
                'metadata' => [
                    'card_request_id' => $card->id,
                    'customer_payment_intent_id' => $card->customer_payment_intent_id,
                ],
            ]
        );
    }

    private function customerPaymentIntentId(OrderAnywhereRequest $request): ?string
    {
        foreach ([
            $request->provider_payment_id,
            $request->authorization_reference,
            $request->provider_reference,
        ] as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, 'pi_')) {
                return $candidate;
            }
        }

        return null;
    }

    private function assertEligible(OrderAnywhereRequest $request): void
    {
        if (OrderAnywhereRequest::isPaymentDisabled()) {
            abort(422, 'Customer payments are disabled.');
        }
        if (! $request->isExternalMerchant()) {
            abort(422, 'Purchase cards are only available for external merchant requests.');
        }
        if (! in_array($request->payment_status, ['authorized', 'captured'], true)) {
            abort(422, 'Customer payment must be authorized or captured.');
        }
        if (! in_array($request->status, [
            'approved',
            'shopper_assigned',
            'shopper_accepted',
            'shopping',
            'purchased',
            'picked_up',
            'out_for_delivery',
        ], true)) {
            abort(422, 'The request is not in an eligible approved state.');
        }
        if (! $request->assigned_delivery_man_id) {
            abort(422, 'A driver assignment is required.');
        }
        if (in_array($request->payment_status, ['refunded', 'partially_refunded', 'disputed'], true)
            || data_get($request->metadata, 'fraud_status') === 'blocked'
            || data_get($request->metadata, 'security_review') === 'blocked'
            || ($request->authorization_expires_at && $request->authorization_expires_at->isPast())) {
            abort(422, 'The request failed payment, expiry, fraud, or security eligibility.');
        }
    }

    private function issuanceIdentity(
        OrderAnywhereRequest $request,
        string $provider,
        string $quoteVersion
    ): string {
        return hash('sha256', implode(':', [
            (string) config('urban_goodz_payments.issuing.mode', 'sandbox'),
            $provider,
            'order-anywhere',
            $request->id,
            $quoteVersion,
            $request->assigned_delivery_man_id,
            'issue-purchase-card',
        ]));
    }

    private function markIssuanceRetry(
        UrbanGoodzOrderAnywhereCardRequest $card,
        string $category
    ): UrbanGoodzOrderAnywhereCardRequest {
        $safeCategory = Str::limit(
            preg_replace('/[^a-z0-9_]+/i', '_', strtolower($category)),
            100,
            ''
        );
        $card->update([
            'card_status' => 'issuance_retry_pending',
            'failure_category' => $safeCategory ?: 'provider_failure',
            'failure_reason' => 'Issuance will retry automatically.',
            'retry_eligible_at' => now()->addMinutes(5),
        ]);

        return $card->fresh();
    }

    private function approvedQuoteVersion(OrderAnywhereRequest $request): string
    {
        return (string) (
            data_get($request->metadata, 'quote_version')
            ?? data_get($request->financial_rules_snapshot, 'rule_version')
            ?? $request->updated_at?->format('YmdHis')
            ?? 'unversioned'
        );
    }

    private function marketZoneReference(OrderAnywhereRequest $request): ?string
    {
        $value = data_get($request->metadata, 'market')
            ?? data_get($request->metadata, 'zone_id')
            ?? data_get($request->metadata, 'zone');

        return $value === null ? null : (string) $value;
    }
}
