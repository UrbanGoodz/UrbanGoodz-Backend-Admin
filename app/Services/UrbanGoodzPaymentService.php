<?php

namespace App\Services;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\Payments\PaymentStatus;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Services\Payments\PaymentProviderManager;
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

    // ─── Live Mode Guard ──────────────────────────────────────────────────

    private function assertPaymentsEnabled(): void
    {
        if (OrderAnywhereRequest::isPaymentDisabled() || $this->providerManager->isDisabled()) {
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
                'provider' => $this->gateway->providerName(),
            ]);
            abort(403, "Live payment of \${$amount} exceeds maximum allowed cap of \${$maxAmount}. Reduce the amount or switch to sandbox mode.");
        }

        if (auth('admin')->check() && ! OrderAnywhereRequest::isLiveAdminAllowed()) {
            Log::critical('LIVE PAYMENT BLOCKED: admin not in allowed list', [
                'admin_id' => auth('admin')->id(),
                'provider' => $this->gateway->providerName(),
            ]);
            abort(403, 'You are not authorized to initiate live payments.');
        }

        if (! OrderAnywhereRequest::isLiveCustomerAllowed($customerId)) {
            Log::critical('LIVE PAYMENT BLOCKED: customer not in allowed list', [
                'customer_id' => $customerId,
                'provider' => $this->gateway->providerName(),
            ]);
            abort(403, 'This customer is not authorized for live payments.');
        }
    }

    private function logLivePaymentAttempt(string $action, float $amount, int $requestId, string $reference, bool $blocked = false): void
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
            'blocked' => $blocked,
            'timestamp' => now()->toISOString(),
        ]);
    }

    // ─── Payment Link Creation ────────────────────────────────────────────

    public function createPaymentLink(OrderAnywhereRequest $request, array $data = []): array
    {
        $this->assertPaymentsEnabled();

        $amount = (float) ($data['amount'] ?? $request->final_amount ?? $request->quote_amount ?? 0);
        $currency = $data['currency'] ?? config('urban_goodz_payments.currency', 'USD');
        $reference = $request->request_number;
        $description = $data['description'] ?? "Order Anywhere - {$request->request_number}";

        $this->assertLiveAmountWithinCap($amount, $request->customer_id);

        if (OrderAnywhereRequest::isLiveMode()) {
            $this->logLivePaymentAttempt('create_payment_link', $amount, $request->id, $reference);
        }

        $idempotencyKey = "payment_link:{$this->gateway->providerName()}:{$request->id}:" . md5($amount . $currency . $reference);

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
            Log::critical('PAYMENT LINK FAILED: provider returned failure', [
                'provider' => $this->gateway->providerName(),
                'request_id' => $request->id,
                'reference' => $reference,
                'result' => $result,
            ]);
            abort(500, 'Payment link creation failed. Please try again.');
        }

        $request->update([
            'payment_provider' => $this->gateway->providerName(),
            'provider_reference' => $result['provider_reference'],
            'merchant_reference' => $reference,
            'payment_link_id' => $result['payment_link_id'],
            'payment_url' => $result['payment_url'],
            'psp_reference' => $result['provider_reference'],
        ]);

        $this->ledger($request, 'payment_link_created', 'credit', $amount, 'payment_link_created', [
            'reference' => $result['provider_reference'],
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'payment_link_id' => $result['payment_link_id'],
                'payment_url' => $result['payment_url'],
                'provider' => $this->gateway->providerName(),
                'staged_test' => $result['staged_test'] ?? false,
            ],
        ]);

        if ($result['staged_test'] ?? false) {
            $request->logActivity(
                'payment_link_created_staged',
                "Staged test payment link created for \${$amount}",
                [],
                $result,
                ['mode' => 'staged_test', 'provider' => $this->gateway->providerName()]
            );
        } else {
            $request->logPaymentEvent('link_created', $amount, $result['provider_reference'], [
                'payment_url' => $result['payment_url'],
                'provider' => $this->gateway->providerName(),
            ]);
        }

        return $result;
    }

    // ─── Quote ────────────────────────────────────────────────────────────

    public function quoteOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $oldStatus = $request->status;

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

            if ($oldStatus !== $request->status) {
                $request->logStatusTransition($oldStatus, $request->status);
            }

            $request->logPaymentEvent('quote', (float) $request->quote_amount, $data['quote_reference'] ?? null);

            return $request->fresh();
        });
    }

    // ─── Authorize ────────────────────────────────────────────────────────

    public function authorizeOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        $this->assertPaymentsEnabled();
        $amount = (float) ($data['authorized_amount'] ?? $request->final_amount ?? $request->quote_amount);

        if (($data['source'] ?? null) !== 'webhook') {
            $this->assertLiveAmountWithinCap($amount, $request->customer_id);

            if (OrderAnywhereRequest::isLiveMode()) {
                $this->logLivePaymentAttempt('authorize', $amount, $request->id, $request->request_number);
            }
        }

        return DB::transaction(function () use ($request, $data, $amount) {
            $reference = $data['authorization_reference'] ?? $data['psp_reference'] ?? 'manual-auth-' . Str::uuid();

            $idempotencyKey = "authorize:{$this->gateway->providerName()}:{$request->id}:" . md5($amount . $reference);

            $existing = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $request->fresh();
            }

            if ($this->gateway->isEnabled() && ($data['source'] ?? null) !== 'webhook') {
                $gatewayResult = $this->gateway->authorize($request, $amount, config('urban_goodz_payments.currency', 'USD'), $reference, $data['psp_reference'] ?? null);

                if (! $gatewayResult['success']) {
                    Log::critical('AUTHORIZATION FAILED: provider returned failure', [
                        'provider' => $this->gateway->providerName(),
                        'request_id' => $request->id,
                        'reference' => $reference,
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

            $request->authorized_amount = $amount;
            $request->payment_method = $data['payment_method'] ?? $request->payment_method ?? 'manual';
            $request->payment_status = 'authorized';
            $request->payment_authorized_at = now();
            $request->save();

            $this->ledger($request, 'authorization', 'credit', $amount, 'authorized', [
                'reference' => $request->authorization_reference,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $request->payment_method,
                'metadata' => [
                    'source' => $data['source'] ?? 'manual',
                    'psp_reference' => $request->psp_reference,
                    'provider' => $this->gateway->providerName(),
                ],
            ]);

            $request->logPaymentEvent('authorization', $amount, $request->authorization_reference, [
                'psp_reference' => $request->psp_reference,
                'source' => $data['source'] ?? 'manual',
                'provider' => $this->gateway->providerName(),
            ]);

            return $request->fresh();
        });
    }

    // ─── Capture ──────────────────────────────────────────────────────────

    public function captureOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        $this->assertPaymentsEnabled();
        $amount = (float) ($data['captured_amount'] ?? $request->authorized_amount ?? $request->final_amount);

        if (($data['source'] ?? null) !== 'webhook') {
            $this->assertLiveAmountWithinCap($amount, $request->customer_id);

            if (OrderAnywhereRequest::isLiveMode()) {
                $this->logLivePaymentAttempt('capture', $amount, $request->id, $request->request_number);
            }
        }

        return DB::transaction(function () use ($request, $data, $amount) {
            $reference = $data['capture_reference'] ?? 'manual-capture-' . Str::uuid();

            $idempotencyKey = "capture:{$this->gateway->providerName()}:{$request->id}:" . md5($amount . $reference);

            $existing = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $request->fresh();
            }

            if ($this->gateway->isEnabled() && ($data['source'] ?? null) !== 'webhook') {
                $gatewayResult = $this->gateway->capture($request, $amount, config('urban_goodz_payments.currency', 'USD'), $reference);

                if (! $gatewayResult['success']) {
                    Log::critical('CAPTURE FAILED: provider returned failure', [
                        'provider' => $this->gateway->providerName(),
                        'request_id' => $request->id,
                        'reference' => $reference,
                    ]);
                    abort(500, 'Payment capture failed. Please try again.');
                }

                $request->psp_reference = $gatewayResult['provider_reference'] ?? $request->psp_reference;
                $request->capture_reference = $gatewayResult['provider_reference'] ?? $reference;
            } elseif (($data['psp_reference'] ?? null) && ($data['source'] ?? null) === 'webhook') {
                $request->psp_reference = $data['psp_reference'];
                $request->capture_reference = $data['psp_reference'];
            } else {
                $request->capture_reference = $reference;
            }

            $request->captured_amount = $amount;
            $request->final_amount = $data['final_amount'] ?? $request->final_amount ?? $amount;
            $request->payment_method = $data['payment_method'] ?? $request->payment_method ?? 'manual';
            $request->payment_status = 'captured';
            $request->payment_captured_at = now();
            $request->save();

            $ledger = $this->ledger($request, 'capture', 'credit', $amount, 'captured', [
                'reference' => $request->capture_reference,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $request->payment_method,
                'metadata' => [
                    'source' => $data['source'] ?? 'manual_capture',
                    'psp_reference' => $request->psp_reference,
                    'provider' => $this->gateway->providerName(),
                ],
            ]);

            $this->captureSplits($ledger, $request, $amount, $data);

            $request->logPaymentEvent('capture', $amount, $request->capture_reference, [
                'source' => $data['source'] ?? 'manual',
                'psp_reference' => $request->psp_reference,
                'provider' => $this->gateway->providerName(),
            ]);

            return $request->fresh();
        });
    }

    // ─── Refund ───────────────────────────────────────────────────────────

    public function refundOrderAnywhere(OrderAnywhereRequest $request, array $data): OrderAnywhereRequest
    {
        $this->assertPaymentsEnabled();
        $amount = (float) ($data['refund_amount'] ?? 0);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $currentRefunded = (float) $request->refunded_amount;
        $capturedAmount = (float) $request->captured_amount;

        if ($capturedAmount <= 0) {
            throw new \InvalidArgumentException('Cannot refund: no captured amount exists. Payment must be captured first.');
        }

        if ($currentRefunded + $amount > $capturedAmount) {
            throw new \InvalidArgumentException(
                "Refund of \${$amount} would exceed captured amount. Already refunded: \${$currentRefunded}, Captured: \${$capturedAmount}."
            );
        }

        if (($data['source'] ?? null) !== 'webhook') {
            $this->assertLiveAmountWithinCap($amount, $request->customer_id);

            if (OrderAnywhereRequest::isLiveMode()) {
                $this->logLivePaymentAttempt('refund', $amount, $request->id, $request->request_number);
            }
        }

        return DB::transaction(function () use ($request, $data, $amount) {
            $reference = $data['refund_reference'] ?? 'manual-refund-' . Str::uuid();

            $idempotencyKey = "refund:{$this->gateway->providerName()}:{$request->id}:" . md5($amount . $reference);

            $existing = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $request->fresh();
            }

            if ($this->gateway->isEnabled() && ($data['source'] ?? null) !== 'webhook') {
                $gatewayResult = $this->gateway->refund($request, $amount, config('urban_goodz_payments.currency', 'USD'), $reference, $data['reason'] ?? null);

                if (! $gatewayResult['success']) {
                    Log::critical('REFUND FAILED: provider returned failure', [
                        'provider' => $this->gateway->providerName(),
                        'request_id' => $request->id,
                        'reference' => $reference,
                    ]);
                    abort(500, 'Refund failed. Please try again.');
                }

                $request->psp_reference = $gatewayResult['provider_reference'] ?? $request->psp_reference;
                $request->refund_reference = $gatewayResult['provider_reference'] ?? $reference;
            } elseif (($data['psp_reference'] ?? null) && ($data['source'] ?? null) === 'webhook') {
                $request->psp_reference = $data['psp_reference'];
                $request->refund_reference = $data['psp_reference'];
            } else {
                $request->refund_reference = $reference;
            }

            $request->refunded_amount = (float) $request->refunded_amount + $amount;
            $request->payment_status = 'refunded';
            $request->payment_refunded_at = now();
            $request->save();

            $ledger = $this->ledger($request, 'refund', 'debit', $amount, 'refunded', [
                'reference' => $request->refund_reference,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $request->payment_method,
                'metadata' => [
                    'reason' => $data['reason'] ?? null,
                    'psp_reference' => $request->psp_reference,
                    'provider' => $this->gateway->providerName(),
                ],
            ]);

            $this->reversalSplits($ledger, $request, $amount);
            $this->applyReleasedReversals($ledger, $request);

            $request->logPaymentEvent('refund', $amount, $request->refund_reference, [
                'reason' => $data['reason'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'provider' => $this->gateway->providerName(),
            ]);

            return $request->fresh();
        });
    }

    // ─── Receipt ──────────────────────────────────────────────────────────

    public function recordWebhookFailure(
        OrderAnywhereRequest $request,
        string $status,
        ?string $reference,
        float $attemptedAmount,
        array $metadata = []
    ): bool {
        return DB::transaction(function () use ($request, $status, $reference, $attemptedAmount, $metadata) {
            $provider = $metadata['provider'] ?? $this->gateway->providerName();
            $idempotencyKey = implode(':', [
                'webhook_failure',
                $provider,
                $request->id,
                $status,
                $reference ?: 'no-reference',
            ]);

            if (UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->exists()) {
                return false;
            }

            $request->update(['payment_status' => $status]);

            $this->ledger($request, $status, 'debit', 0.0, $status, [
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_merge($metadata, [
                    'source' => 'webhook',
                    'failed' => true,
                    'attempted_amount' => $attemptedAmount,
                    'provider' => $provider,
                ]),
            ]);

            $request->logPaymentEvent($status, $attemptedAmount, $reference, [
                'source' => 'webhook',
                'failed' => true,
                'provider' => $provider,
            ]);

            return true;
        });
    }

    public function storeReceipt(OrderAnywhereRequest $request, string $path): OrderAnywhereRequest
    {
        $request->receipt_path = $path;
        $request->save();

        $request->logActivity('receipt_uploaded', 'Receipt uploaded', [], ['path' => $path]);

        return $request->fresh();
    }

    // ─── Readiness ────────────────────────────────────────────────────────

    public function readiness(): array
    {
        $mode = OrderAnywhereRequest::paymentMode();
        $provider = config('urban_goodz_payments.provider', 'staged_test');
        $providerEnabled = $this->gateway->isEnabled();

        $orderAnywhereStatus = match ($provider) {
            'disabled' => 'payment_disabled',
            'staged_test' => 'staged_test',
            default => $providerEnabled ? 'payment_ready' : 'staged_test',
        };

        return [
            'order_anywhere' => $orderAnywhereStatus,
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

    public function settleSplits(OrderAnywhereRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $splits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->where('status', 'manual_pending')
                ->lockForUpdate()
                ->get();

            if (in_array($request->status, ['cancelled', 'failed'], true) || ($request->payment_status === 'refunded' && $request->captured_amount <= $request->refunded_amount)) {
                foreach ($splits as $split) {
                    $split->update(['status' => 'cancelled']);
                }
                return;
            }

            foreach ($splits as $split) {
                if ($split->status !== 'manual_pending') {
                    continue;
                }

                $amount = (float) $split->amount;
                if ($amount <= 0) {
                    continue;
                }

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

                if ($split->recipient_type === 'platform') {
                    $admin = \App\Models\Admin::where('role_id', 1)->first();
                    if ($admin) {
                        $adminWallet = \App\Models\AdminWallet::firstOrCreate(['admin_id' => $admin->id]);
                        $adminWallet->increment('total_commission_earning', $finalAmount);
                    }
                } elseif ($split->recipient_type === 'vendor') {
                    $vendorId = $split->recipient_id;
                    if ($vendorId) {
                        $vendorWallet = \App\Models\StoreWallet::firstOrCreate(['vendor_id' => $vendorId]);
                        $vendorWallet->increment('total_earning', $finalAmount);
                    }
                } elseif ($split->recipient_type === 'driver') {
                    $driverId = $split->recipient_id;
                    if ($driverId) {
                        $dmWallet = \App\Models\DeliveryManWallet::firstOrCreate(['delivery_man_id' => $driverId]);
                        $dmWallet->increment('total_earning', $finalAmount);

                        \App\Models\UrbanGoodzDriverEarning::firstOrCreate(
                            [
                                'delivery_man_id' => $driverId,
                                'dedicated_route_id' => null,
                                'business_client_job_id' => null,
                                'package_id' => null,
                                'earning_type' => 'per_package',
                                'amount' => $finalAmount,
                                'description' => "Order Anywhere Delivery - Req #{$request->request_number}",
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
        });
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function ledger(OrderAnywhereRequest $request, string $event, string $direction, float $amount, string $status, array $options = []): UrbanGoodzPaymentLedger
    {
        $key = $options['idempotency_key'] ?? implode(':', [
            'order_anywhere',
            $this->gateway->providerName(),
            $request->id,
            $event,
            $options['reference'] ?? number_format($amount, 2, '.', ''),
        ]);

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
                'currency' => config('urban_goodz_payments.currency', 'USD'),
                'payment_method' => $options['payment_method'] ?? $request->payment_method,
                'payment_status' => $status,
                'reference' => $options['reference'] ?? null,
                'customer_id' => $request->customer_id,
                'vendor_id' => $request->vendor_id,
                'delivery_man_id' => $request->assigned_delivery_man_id,
                'created_by_admin_id' => auth('admin')->id() ?? null,
                'metadata' => $options['metadata'] ?? [],
            ]
        );
    }

    private function captureSplits(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request, float $amount, array $data): void
    {
        $feePercent = (float) config('urban_goodz_payments.default_platform_fee_percent', 10);
        $platformFee = (float) ($data['platform_fee'] ?? round($amount * ($feePercent / 100), 2));
        $driverAmount = (float) ($data['driver_amount'] ?? 0);

        if (isset($data['vendor_amount'])) {
            $vendorAmount = (float) $data['vendor_amount'];
            if (abs(($platformFee + $driverAmount + $vendorAmount) - $amount) > 0.01) {
                throw new \InvalidArgumentException("Ledger split mismatch: Platform fee (\${$platformFee}) + Driver amount (\${$driverAmount}) + Vendor amount (\${$vendorAmount}) does not equal captured amount (\${$amount})");
            }
        } else {
            $vendorAmount = max($amount - $platformFee - $driverAmount, 0);
        }

        $this->split($ledger, $request, 'platform', null, 'platform_fee', $platformFee, 'manual_pending');
        $this->split($ledger, $request, 'vendor', $request->vendor_id, 'vendor_earning', $vendorAmount, 'manual_pending');

        if ($request->assigned_delivery_man_id && $driverAmount > 0) {
            $this->split($ledger, $request, 'driver', $request->assigned_delivery_man_id, 'driver_earning', $driverAmount, 'manual_pending');
        }
    }

    private function reversalSplits(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request, float $amount): void
    {
        $this->split($ledger, $request, 'customer', $request->customer_id, 'refund', $amount, 'reversed');

        $remaining = $amount;
        $priority = ['vendor' => 0, 'driver' => 1, 'platform' => 2];
        $originalSplits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->whereIn('split_type', ['vendor_earning', 'driver_earning', 'platform_fee'])
            ->get()
            ->sortBy(fn (UrbanGoodzPaymentSplit $split) => $priority[$split->recipient_type] ?? 99);

        foreach ($originalSplits as $originalSplit) {
            if ($remaining <= 0) {
                break;
            }

            $alreadyReversed = (float) UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->where('recipient_type', $originalSplit->recipient_type)
                ->where('recipient_id', $originalSplit->recipient_id)
                ->where('split_type', "{$originalSplit->recipient_type}_refund_reversal")
                ->sum('amount');
            $available = max((float) $originalSplit->amount - $alreadyReversed, 0);
            $allocation = min($available, $remaining);

            if ($allocation <= 0) {
                continue;
            }

            $this->split(
                $ledger,
                $request,
                $originalSplit->recipient_type,
                $originalSplit->recipient_id,
                "{$originalSplit->recipient_type}_refund_reversal",
                $allocation,
                'reversed'
            );
            $remaining -= $allocation;
        }

        if ($remaining > 0.01) {
            throw new \LogicException('Refund reversal allocations do not reconcile to the refund amount.');
        }
    }

    private function applyReleasedReversals(UrbanGoodzPaymentLedger $ledger, OrderAnywhereRequest $request): void
    {
        $reversals = UrbanGoodzPaymentSplit::where('ledger_id', $ledger->id)
            ->whereIn('split_type', [
                'vendor_refund_reversal',
                'driver_refund_reversal',
                'platform_refund_reversal',
            ])
            ->get();

        foreach ($reversals as $reversal) {
            $wasReleased = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
                ->where('payable_id', $request->id)
                ->where('recipient_type', $reversal->recipient_type)
                ->where('recipient_id', $reversal->recipient_id)
                ->whereIn('split_type', ['vendor_earning', 'driver_earning', 'platform_fee'])
                ->where('status', 'released')
                ->exists();

            if (! $wasReleased) {
                continue;
            }

            $amount = (float) $reversal->amount;

            if ($reversal->recipient_type === 'vendor' && $reversal->recipient_id) {
                \App\Models\StoreWallet::where('vendor_id', $reversal->recipient_id)
                    ->decrement('total_earning', $amount);
            } elseif ($reversal->recipient_type === 'driver' && $reversal->recipient_id) {
                \App\Models\DeliveryManWallet::where('delivery_man_id', $reversal->recipient_id)
                    ->decrement('total_earning', $amount);

                \App\Models\UrbanGoodzDriverEarning::firstOrCreate(
                    [
                        'delivery_man_id' => $reversal->recipient_id,
                        'earning_type' => 'refund_reversal',
                        'amount' => -$amount,
                        'description' => "Order Anywhere Refund Reversal - Req #{$request->request_number} - Ledger #{$ledger->id}",
                    ],
                    [
                        'currency' => $reversal->currency ?? 'USD',
                        'status' => 'pending',
                    ]
                );
            } elseif ($reversal->recipient_type === 'platform') {
                $admin = \App\Models\Admin::where('role_id', 1)->first();
                if ($admin) {
                    \App\Models\AdminWallet::where('admin_id', $admin->id)
                        ->decrement('total_commission_earning', $amount);
                }
            }
        }
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
                'currency' => config('urban_goodz_payments.currency', 'USD'),
                'status' => $status,
                'metadata' => [],
            ]
        );
    }
}
