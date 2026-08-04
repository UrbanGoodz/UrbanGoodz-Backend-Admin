<?php

namespace App\Services;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\UrbanGoodzWebhookEvent;
use App\Services\Payments\PaymentFinalizationConflict;
use App\Services\Payments\PaymentFinalizationResult;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UrbanGoodzPaymentService
{
    private PaymentGatewayInterface $gateway;
    private PaymentProviderManager $providerManager;

    public function __construct(?PaymentProviderManager $providerManager = null)
    {
        $this->providerManager = $providerManager ?? app(PaymentProviderManager::class);
        $this->gateway = $this->providerManager->activeProvider();
    }

    public function getProviderManager(): PaymentProviderManager
    {
        return $this->providerManager;
    }

    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    // ─── Live Mode Guards ─────────────────────────────────────────────────

    private function assertPaymentsEnabled(): void
    {
        if (OrderAnywhereRequest::isPaymentDisabled()
            || $this->providerManager->isDisabled()
            || ! $this->gateway->isEnabled()) {
            abort(403, 'Payments are currently disabled.');
        }
    }

    private function assertLiveAmountWithinCap(float $amount, ?int $customerId = null): void
    {
        if (! OrderAnywhereRequest::isLiveMode()) {
            return;
        }

        $maxAmount = OrderAnywhereRequest::liveMaxAmount();

        if ($amount > $maxAmount) {
            Log::critical('LIVE PAYMENT BLOCKED: amount exceeds cap', [
                'amount' => $amount,
                'max' => $maxAmount,
                'customer_id' => $customerId,
            ]);
            abort(403, "Live payment of \${$amount} exceeds maximum allowed cap of \${$maxAmount}.");
        }

        if (auth('admin')->check() && ! OrderAnywhereRequest::isLiveAdminAllowed()) {
            abort(403, 'You are not authorized to initiate live payments.');
        }

        if (! OrderAnywhereRequest::isLiveCustomerAllowed($customerId)) {
            abort(403, 'This customer is not authorized for live payments.');
        }
    }

    private function logLivePaymentAttempt(string $action, float $amount, int $requestId, string $reference): void
    {
        Log::channel('daily')->info('LIVE PAYMENT ATTEMPT', [
            'action' => $action,
            'amount' => $amount,
            'request_id' => $requestId,
            'reference' => $reference,
            'mode' => OrderAnywhereRequest::paymentMode(),
            'provider' => $this->gateway->providerName(),
            'admin_id' => auth('admin')->id(),
            'ip' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 3: SORCING
    // ═══════════════════════════════════════════════════════════════════════

    public function beginSourcing(OrderAnywhereRequest $request): OrderAnywhereRequest
    {
        if ($request->status !== 'pending_review') {
            throw new \InvalidArgumentException('Request must be in pending_review to begin sourcing.');
        }

        return DB::transaction(function () use ($request) {
            $request->update([
                'status' => 'sourcing',
                'sourcing_status' => 'in_progress',
                'payment_status' => 'awaiting_quote',
            ]);

            $this->ledger($request, 'sourcing_started', 'neutral', 0, 'awaiting_quote');
            $request->logPaymentEvent('sourcing_started', 0, null);

            return $request->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 4: QUOTE GENERATION
    // ═══════════════════════════════════════════════════════════════════════

    public function quoteOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $quoteAmount = (float) ($data['quote_amount'] ?? 0);
            $finalAmount = (float) ($data['final_amount'] ?? $quoteAmount);
            $fulfillmentType = $data['fulfillment_type'] ?? $request->fulfillment_type ?? OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT;

            $request->update([
                'quote_amount' => $quoteAmount,
                'final_amount' => $finalAmount,
                'fulfillment_type' => $fulfillmentType,
                'payment_status' => 'quoted',
                'status' => 'quote_ready',
                'admin_notes' => $data['admin_notes'] ?? $request->admin_notes,
                'item_subtotal' => $data['item_subtotal'] ?? $request->item_subtotal,
                'service_fee' => $data['service_fee'] ?? $request->service_fee,
                'delivery_fee' => $data['delivery_fee'] ?? $request->delivery_fee,
                'tax' => $data['tax'] ?? $request->tax,
                'tip' => $data['tip'] ?? $request->tip,
            ]);

            // Calculate estimated splits
            $this->calculateSplits($request, $finalAmount, $data);

            $this->ledger($request, 'quote', 'credit', $finalAmount, 'quoted', [
                'reference' => $data['quote_reference'] ?? null,
                'fulfillment_type' => $fulfillmentType,
                'item_subtotal' => $request->item_subtotal,
                'service_fee' => $request->service_fee,
                'delivery_fee' => $request->delivery_fee,
                'tax' => $request->tax,
            ]);

            $request->logPaymentEvent('quote', $finalAmount, $data['quote_reference'] ?? null);

            return $request->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 5: CUSTOMER PAYMENT ACCEPTANCE
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a payment session for customer to authorize funds.
     * This is SEPARATE from any card issuing for the shopper/driver.
     */
    public function createPaymentSession(OrderAnywhereRequest $request, array $data = []): array
    {
        $this->assertPaymentsEnabled();

        if (! in_array($request->payment_status, ['quoted', 'awaiting_payment', 'payment_session_created'], true)) {
            throw new \InvalidArgumentException('Cannot create payment session in status: ' . $request->payment_status);
        }

        $amount = (float) ($data['amount'] ?? $request->final_amount ?? $request->quote_amount ?? 0);
        $currency = $data['currency'] ?? config('urban_goodz_payments.currency', 'USD');
        $reference = $request->request_number;
        $description = $data['description'] ?? "Order Anywhere - {$request->request_number}";

        $this->assertLiveAmountWithinCap($amount, $request->customer_id);

        if (OrderAnywhereRequest::isLiveMode()) {
            $this->logLivePaymentAttempt('create_payment_session', $amount, $request->id, $reference);
        }

        $idempotencyKey = "payment_session:{$this->gateway->providerName()}:{$request->id}:" . md5($amount . $currency . $reference);

        // Idempotency check
        $existing = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return [
                'success' => true,
                'provider' => $this->gateway->providerName(),
                'provider_reference' => $existing->reference,
                'merchant_reference' => $reference,
                'payment_link_id' => $existing->metadata['payment_link_id'] ?? null,
                'payment_url' => $existing->metadata['payment_url'] ?? null,
                'status' => 'active',
                'amount' => $amount,
                'currency' => $currency,
                'idempotent_replay' => true,
            ];
        }

        $returnUrl = match ($this->gateway->providerName()) {
            'adyen' => config('urban_goodz_payments.adyen.return_url'),
            'stripe' => config('urban_goodz_payments.stripe.success_url'),
            default => null,
        };

        $result = $this->gateway->createPaymentLink($request, $amount, $currency, $reference, $returnUrl, $description);

        if (! $result['success']) {
            Log::critical('PAYMENT SESSION FAILED: provider returned failure', [
                'provider' => $this->gateway->providerName(),
                'request_id' => $request->id,
                'reference' => $reference,
                'result' => $result,
            ]);
            abort(500, 'Payment session creation failed. Please try again.');
        }

        $request->update([
            'payment_provider' => $this->gateway->providerName(),
            'provider_reference' => $result['provider_reference'],
            'merchant_reference' => $reference,
            'payment_link_id' => $result['payment_link_id'],
            'payment_url' => $result['payment_url'],
            'psp_reference' => $result['provider_reference'],
            'payment_status' => 'payment_session_created',
        ]);

        $this->ledger($request, 'payment_session_created', 'credit', $amount, 'payment_session_created', [
            'reference' => $result['provider_reference'],
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'payment_link_id' => $result['payment_link_id'],
                'payment_url' => $result['payment_url'],
                'provider' => $this->gateway->providerName(),
                'staged_test' => $result['staged_test'] ?? false,
            ],
        ]);

        $request->logPaymentEvent('payment_session_created', $amount, $result['provider_reference'], [
            'payment_url' => $result['payment_url'],
            'provider' => $this->gateway->providerName(),
        ]);

        return $result;
    }

    /**
     * Authorize customer payment via provider.
     * Called by webhook or manual admin action.
     */
    public function authorizeCustomerPayment(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        $this->assertPaymentsEnabled();

        if (! in_array($request->payment_status, ['quoted', 'payment_session_created', 'awaiting_payment'], true)) {
            throw new \InvalidArgumentException('Cannot authorize payment in status: ' . $request->payment_status);
        }

        $amount = (float) ($data['authorized_amount'] ?? $request->final_amount ?? $request->quote_amount);

        if (($data['source'] ?? null) !== 'webhook') {
            $this->assertLiveAmountWithinCap($amount, $request->customer_id);
        }

        return DB::transaction(function () use ($request, $data, $amount) {
            $reference = $data['authorization_reference'] ?? $data['psp_reference'] ?? 'manual-auth-' . Str::uuid();

            $idempotencyKey = "customer_authorize:{$this->gateway->providerName()}:{$request->id}:" . md5($amount . $reference);

            // Idempotency check
            $existing = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $request->fresh();
            }

            // Call gateway if enabled and not webhook
            if ($this->gateway->isEnabled() && ($data['source'] ?? null) !== 'webhook') {
                $gatewayResult = $this->gateway->authorize($request, $amount, config('urban_goodz_payments.currency', 'USD'), $reference, $data['psp_reference'] ?? null);

                if (! $gatewayResult['success']) {
                    Log::critical('CUSTOMER AUTHORIZATION FAILED', [
                        'provider' => $this->gateway->providerName(),
                        'request_id' => $request->id,
                    ]);
                    abort(500, 'Payment authorization failed. Please try again.');
                }

                $request->psp_reference = $gatewayResult['provider_reference'] ?? null;
                $request->authorization_reference = $gatewayResult['provider_reference'] ?? $reference;
            } elseif (($data['psp_reference'] ?? null) && ($data['source'] ?? null) === 'webhook') {
                $request->psp_reference = $data['psp_reference'];
                $request->authorization_reference = $data['psp_reference'];
            } else {
                $request->authorization_reference = $reference;
            }

            $request->update([
                'authorized_amount' => $amount,
                'payment_method' => $data['payment_method'] ?? $request->payment_method ?? 'card',
                'payment_status' => 'authorized',
                'payment_authorized_at' => now(),
                'authorization_expires_at' => now()->addHours(72),
                'status' => 'authorized',
            ]);

            // Reserve pending splits (do NOT settle yet)
            $this->reservePendingSplits($request);

            $this->ledger($request, 'customer_authorization', 'credit', $amount, 'authorized', [
                'reference' => $request->authorization_reference,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $request->payment_method,
                'metadata' => [
                    'source' => $data['source'] ?? 'manual',
                    'provider' => $this->gateway->providerName(),
                ],
            ]);

            $request->logPaymentEvent('customer_authorized', $amount, $request->authorization_reference);

            return $request->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 7: ASSIGNMENT (approved → fulfillment path)
    // ═══════════════════════════════════════════════════════════════════════

    public function approveRequest(OrderAnywhereRequest $request): OrderAnywhereRequest
    {
        if ($request->payment_status !== 'authorized') {
            throw new \InvalidArgumentException('Cannot approve: customer payment not authorized.');
        }

        return $request->transitionTo('approved');
    }

    public function assignShopper(OrderAnywhereRequest $request, int $shopperId): OrderAnywhereRequest
    {
        if (! $request->isExternalMerchant()) {
            throw new \InvalidArgumentException('Shopper assignment is only for external merchant orders.');
        }

        if ($request->status !== 'approved') {
            throw new \InvalidArgumentException('Request must be approved before assigning shopper.');
        }

        $request->update([
            'shopper_id' => $shopperId,
            'shopper_status' => 'assigned',
            'assigned_delivery_man_id' => $shopperId,
            'assigned_at' => now(),
        ]);

        return $request->transitionTo('shopper_assigned');
    }

    public function assignVendorToRequest(OrderAnywhereRequest $request, int $vendorId): OrderAnywhereRequest
    {
        if (! $request->isParticipatingVendor()) {
            throw new \InvalidArgumentException('Vendor assignment is only for participating vendor orders.');
        }

        if ($request->status !== 'approved') {
            throw new \InvalidArgumentException('Request must be approved before assigning vendor.');
        }

        $request->update([
            'vendor_id' => $vendorId,
            'vendor_status' => 'assigned',
        ]);

        return $request->transitionTo('vendor_assigned');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 8: SPLIT LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Calculate deterministic splits at quote time.
     *
     * Precedence for dispatcher commission rate:
     *   1. Per-order approved override ($data['dispatcher_commission_rate'])
     *   2. Business client dispatch_default_commission_rate
     *   3. Platform default config('urban_goodz_payments.default_dispatcher_commission_rate')
     *   4. Zero (when no dispatcher is attached)
     *
     * Dispatcher commission base: delivery_fee + service_fee
     *   (the fees the customer pays for delivery/dispatch services)
     *
     * Participating vendor revenue: platform_fee + service_fee
     * External merchant revenue: totalAmount - merchantPurchase - driverPayout - dispatcherCommission - processingReserve
     */
    public function calculateSplits(OrderAnywhereRequest $request, float $totalAmount, array $data = []): void
    {
        $currency = config('urban_goodz_payments.currency', 'USD');

        // ─── Platform fee ───────────────────────────────────────────────
        $platformFeeSetting = app(UrbanGoodzPaymentSettings::class)->platformFee();
        $feePercent = $platformFeeSetting['effective_percent'];
        $platformFee = (float) ($data['platform_fee'] ?? round($totalAmount * ($feePercent / 100), 2));

        // ─── Driver payout ──────────────────────────────────────────────
        $driverPolicyId = null;
        $driverPayoutModel = 'admin_override';
        if (isset($data['driver_amount'])) {
            $driverAmount = (float) $data['driver_amount'];
        } else {
            $deliveryMan = $request->assigned_delivery_man_id ? \App\Models\DeliveryMan::find($request->assigned_delivery_man_id) : null;
            $metadata = is_array($request->metadata) ? $request->metadata : [];
            $driverPricingService = resolve(\App\Services\UrbanGoodz\UrbanGoodzDriverPricingService::class);
            $payoutResult = $driverPricingService->calculatePayout('order_anywhere', [
                'zone_id' => $data['zone_id'] ?? ($metadata['zone_id'] ?? null),
                'mileage' => $data['distance_miles'] ?? ($metadata['distance_miles'] ?? 0.00),
                'duration' => $data['duration_minutes'] ?? ($metadata['duration_minutes'] ?? 0.00),
                'revenue' => $totalAmount,
                'base_amount' => $request->delivery_fee ?? 0.00,
                'vehicle_id' => $deliveryMan?->vehicle_id,
            ]);
            $driverAmount = $payoutResult['payout'];
            $driverPolicyId = $payoutResult['policy_id'] ?? null;
            $driverPayoutModel = $payoutResult['payout_model'] ?? 'policy';
        }

        // ─── Dispatcher commission (deterministic resolution) ────────────
        $dispatcherCommission = 0.0;
        $dispatcherRate = 0.0;
        $dispatcherBase = 'delivery_service_fees';
        $dispatcherRuleSource = 'none';
        $dispatcherId = null;

        $deliveryFee = (float) ($request->delivery_fee ?? 0);
        $serviceFee = (float) ($request->service_fee ?? 0);
        $commissionBase = $deliveryFee + $serviceFee;

        if ($commissionBase > 0 && $request->assigned_delivery_man_id) {
            // 1. Per-order override
            if (isset($data['dispatcher_commission_rate']) && is_numeric($data['dispatcher_commission_rate'])) {
                $dispatcherRate = (float) $data['dispatcher_commission_rate'];
                $dispatcherRuleSource = 'per_order_override';
            }
            // 2. Business client rate
            elseif ($request->business_id) {
                $businessClient = \App\Models\UrbanGoodzBusinessClient::find($request->business_id);
                if ($businessClient && $businessClient->dispatch_default_commission_rate > 0) {
                    $dispatcherRate = (float) $businessClient->dispatch_default_commission_rate;
                    $dispatcherRuleSource = 'business_client';
                }
            }
            // 3. Platform default
            if ($dispatcherRate === 0.0) {
                $dispatcherRate = (float) config('urban_goodz_payments.default_dispatcher_commission_rate', 0);
                $dispatcherRuleSource = $dispatcherRate > 0 ? 'platform_default' : 'none';
            }

            if ($dispatcherRate > 0) {
                $dispatcherCommission = round($commissionBase * ($dispatcherRate / 100), 2);
                $dispatcherId = $data['dispatcher_id'] ?? null;
            }
        }

        // ─── Processing reserve ─────────────────────────────────────────
        $processingReserve = (float) ($data['processing_reserve'] ?? 0);

        // ─── Vendor payout (participating vendor only) ──────────────────
        $vendorAmount = 0.0;
        if ($request->isParticipatingVendor()) {
            $vendorAmount = max($totalAmount - $platformFee - $serviceFee - $driverAmount - $dispatcherCommission - $processingReserve, 0);
        }

        // ─── Urban Goodz revenue ────────────────────────────────────────
        $urbanGoodzRevenue = 0.0;
        if ($request->isParticipatingVendor()) {
            $urbanGoodzRevenue = $platformFee + $serviceFee;
        } else {
            $merchantPurchase = (float) ($data['merchant_purchase_amount'] ?? 0);
            $urbanGoodzRevenue = max($totalAmount - $merchantPurchase - $driverAmount - $dispatcherCommission - $processingReserve, 0);
        }

        // ─── Persist to order record ────────────────────────────────────
        $request->update([
            'platform_fee' => $platformFee,
            'vendor_payout_amount' => $vendorAmount,
            'driver_payout_amount' => $driverAmount,
            'dispatcher_commission' => $dispatcherCommission,
            'urban_goodz_revenue' => $urbanGoodzRevenue,
            'processing_reserve' => $processingReserve,
            'merchant_purchase_amount' => $data['merchant_purchase_amount'] ?? $request->merchant_purchase_amount,
        ]);

        // ─── Financial rule snapshot ────────────────────────────────────
        $snapshot = [
            'platform_fee_percent' => $feePercent,
            'platform_fee_amount' => $platformFee,
            'platform_fee_source' => $platformFeeSetting['source'],
            'driver_payout_formula' => $driverPayoutModel,
            'driver_pricing_policy_id' => $driverPolicyId,
            'dispatcher_commission_rate' => $dispatcherRate,
            'dispatcher_commission_base' => $dispatcherBase,
            'dispatcher_commission_amount' => $dispatcherCommission,
            'dispatcher_rule_source' => $dispatcherRuleSource,
            'dispatcher_id' => $dispatcherId,
            'vendor_amount_formula' => 'total - platformFee - serviceFee - driverPayout - dispatcherCommission - processingReserve',
            'urban_goodz_revenue_formula' => $request->isParticipatingVendor()
                ? 'platformFee + serviceFee'
                : 'total - merchantPurchase - driverPayout - dispatcherCommission - processingReserve',
            'reserve_percent' => 0,
            'fulfillment_type' => $request->fulfillment_type,
            'rule_source' => 'urban_goodz_payment_service_v2',
            'rule_version' => '2.0',
            'effective_timestamp' => now()->toISOString(),
            'currency' => $currency,
        ];
        $request->update(['financial_rules_snapshot' => $snapshot]);

        // ─── Ledger entry ───────────────────────────────────────────────
        $ledger = $this->ledger($request, 'split_calculated', 'neutral', $totalAmount, 'quoted', [
            'platform_fee' => $platformFee,
            'vendor_amount' => $vendorAmount,
            'driver_amount' => $driverAmount,
            'dispatcher_commission' => $dispatcherCommission,
            'urban_goodz_revenue' => $urbanGoodzRevenue,
            'processing_reserve' => $processingReserve,
            'financial_rules_snapshot' => $snapshot,
        ]);

        // ─── Create split records ───────────────────────────────────────
        if ($platformFee > 0) {
            $this->split($ledger, $request, 'platform', null, 'platform_fee', $platformFee, 'calculated');
        }
        $additionalPlatformRevenue = max($urbanGoodzRevenue - $platformFee, 0);
        if ($additionalPlatformRevenue > 0) {
            $this->split($ledger, $request, 'platform', null, 'service_revenue', $additionalPlatformRevenue, 'calculated');
        }
        if ($vendorAmount > 0 && $request->vendor_id) {
            $this->split($ledger, $request, 'vendor', $request->vendor_id, 'vendor_earning', $vendorAmount, 'calculated');
        }
        if ($driverAmount > 0 && $request->assigned_delivery_man_id) {
            $this->split($ledger, $request, 'driver', $request->assigned_delivery_man_id, 'driver_earning', $driverAmount, 'calculated');
        }
        if ($dispatcherCommission > 0 && $dispatcherId) {
            $this->split($ledger, $request, 'dispatcher', $dispatcherId, 'dispatcher_commission', $dispatcherCommission, 'calculated');
        }
    }

    /**
     * Reserve pending allocations at authorization time.
     * Do NOT settle yet.
     */
    public function reservePendingSplits(OrderAnywhereRequest $request): void
    {
        DB::transaction(function () use ($request) {
            // Upgrade calculated splits to reserved
            UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->where('status', 'calculated')
                ->update(['status' => 'reserved']);

            $request->logPaymentEvent('splits_reserved', (float) $request->authorized_amount, $request->authorization_reference);
        });
    }

    /**
     * Accrue earnings at delivery time.
     */
    public function accrueEarnings(OrderAnywhereRequest $request): void
    {
        DB::transaction(function () use ($request) {
            UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->where('status', 'reserved')
                ->update(['status' => 'accrued']);

            $request->logPaymentEvent('earnings_accrued', (float) $request->captured_amount ?? (float) $request->authorized_amount, null);
        });
    }

    /**
     * Finalize actual split amounts at completion.
     */
    public function finalizeSplits(OrderAnywhereRequest $request, array $actualAmounts = []): void
    {
        DB::transaction(function () use ($request, $actualAmounts) {
            // Update splits with actual amounts if provided
            if (! empty($actualAmounts['platform_fee'])) {
                UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                    ->where('payable_id', $request->id)
                    ->where('split_type', 'platform_fee')
                    ->whereIn('status', ['reserved', 'accrued'])
                    ->update(['amount' => $actualAmounts['platform_fee']]);
            }

            if (! empty($actualAmounts['vendor_amount'])) {
                UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                    ->where('payable_id', $request->id)
                    ->where('split_type', 'vendor_earning')
                    ->whereIn('status', ['reserved', 'accrued'])
                    ->update(['amount' => $actualAmounts['vendor_amount']]);
            }

            if (! empty($actualAmounts['driver_amount'])) {
                UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                    ->where('payable_id', $request->id)
                    ->where('split_type', 'driver_earning')
                    ->whereIn('status', ['reserved', 'accrued'])
                    ->update(['amount' => $actualAmounts['driver_amount']]);
            }

            UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->whereIn('status', ['reserved', 'accrued'])
                ->update(['status' => 'finalized']);

            $request->logPaymentEvent('splits_finalized', (float) $request->captured_amount ?? (float) $request->authorized_amount, null);
        });
    }

    /**
     * Settle splits once after successful capture.
     * This releases funds to wallets. Only called once.
     */
    public function settleSplits(OrderAnywhereRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $splits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->whereIn('status', ['finalized', 'accrued', 'reserved'])
                ->lockForUpdate()
                ->get();

            if (in_array($request->status, ['cancelled', 'failed'], true) || ($request->payment_status === 'refunded' && (float) $request->captured_amount <= (float) $request->refunded_amount)) {
                foreach ($splits as $split) {
                    $split->update(['status' => 'cancelled']);
                }
                return;
            }

            foreach ($splits as $split) {
                $amount = (float) $split->amount;
                if ($amount <= 0) {
                    $split->update(['status' => 'cancelled']);
                    continue;
                }

                // Check for refund reversals
                $reversedAmount = (float) UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                    ->where('payable_id', $request->id)
                    ->where('recipient_type', $split->recipient_type)
                    ->where('recipient_id', $split->recipient_id)
                    ->where('split_type', "{$split->recipient_type}_refund_reversal")
                    ->sum('amount');
                $finalAmount = max($amount - $reversedAmount, 0);

                if ($finalAmount <= 0) {
                    $split->update(['status' => 'cancelled']);
                    continue;
                }

                // Release to wallets
                if ($split->recipient_type === 'platform') {
                    $admin = \App\Models\Admin::where('role_id', 1)->first();
                    if ($admin) {
                        $adminWallet = \App\Models\AdminWallet::firstOrCreate(['admin_id' => $admin->id]);
                        $adminWallet->increment('total_commission_earning', $finalAmount);
                    }
                } elseif ($split->recipient_type === 'vendor' && $split->recipient_id) {
                    $vendorWallet = \App\Models\StoreWallet::firstOrCreate(['vendor_id' => $split->recipient_id]);
                    $vendorWallet->increment('total_earning', $finalAmount);
                } elseif ($split->recipient_type === 'driver' && $split->recipient_id) {
                    $dmWallet = \App\Models\DeliveryManWallet::firstOrCreate(['delivery_man_id' => $split->recipient_id]);
                    $dmWallet->increment('total_earning', $finalAmount);

                    \App\Models\UrbanGoodzDriverEarning::firstOrCreate(
                        [
                            'delivery_man_id' => $split->recipient_id,
                            'earning_type' => 'per_package',
                            'amount' => $finalAmount,
                            'description' => "Order Anywhere Delivery - Req #{$request->request_number}",
                        ],
                        [
                            'currency' => $split->currency ?? 'USD',
                            'status' => 'pending',
                        ]
                    );
                } elseif ($split->recipient_type === 'dispatcher' && $split->recipient_id) {
                    $dispatcherUser = \App\Models\UrbanGoodzBusinessClientUser::find($split->recipient_id);
                    if ($dispatcherUser) {
                        $dmWallet = \App\Models\DeliveryManWallet::firstOrCreate(['delivery_man_id' => $split->recipient_id]);
                        $dmWallet->increment('total_earning', $finalAmount);

                        \App\Models\UrbanGoodzDriverEarning::firstOrCreate(
                            [
                                'delivery_man_id' => $split->recipient_id,
                                'earning_type' => 'dispatcher_commission',
                                'amount' => $finalAmount,
                                'description' => "Order Anywhere Dispatcher Commission - Req #{$request->request_number}",
                            ],
                            [
                                'currency' => $split->currency ?? 'USD',
                                'status' => 'pending',
                            ]
                        );
                    }
                }

                $split->update([
                    'status' => 'released',
                    'metadata' => array_merge($split->metadata ?? [], [
                        'released_amount' => $finalAmount,
                        'reversed_amount' => $reversedAmount,
                    ]),
                ]);
            }

            $request->update(['splits_settled_at' => now()]);
            $request->logPaymentEvent('splits_settled', (float) $request->captured_amount, null);
        });
    }

    /**
     * Reverse pending splits on cancellation before capture.
     */
    public function reversePendingSplits(OrderAnywhereRequest $request): void
    {
        DB::transaction(function () use ($request) {
            UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->whereIn('status', ['calculated', 'reserved', 'accrued', 'finalized'])
                ->update(['status' => 'cancelled']);

            $request->logPaymentEvent('pending_splits_reversed', 0, null);
        });
    }

    /**
     * Reverse settled splits on refund after capture.
     */
    public function reverseSettledSplits(OrderAnywhereRequest $request, float $refundAmount): void
    {
        DB::transaction(function () use ($request, $refundAmount) {
            $remaining = $refundAmount;
            $priority = ['vendor' => 0, 'driver' => 1, 'dispatcher' => 2, 'platform' => 3];

            $originalSplits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->whereIn('split_type', ['vendor_earning', 'driver_earning', 'platform_fee', 'service_revenue', 'dispatcher_commission'])
                ->where('status', 'released')
                ->get()
                ->sortBy(fn ($split) => $priority[$split->recipient_type] ?? 99);

            foreach ($originalSplits as $originalSplit) {
                if ($remaining <= 0) {
                    break;
                }

                $alreadyReversed = (float) UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                    ->where('payable_id', $request->id)
                    ->where('recipient_type', $originalSplit->recipient_type)
                    ->where('recipient_id', $originalSplit->recipient_id)
                    ->where('split_type', "{$originalSplit->recipient_type}_refund_reversal")
                    ->where('metadata->original_split_id', $originalSplit->id)
                    ->sum('amount');
                $available = max((float) $originalSplit->amount - $alreadyReversed, 0);
                $allocation = min($available, $remaining);

                if ($allocation <= 0) {
                    continue;
                }

                // Reverse from wallets
                if ($originalSplit->recipient_type === 'vendor' && $originalSplit->recipient_id) {
                    \App\Models\StoreWallet::where('vendor_id', $originalSplit->recipient_id)
                        ->decrement('total_earning', $allocation);
                } elseif ($originalSplit->recipient_type === 'driver' && $originalSplit->recipient_id) {
                    \App\Models\DeliveryManWallet::where('delivery_man_id', $originalSplit->recipient_id)
                        ->decrement('total_earning', $allocation);

                    \App\Models\UrbanGoodzDriverEarning::firstOrCreate(
                        [
                            'delivery_man_id' => $originalSplit->recipient_id,
                            'earning_type' => 'refund_reversal',
                            'amount' => -$allocation,
                            'description' => "Order Anywhere Refund Reversal - Req #{$request->request_number}",
                        ],
                        [
                            'currency' => $originalSplit->currency ?? 'USD',
                            'status' => 'pending',
                        ]
                    );
                } elseif ($originalSplit->recipient_type === 'dispatcher' && $originalSplit->recipient_id) {
                    \App\Models\DeliveryManWallet::where('delivery_man_id', $originalSplit->recipient_id)
                        ->decrement('total_earning', $allocation);

                    \App\Models\UrbanGoodzDriverEarning::firstOrCreate(
                        [
                            'delivery_man_id' => $originalSplit->recipient_id,
                            'earning_type' => 'dispatcher_commission_reversal',
                            'amount' => -$allocation,
                            'description' => "Order Anywhere Dispatcher Commission Reversal - Req #{$request->request_number}",
                        ],
                        [
                            'currency' => $originalSplit->currency ?? 'USD',
                            'status' => 'pending',
                        ]
                    );
                } elseif ($originalSplit->recipient_type === 'platform') {
                    $admin = \App\Models\Admin::where('role_id', 1)->first();
                    if ($admin) {
                        \App\Models\AdminWallet::where('admin_id', $admin->id)
                            ->decrement('total_commission_earning', $allocation);
                    }
                }

                // Create reversal split
                $ledger = UrbanGoodzPaymentLedger::firstOrCreate(
                    ['idempotency_key' => "refund_reversal:{$request->id}:{$originalSplit->id}:" . md5($allocation . $refundAmount)],
                    [
                        'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
                        'feature' => 'order_anywhere',
                        'payable_type' => OrderAnywhereRequest::class,
                        'payable_id' => $request->id,
                        'event_type' => 'split_reversal',
                        'direction' => 'neutral',
                        'amount' => $allocation,
                        'currency' => $originalSplit->currency ?? 'USD',
                        'payment_method' => 'refund',
                        'payment_status' => 'refunded',
                        'customer_id' => $request->customer_id,
                        'vendor_id' => $request->vendor_id,
                        'delivery_man_id' => $request->assigned_delivery_man_id,
                    ]
                );

                $this->split(
                    $ledger,
                    $request,
                    $originalSplit->recipient_type,
                    $originalSplit->recipient_id,
                    "{$originalSplit->recipient_type}_refund_reversal",
                    $allocation,
                    'reversed',
                    ['original_split_id' => $originalSplit->id]
                );

                $remaining -= $allocation;
            }

            if ($remaining > 0.01) {
                throw new \LogicException('Refund reversal allocations do not reconcile to the refund amount.');
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 9-10: CAPTURE
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Capture customer payment after delivery/completion.
     * Only captures through the SAME provider that authorized.
     */
    public function captureCustomerPayment(OrderAnywhereRequest $request, array $data = []): OrderAnywhereRequest
    {
        return $this->finalizeCustomerPayment($request, $data)->request;
    }

    /**
     * Canonical business identity for "this customer payment has been captured
     * and split".
     *
     * Anchored on the provider plus the provider's payment identifier (for
     * Stripe, the PaymentIntent) plus the internal payable. It deliberately
     * excludes the amount and the webhook event id, so that every event that
     * confirms the same PaymentIntent -- payment_intent.succeeded,
     * charge.succeeded, or a redelivered copy of either -- resolves to one
     * finalization.
     */
    public static function finalizationIdentity(
        string $provider,
        OrderAnywhereRequest $request,
        ?string $paymentReference,
        string $operation = 'capture'
    ): string {
        $internalReference = $request->request_number ?: 'id-' . $request->id;
        $providerReference = $paymentReference ?: 'internal-' . $internalReference;

        return implode(':', [
            'payment_finalization',
            strtolower($provider),
            $operation,
            'order_anywhere',
            $request->id,
            $internalReference,
            $providerReference,
        ]);
    }

    /**
     * Capture a customer payment exactly once.
     *
     * Safe to call repeatedly and concurrently for the same payment: the
     * authoritative order row is locked for the duration of the transaction, so
     * competing webhook deliveries serialize and all but the first observe the
     * committed finalization and return it unchanged.
     */
    public function finalizeCustomerPayment(OrderAnywhereRequest $request, array $data = []): PaymentFinalizationResult
    {
        $amount = (float) ($data['captured_amount'] ?? $request->authorized_amount);
        $currency = strtoupper((string) ($data['currency'] ?? config('urban_goodz_payments.currency', 'USD')));
        $reference = (string) ($data['capture_reference'] ?? ('manual-capture-' . Str::uuid()));
        $paymentReference = trim((string) (
            $data['payment_intent_id']
            ?? $data['psp_reference']
            ?? $reference
        ));

        if (($data['source'] ?? null) === 'webhook' && $paymentReference === '') {
            throw new \InvalidArgumentException('Webhook capture requires a provider payment reference.');
        }

        if (isset($data['platform_fee'], $data['vendor_amount'], $data['driver_amount'])) {
            $manualTotal = (float) $data['platform_fee']
                + (float) $data['vendor_amount']
                + (float) $data['driver_amount']
                + (float) ($data['dispatcher_commission'] ?? 0)
                + (float) ($data['processing_reserve'] ?? 0);
            if (abs($amount - $manualTotal) > 0.01) {
                throw new \InvalidArgumentException('Ledger split mismatch: manual allocations must equal the captured amount.');
            }
        }

        if (($data['source'] ?? null) !== 'webhook') {
            $this->assertLiveAmountWithinCap($amount, $request->customer_id);
        }

        $this->assertPaymentsEnabled();
        $gateway = $this->gatewayForRequest($request);

        return DB::transaction(function () use (
            $request,
            $data,
            $amount,
            $currency,
            $reference,
            $paymentReference,
            $gateway
        ) {
            // Providers fan a single payment out into several events (Stripe sends both
            // payment_intent.succeeded and charge.succeeded, each with its own event id, so
            // the webhook-level guard cannot collapse them). Those deliveries arrive as
            // concurrent requests that all read payment_status = 'authorized' before any of
            // them commits. Take the row lock first so the captures serialise, then re-read
            // the status the winner committed instead of acting on a stale one.
            $request = OrderAnywhereRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $provider = $gateway->providerName();
            $amountMinor = $this->toMinorUnits($amount, $currency);
            $finalizationKey = self::finalizationIdentity(
                $provider,
                $request,
                $paymentReference,
                'capture'
            );
            $captureLedgerKey = $finalizationKey . ':ledger';

            $conflictingFinalization = UrbanGoodzWebhookEvent::where('event_type', 'payment_finalization')
                ->where('provider', $provider)
                ->where('payment_intent_id', $paymentReference)
                ->where('operation', 'capture')
                ->where('idempotency_key', '!=', $finalizationKey)
                ->lockForUpdate()
                ->first();

            if ($conflictingFinalization) {
                throw new PaymentFinalizationConflict('Payment finalization conflicts with another internal payment reference.');
            }

            $existingFinalization = UrbanGoodzWebhookEvent::where('idempotency_key', $finalizationKey)
                ->lockForUpdate()
                ->first();

            $captureLedgers = UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->where('event_type', 'capture')
                ->lockForUpdate()
                ->get();

            if ($captureLedgers->count() > 1) {
                throw new PaymentFinalizationConflict('Conflicting capture ledger rows exist for this payment.');
            }

            $captureLedger = $captureLedgers->first();

            if ($existingFinalization) {
                $this->assertFinalizationMatches(
                    $existingFinalization,
                    $request,
                    $provider,
                    $paymentReference,
                    $amountMinor,
                    $currency
                );
            }

            if ($captureLedger) {
                $this->assertCaptureLedgerMatches(
                    $captureLedger,
                    $request,
                    $paymentReference,
                    $amountMinor,
                    $currency
                );
            }

            $hasCapturedEvidence = $request->payment_status === 'captured'
                || $captureLedger !== null
                || $existingFinalization !== null;

            if ($hasCapturedEvidence
                && $request->capture_reference
                && ! hash_equals((string) $request->capture_reference, $paymentReference)) {
                throw new PaymentFinalizationConflict('Captured payment reference conflicts with the incoming PaymentIntent.');
            }

            $allocationComplete = $this->allocationIsComplete($request, $amountMinor);
            $notificationExists = $this->captureNotificationExists($request);

            if ($existingFinalization
                && $request->payment_status === 'captured'
                && $captureLedger
                && $allocationComplete
                && $notificationExists) {
                $allocationHash = $this->allocationFingerprint($request);
                if (! hash_equals((string) $existingFinalization->allocation_hash, $allocationHash)) {
                    throw new PaymentFinalizationConflict('Persisted finalization allocation does not match the ledger allocation.');
                }

                return new PaymentFinalizationResult(
                    request: $request->fresh(),
                    alreadyProcessed: true,
                    finalizationKey: $finalizationKey
                );
            }

            if (! $hasCapturedEvidence && $request->payment_status !== 'authorized') {
                throw new \InvalidArgumentException(
                    'Cannot capture: payment status is ' . $request->payment_status . '. Must be authorized.'
                );
            }

            // Only the first delivery may call the provider. Recovery and webhook paths
            // reconstruct local state from provider-confirmed capture evidence.
            if (! $hasCapturedEvidence && ($data['source'] ?? null) !== 'webhook') {
                if (! $gateway->isEnabled()) {
                    abort(503, 'The original payment provider is unavailable; capture was not recorded.');
                }

                $gatewayResult = $gateway->capture($request, $amount, $currency, $reference);

                if (! $gatewayResult['success']) {
                    Log::critical('CAPTURE FAILED', [
                        'provider' => $gateway->providerName(),
                        'request_id' => $request->id,
                    ]);
                    abort(500, 'Payment capture failed. Please try again.');
                }

                $request->psp_reference = $gatewayResult['provider_reference'] ?? $request->psp_reference;
                $request->capture_reference = $gatewayResult['provider_reference'] ?? $reference;
            } else {
                $request->psp_reference = $paymentReference;
                $request->capture_reference = $paymentReference;
            }

            $request->update([
                'captured_amount' => $amount,
                'final_amount' => $data['final_amount'] ?? $request->final_amount ?? $amount,
                'payment_status' => 'captured',
                'payment_captured_at' => $request->payment_captured_at ?? now(),
                'capture_idempotency_key' => $captureLedgerKey,
                'merchant_purchase_amount' => $data['merchant_purchase_amount'] ?? $request->merchant_purchase_amount,
                'tax_amount' => $data['tax_amount'] ?? $request->tax_amount,
            ]);

            if (! UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->exists()) {
                if ($request->vendor_id && ! $request->isParticipatingVendor()) {
                    $request->update(['fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_PARTICIPATING_VENDOR]);
                }
                $this->calculateSplits($request, $amount, $data);
                $this->reservePendingSplits($request);
            }

            if (! $captureLedger) {
                $captureLedger = $this->ledger($request, 'capture', 'credit', $amount, 'captured', [
                    'reference' => $paymentReference,
                    'idempotency_key' => $captureLedgerKey,
                    'currency' => $currency,
                    'metadata' => [
                        'source' => $data['source'] ?? 'manual_capture',
                        'provider' => $provider,
                        'payment_intent_id' => $paymentReference,
                        'operation' => 'capture',
                    ],
                ]);
            }

            if (! $this->allocationIsComplete($request, $amountMinor)) {
                $this->reservePendingSplits($request);
                $this->finalizeSplits($request, $data);
                $this->settleSplits($request);
            }

            // Reconciliation is a hard gate in every environment.
            $this->reconcileSplits($request->fresh());

            if (! $this->captureNotificationExists($request)) {
                $request->logPaymentEvent('capture', $amount, $paymentReference, [
                    'provider' => $provider,
                    'payment_intent_id' => $paymentReference,
                    'finalization_key' => $finalizationKey,
                ]);
            }

            $allocationHash = $this->allocationFingerprint($request);
            $finalizationAttributes = [
                'provider' => $provider,
                'event_id' => null,
                'event_type' => 'payment_finalization',
                'payment_intent_id' => $paymentReference,
                'internal_reference' => $request->request_number,
                'operation' => 'capture',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'allocation_hash' => $allocationHash,
                'payable_type' => OrderAnywhereRequest::class,
                'payable_id' => $request->id,
                'received_at' => now(),
                'processed_at' => now(),
                'status' => 'completed',
                'failure_type' => null,
                'result' => [
                    'capture_ledger_id' => $captureLedger->id,
                    'split_count' => UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                        ->where('payable_id', $request->id)
                        ->count(),
                    'capture_notification_count' => $request->activityLogs()
                        ->where('event', 'payment.capture')
                        ->count(),
                ],
            ];

            $finalization = $this->firstOrCreateIdempotent(
                fn () => UrbanGoodzWebhookEvent::firstOrCreate(
                    ['idempotency_key' => $finalizationKey],
                    $finalizationAttributes
                ),
                fn () => UrbanGoodzWebhookEvent::where('idempotency_key', $finalizationKey)->first(),
                fn (UrbanGoodzWebhookEvent $existing) => $this->finalizationMatches(
                    $existing,
                    $request,
                    $provider,
                    $paymentReference,
                    $amountMinor,
                    $currency,
                    $allocationHash
                )
            );

            if ($finalization->status !== 'completed') {
                throw new PaymentFinalizationConflict('Payment finalization did not reach completed status.');
            }

            return new PaymentFinalizationResult(
                request: $request->fresh(),
                alreadyProcessed: $hasCapturedEvidence,
                finalizationKey: $finalizationKey
            );
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 12: CANCELLATION
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cancel order. Behavior depends on timing.
     */
    public function cancelOrder(OrderAnywhereRequest $request, string $reason = ''): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $reason) {
            $paymentStatus = $request->payment_status;

            // Before authorization: simple cancel
            if (in_array($paymentStatus, ['unpaid', 'awaiting_quote', 'quoted', 'awaiting_payment', 'payment_session_created'], true)) {
                $request->update(['payment_status' => 'cancelled']);
                $this->reversePendingSplits($request);
                return $request->transitionTo('cancelled');
            }

            // After authorization but before capture: void authorization + reverse pending splits
            if (in_array($paymentStatus, ['authorized', 'capture_pending'], true)) {
                $gateway = $this->gatewayForRequest($request);

                // Void authorization via provider if possible
                if ($gateway->isEnabled() && $request->psp_reference) {
                    try {
                        $gateway->cancel($request, $request->psp_reference);
                    } catch (\Exception $e) {
                        Log::warning('Authorization void failed', ['error' => $e->getMessage()]);
                    }
                }

                $request->update([
                    'payment_status' => 'cancelled',
                    'payment_refunded_at' => now(),
                ]);

                $this->reversePendingSplits($request);

                $this->ledger($request, 'authorization_voided', 'debit', (float) $request->authorized_amount, 'cancelled', [
                    'reference' => $request->authorization_reference,
                    'reason' => $reason,
                ]);

                return $request->transitionTo('cancelled');
            }

            // After capture: requires refund process
            if (in_array($paymentStatus, ['captured', 'partially_captured'], true)) {
                throw new \InvalidArgumentException('Cannot cancel after capture. Use refund instead.');
            }

            // Default: simple cancel
            $request->update(['payment_status' => 'cancelled']);
            $this->reversePendingSplits($request);
            return $request->transitionTo('cancelled');
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 13: REFUNDS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Refund customer payment. Provider-correct, idempotent, race-safe.
     */
    public function refundCustomerPayment(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        $this->assertPaymentsEnabled();

        return DB::transaction(function () use ($request, $data) {
            // Fresh lock to prevent race condition
            $fresh = OrderAnywhereRequest::lockForUpdate()->find($request->id);

            $capturedAmount = (float) $fresh->captured_amount;
            $currentRefunded = (float) $fresh->refunded_amount;
            $amount = (float) ($data['refund_amount'] ?? $capturedAmount - $currentRefunded);

            if (! in_array($fresh->payment_status, ['captured', 'partially_captured', 'partially_refunded'], true)) {
                throw new \InvalidArgumentException('Cannot refund: payment status is ' . $fresh->payment_status . '. Must be captured.');
            }

            if ($amount <= 0) {
                throw new \InvalidArgumentException('Refund amount must be positive.');
            }
            if ($capturedAmount <= 0) {
                throw new \InvalidArgumentException('Cannot refund: no captured amount exists.');
            }
            if ($currentRefunded + $amount > $capturedAmount) {
                $remaining = $capturedAmount - $currentRefunded;
                throw new \InvalidArgumentException("Refund amount \${$amount} exceeds remaining capturable amount of \${$remaining}.");
            }
            // Provider-correct refund: use the same provider that authorized/captured
            $gateway = $this->gatewayForRequest($fresh);
            $provider = $gateway->providerName();
            $idempotencyKey = $data['refund_idempotency_key']
                ?? "refund:{$provider}:{$fresh->id}:" . hash('sha256', $amount . '|' . ($data['refund_reference'] ?? ''));
            $refundReference = (string) ($data['refund_reference']
                ?? $fresh->refund_reference
                ?? ('refund-' . $fresh->id . '-' . Str::uuid()));

            // Idempotency check
            $existing = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $fresh->fresh();
            }

            // Call gateway
            $gatewayResult = [];
            if (($data['source'] ?? null) !== 'webhook') {
                if (! $gateway->isEnabled()) {
                    abort(503, 'The original payment provider is unavailable; refund was not recorded.');
                }

                $gatewayResult = $gateway->refund(
                    $fresh,
                    $amount,
                    config('urban_goodz_payments.currency', 'USD'),
                    $refundReference,
                    $data['reason'] ?? null
                );

                if (! $gatewayResult['success']) {
                    Log::critical('REFUND FAILED', [
                        'provider' => $provider,
                        'request_id' => $fresh->id,
                        'amount' => $amount,
                    ]);
                    abort(500, 'Refund failed. Please try again.');
                }
            }

            $newRefundedAmount = $currentRefunded + $amount;
            $newPaymentStatus = $newRefundedAmount >= $capturedAmount ? 'refunded' : 'partially_refunded';

            $fresh->update([
                'refunded_amount' => $newRefundedAmount,
                'payment_status' => $newPaymentStatus,
                'payment_refunded_at' => now(),
                'refund_reference' => $gatewayResult['provider_reference'] ?? $refundReference,
                'refund_idempotency_key' => $idempotencyKey,
            ]);

            $this->ledger($fresh, 'refund', 'debit', $amount, $newPaymentStatus, [
                'reference' => $fresh->refund_reference,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'reason' => $data['reason'] ?? null,
                    'provider' => $provider,
                ],
            ]);

            // Reverse released earnings when present; otherwise cancel pending allocations.
            $hasReleasedSplits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $fresh->id)
                ->where('status', 'released')
                ->exists();
            if ($hasReleasedSplits) {
                $this->reverseSettledSplits($fresh, $amount);
            } else {
                $this->reversePendingSplits($fresh);
            }

            $fresh->logPaymentEvent('refund', $amount, $fresh->refund_reference);

            return $fresh->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 14: WEBHOOKS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Record webhook failure for audit trail.
     */
    public function recordWebhookFailure(OrderAnywhereRequest $request, string $eventType, array $metadata = []): void
    {
        $request->update(['payment_status' => $eventType]);

        $this->ledger($request, $eventType, 'debit', 0, 'webhook_failure', [
            'reference' => $metadata['reference'] ?? null,
            'metadata' => array_merge($metadata, [
                'attempted_amount' => $metadata['amount'] ?? 0,
                'failed' => true,
            ]),
        ]);

        $request->logPaymentEvent($eventType, 0, null, ['failed' => true]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // READINESS
    // ═══════════════════════════════════════════════════════════════════════

    public function readiness(): array
    {
        $provider = config('urban_goodz_payments.provider', 'staged_test');
        $providerEnabled = $this->gateway->isEnabled();

        $orderAnywhereStatus = match ($provider) {
            'disabled' => 'payment_disabled',
            'staged_test' => 'staged_test',
            default => $providerEnabled ? 'payment_ready' : 'staged_test',
        };

        return [
            'order_anywhere' => $orderAnywhereStatus,
            'fashion_fit' => 'payment_pending',
            'earn_money' => 'payment_pending',
            'logistics' => 'payment_pending',
            'load_board' => 'payment_pending',
            'medical_courier' => 'payment_pending',
            'book_anything' => 'payment_pending',
            'rentals' => 'payment_pending',
            'events' => 'payment_pending',
            'creator_commerce' => 'payment_pending',
            'community_marketplace' => 'no_payment_needed',
            'discovery' => 'no_payment_needed',
            'ask_urban_goodz' => 'no_payment_needed',
            'urban_goodz_plus' => 'payment_pending',
            'spotlight' => 'no_payment_needed',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RECEIPT
    // ═══════════════════════════════════════════════════════════════════════

    public function storeReceipt(OrderAnywhereRequest $request, string $path): OrderAnywhereRequest
    {
        $request->update(['receipt_path' => $path]);
        $request->logActivity('receipt_uploaded', 'Receipt uploaded', [], ['path' => $path]);
        return $request->fresh();
    }

    public function reconcileReceipt(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $receiptAmount = (float) ($data['receipt_amount'] ?? 0);
            $authorizedAmount = (float) $request->authorized_amount;
            $difference = $receiptAmount - $authorizedAmount;

            $request->update([
                'receipt_amount' => $receiptAmount,
                'receipt_difference' => $difference,
                'receipt_notes' => $data['receipt_notes'] ?? null,
                'reconciliation_status' => abs($difference) <= ($request->overage_threshold ?? 5.00) ? 'auto_approved' : 'pending_review',
            ]);

            $request->logActivity('receipt_reconciled', "Receipt reconciled: \${$receiptAmount} (difference: \${$difference})", [], $data);

            return $request->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RECONCILIATION
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Validate that all splits reconcile to the captured amount.
     * Returns true if difference <= $0.01 (currency rounding tolerance).
     * Throws if reconciliation fails.
     */
    public function reconcileSplits(OrderAnywhereRequest $request): bool
    {
        $capturedAmount = (float) $request->captured_amount;
        $refundedAmount = (float) $request->refunded_amount;
        $netCaptured = $capturedAmount - $refundedAmount;

        $merchantPurchase = (float) $request->merchant_purchase_amount;
        $vendorPayout = (float) $request->vendor_payout_amount;
        $driverPayout = (float) $request->driver_payout_amount;
        $dispatcherCommission = (float) $request->dispatcher_commission;
        $urbanGoodzRevenue = (float) $request->urban_goodz_revenue;
        $processingReserve = (float) $request->processing_reserve;

        if ($request->isExternalMerchant()) {
            $expectedTotal = $merchantPurchase + $vendorPayout + $driverPayout + $dispatcherCommission + $urbanGoodzRevenue + $processingReserve;
        } else {
            $expectedTotal = $vendorPayout + $driverPayout + $dispatcherCommission + $urbanGoodzRevenue + $processingReserve;
        }

        $difference = abs($netCaptured - $expectedTotal);

        if ($difference > 0.01) {
            Log::critical('RECONCILIATION FAILED', [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'net_captured' => $netCaptured,
                'expected_total' => $expectedTotal,
                'difference' => $difference,
                'breakdown' => [
                    'merchant_purchase' => $merchantPurchase,
                    'vendor_payout' => $vendorPayout,
                    'driver_payout' => $driverPayout,
                    'dispatcher_commission' => $dispatcherCommission,
                    'urban_goodz_revenue' => $urbanGoodzRevenue,
                    'processing_reserve' => $processingReserve,
                ],
            ]);

            throw new \LogicException(
                "Reconciliation failed for {$request->request_number}: " .
                "net captured \${$netCaptured} ≠ expected \${$expectedTotal} (diff: \${$difference})"
            );
        }

        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function assertFinalizationMatches(
        UrbanGoodzWebhookEvent $finalization,
        OrderAnywhereRequest $request,
        string $provider,
        string $paymentReference,
        int $amountMinor,
        string $currency
    ): void {
        if (! $this->finalizationMatches(
            $finalization,
            $request,
            $provider,
            $paymentReference,
            $amountMinor,
            $currency
        )) {
            throw new PaymentFinalizationConflict('Existing payment finalization conflicts with the incoming capture.');
        }
    }

    private function finalizationMatches(
        UrbanGoodzWebhookEvent $finalization,
        OrderAnywhereRequest $request,
        string $provider,
        string $paymentReference,
        int $amountMinor,
        string $currency,
        ?string $allocationHash = null
    ): bool {
        $matches = $finalization->event_type === 'payment_finalization'
            && $finalization->provider === $provider
            && $finalization->payment_intent_id === $paymentReference
            && $finalization->internal_reference === $request->request_number
            && $finalization->operation === 'capture'
            && (int) $finalization->amount_minor === $amountMinor
            && strtoupper((string) $finalization->currency) === $currency
            && $finalization->payable_type === OrderAnywhereRequest::class
            && (int) $finalization->payable_id === (int) $request->id;

        if (! $matches || $allocationHash === null) {
            return $matches;
        }

        return is_string($finalization->allocation_hash)
            && hash_equals($finalization->allocation_hash, $allocationHash);
    }

    private function assertCaptureLedgerMatches(
        UrbanGoodzPaymentLedger $ledger,
        OrderAnywhereRequest $request,
        string $paymentReference,
        int $amountMinor,
        string $currency
    ): void {
        $matches = $ledger->payable_type === OrderAnywhereRequest::class
            && (int) $ledger->payable_id === (int) $request->id
            && $ledger->event_type === 'capture'
            && $ledger->direction === 'credit'
            && $this->toMinorUnits((float) $ledger->amount, (string) $ledger->currency) === $amountMinor
            && strtoupper((string) $ledger->currency) === $currency
            && $ledger->reference === $paymentReference;

        if (! $matches) {
            throw new PaymentFinalizationConflict('Existing capture ledger conflicts with the incoming capture.');
        }
    }

    private function allocationIsComplete(OrderAnywhereRequest $request, int $amountMinor): bool
    {
        $splits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->get();

        if ($splits->isEmpty() || $splits->contains(fn (UrbanGoodzPaymentSplit $split) => $split->status !== 'released')) {
            return false;
        }

        $allocatedMinor = $splits->sum(
            fn (UrbanGoodzPaymentSplit $split) => $this->toMinorUnits(
                (float) $split->amount,
                (string) ($split->currency ?: config('urban_goodz_payments.currency', 'USD'))
            )
        );

        return (int) $allocatedMinor === $amountMinor;
    }

    private function allocationFingerprint(OrderAnywhereRequest $request): string
    {
        $allocation = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->get()
            ->map(fn (UrbanGoodzPaymentSplit $split) => [
                'recipient_type' => $split->recipient_type,
                'recipient_id' => $split->recipient_id,
                'split_type' => $split->split_type,
                'amount_minor' => $this->toMinorUnits((float) $split->amount, (string) $split->currency),
                'currency' => strtoupper((string) $split->currency),
            ])
            ->sortBy(fn (array $row) => implode('|', [
                $row['recipient_type'],
                $row['recipient_id'] ?? 'platform',
                $row['split_type'],
                $row['amount_minor'],
                $row['currency'],
            ]))
            ->values()
            ->all();

        return hash('sha256', json_encode($allocation, JSON_THROW_ON_ERROR));
    }

    private function captureNotificationExists(OrderAnywhereRequest $request): bool
    {
        return $request->activityLogs()
            ->where('event', 'payment.capture')
            ->exists();
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        $minorUnits = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KRW' => 0];
        $exponent = $minorUnits[strtoupper($currency)] ?? 2;

        return (int) round($amount * pow(10, $exponent));
    }

    private function gatewayForRequest(OrderAnywhereRequest $request): PaymentGatewayInterface
    {
        $provider = $request->payment_provider;

        if (! $provider) {
            return $this->gateway;
        }

        if (! in_array($provider, ['adyen', 'stripe', 'staged_test', 'disabled'], true)) {
            throw new \LogicException("Unsupported stored payment provider [{$provider}].");
        }

        return $this->providerManager->resolveProvider($provider);
    }

    private function ledger(OrderAnywhereRequest $request, string $event, string $direction, float $amount, string $status, array $options = []): UrbanGoodzPaymentLedger
    {
        $key = $options['idempotency_key'] ?? implode(':', [
            'order_anywhere',
            $this->gateway->providerName(),
            $request->id,
            $event,
            $options['reference'] ?? number_format($amount, 2, '.', ''),
        ]);

        $attributes = $this->ledgerAttributes($request, $event, $direction, $amount, $status, $options);

        return $this->firstOrCreateIdempotent(
            fn () => UrbanGoodzPaymentLedger::firstOrCreate(
                ['idempotency_key' => $key],
                $attributes
            ),
            fn () => UrbanGoodzPaymentLedger::where('idempotency_key', $key)->first(),
            fn (UrbanGoodzPaymentLedger $existing) => $existing->feature === $attributes['feature']
                && $existing->payable_type === $attributes['payable_type']
                && (int) $existing->payable_id === (int) $attributes['payable_id']
                && $existing->event_type === $attributes['event_type']
                && $existing->direction === $attributes['direction']
                && $this->toMinorUnits((float) $existing->amount, (string) $existing->currency)
                    === $this->toMinorUnits((float) $attributes['amount'], (string) $attributes['currency'])
                && strtoupper((string) $existing->currency) === strtoupper((string) $attributes['currency'])
                && $existing->reference === $attributes['reference']
        );
    }

    /**
     * Run an idempotent write, tolerating a concurrent writer that wins the unique index.
     *
     * firstOrCreate is a SELECT followed by an INSERT, so two concurrent captures for the
     * same request can both miss on the SELECT and race to the INSERT. The loser gets a
     * duplicate-key error even though the write it wanted has already happened. Recover by
     * re-reading the row the winner committed. Any other database error still propagates.
     */
    private function firstOrCreateIdempotent(\Closure $write, \Closure $reread, ?\Closure $matches = null)
    {
        $duplicateException = null;

        try {
            $result = $write();
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $duplicateException = $e;
            $result = $reread();
        }

        if (! $result) {
            throw $duplicateException ?? new \LogicException('Idempotent write did not return a persisted record.');
        }

        if ($matches && ! $matches($result)) {
            throw new PaymentFinalizationConflict(
                'Idempotency identity collided with conflicting persisted data.',
                0,
                $duplicateException
            );
        }

        return $result;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = strtolower($e->getMessage());

        return $driverCode === 1062
            || $sqlState === '23505'
            || ($driverCode === 19 && str_contains($message, 'unique'))
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }

    /**
     * @return array<string, mixed>
     */
    private function ledgerAttributes(OrderAnywhereRequest $request, string $event, string $direction, float $amount, string $status, array $options): array
    {
        return [
            'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
            'feature' => 'order_anywhere',
            'payable_type' => OrderAnywhereRequest::class,
            'payable_id' => $request->id,
            'event_type' => $event,
            'direction' => $direction,
            'amount' => $amount,
            'currency' => $options['currency'] ?? config('urban_goodz_payments.currency', 'USD'),
            'payment_method' => $options['payment_method'] ?? $request->payment_method,
            'payment_status' => $status,
            'reference' => $options['reference'] ?? null,
            'customer_id' => $request->customer_id,
            'vendor_id' => $request->vendor_id,
            'delivery_man_id' => $request->assigned_delivery_man_id,
            'created_by_admin_id' => auth('admin')->id() ?? null,
            'metadata' => $options['metadata'] ?? [],
        ];
    }

    private function split(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request, string $recipientType, ?int $recipientId, string $splitType, float $amount, string $status = 'pending', array $metadata = []): void
    {
        if ($amount <= 0) {
            return;
        }

        $splitKey = implode(':', [$ledger->id, $recipientType, $recipientId ?: 'platform', $splitType]);

        $currency = (string) config('urban_goodz_payments.currency', 'USD');

        $this->firstOrCreateIdempotent(
            fn () => UrbanGoodzPaymentSplit::firstOrCreate(
                ['idempotency_key' => $splitKey],
                [
                    'ledger_id' => $ledger->id,
                    'feature' => 'order_anywhere',
                    'payable_type' => OrderAnywhereRequest::class,
                    'payable_id' => $request->id,
                    'recipient_type' => $recipientType,
                    'recipient_id' => $recipientId,
                    'split_type' => $splitType,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'metadata' => $metadata,
                ]
            ),
            fn () => UrbanGoodzPaymentSplit::where('idempotency_key', $splitKey)->first(),
            fn (UrbanGoodzPaymentSplit $existing) => (int) $existing->ledger_id === (int) $ledger->id
                && $existing->payable_type === OrderAnywhereRequest::class
                && (int) $existing->payable_id === (int) $request->id
                && $existing->recipient_type === $recipientType
                && (int) ($existing->recipient_id ?? 0) === (int) ($recipientId ?? 0)
                && $existing->split_type === $splitType
                && strtoupper((string) $existing->currency) === strtoupper($currency)
                && $this->toMinorUnits((float) $existing->amount, (string) $existing->currency)
                    === $this->toMinorUnits($amount, $currency)
        );
    }
}
