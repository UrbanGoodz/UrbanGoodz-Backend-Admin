<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Exceptions\PaymentFinalizationConflictException;
use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzWebhookEvent;
use App\Services\Payments\PaymentFinalizationResult;
use App\Services\Payments\PaymentProviderManager;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    private PaymentProviderManager $providerManager;

    public function __construct(PaymentProviderManager $providerManager)
    {
        $this->providerManager = $providerManager;
    }

    public function handle(Request $request, string $provider, UrbanGoodzPaymentService $payments): Response
    {
        $allowedProviders = ['adyen', 'stripe', 'staged_test'];

        if (! in_array($provider, $allowedProviders, true)) {
            Log::warning('Payment webhook received for unknown provider', ['provider' => $provider]);

            return response('OK', 200);
        }

        if ($this->providerManager->isDisabled()) {
            Log::warning('Payment webhook received but payments are globally disabled', ['provider' => $provider]);

            return response('OK', 200);
        }

        $gateway = $this->providerManager->resolveProvider($provider);

        if (! $gateway->isEnabled()) {
            Log::warning('Payment webhook received for disabled provider', ['provider' => $provider]);

            return response('OK', 200);
        }

        $payload = $this->extractPayload($request, $provider);
        $headers = $this->extractHeaders($request, $provider);
        $payloadHash = hash('sha256', is_string($payload) ? $payload : json_encode($payload));

        if (! $gateway->validateWebhook($payload, $headers)) {
            $this->recordInvalidReceipt($provider, $payload, $payloadHash);
            Log::warning("{$provider} webhook validation failed", [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return response('OK', 200);
        }

        $events = $gateway->parseWebhook($payload, $headers);

        $handled = [];
        $failed = [];

        foreach ($events as $event) {
            $startedAt = microtime(true);
            $eventCode = $event['event_code'] ?? '';
            $eventId = (string) ($event['event_id'] ?? ('missing:' . $payloadHash));
            $merchantReference = $event['merchant_reference'] ?? null;
            $providerReference = $event['provider_reference'] ?? null;
            $resourceReference = $event['resource_reference'] ?? null;
            $receipt = null;

            // Event-level dedup: record each provider event id exactly once.
            $eventKey = "webhook_event:{$provider}:{$eventId}";
            try {
                $receipt = UrbanGoodzWebhookEvent::create([
                    'provider' => $provider,
                    'event_id' => $eventId,
                    'event_type' => $eventCode,
                    'payment_intent_id' => $provider === 'stripe' ? $providerReference : null,
                    'charge_id' => $provider === 'stripe' && str_starts_with($eventCode, 'charge.')
                        ? $resourceReference
                        : null,
                    'internal_reference' => $merchantReference,
                    'idempotency_key' => $eventKey,
                    'processing_status' => 'received',
                    'signature_valid' => true,
                    'payload_hash' => $payloadHash,
                    'received_at' => now(),
                ]);
            } catch (QueryException $e) {
                $isDup = ($e->errorInfo[1] ?? null) === 1062 || $e->getCode() === '23000';
                if (! $isDup) {
                    throw $e;
                }

                $existingReceipt = UrbanGoodzWebhookEvent::where('provider', $provider)
                    ->where('event_id', $eventId)
                    ->firstOrFail();
                $existingReceipt->increment('duplicate_count');
                $existingReceipt->update(['last_duplicate_at' => now()]);

                Log::info('stripe_webhook_already_processed', [
                    'provider' => $provider,
                    'event_hash' => hash('sha256', $eventId),
                    'result' => 'duplicate',
                ]);
                $handled[] = ['event' => $eventCode, 'result' => 'duplicate'];
                continue;
            }

            $requestModel = $this->findRequestByReference($merchantReference, $providerReference);

            if (! $requestModel) {
                $receipt->update([
                    'processing_status' => 'unmatched',
                    'failure_type' => 'unknown_payment',
                    'processing_latency_ms' => $this->elapsedMilliseconds($startedAt),
                    'processed_at' => now(),
                ]);
                Log::warning("{$provider} webhook: no matching OrderAnywhereRequest found", [
                    'merchant_reference_hash' => hash('sha256', (string) $merchantReference),
                    'provider_reference_hash' => hash('sha256', (string) $providerReference),
                    'event_code' => $eventCode,
                ]);
                $failed[] = ['event' => $eventCode, 'reason' => 'unmatched'];
                continue;
            }

            $receipt->update([
                'payable_type' => OrderAnywhereRequest::class,
                'payable_id' => $requestModel->id,
            ]);

            // Webhook idempotency protection: check if event has already been recorded in payment ledgers
            $eventType = $this->mapWebhookEventToLedgerType($eventCode, $provider, (bool) ($event['success'] ?? false));
            if ($eventType) {
                $existingLedger = $eventId
                    ? \App\Models\UrbanGoodzPaymentLedger::where('idempotency_key', "webhook:{$provider}:{$eventId}")->first()
                    : \App\Models\UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
                        ->where('payable_id', $requestModel->id)
                        ->where('event_type', $eventType)
                        ->where('reference', $providerReference)
                        ->first();
                if ($existingLedger) {
                    $receipt->update([
                        'processing_status' => 'already_processed',
                        'processing_latency_ms' => $this->elapsedMilliseconds($startedAt),
                        'processed_at' => now(),
                    ]);
                    Log::info('stripe_webhook_already_processed', [
                        'provider' => $provider,
                        'event_hash' => hash('sha256', $eventId),
                        'result' => 'legacy_ledger_match',
                    ]);
                    $handled[] = ['event' => $eventCode, 'result' => 'already_processed'];
                    continue;
                }
            }

            try {
                $result = $this->processEvent($event, $requestModel, $gateway, $payments);
            } catch (PaymentFinalizationConflictException $e) {
                $receipt->update([
                    'processing_status' => 'failed',
                    'failure_type' => 'payment_identity_conflict',
                    'processing_latency_ms' => $this->elapsedMilliseconds($startedAt),
                    'processed_at' => now(),
                ]);
                Log::warning('Stripe webhook payment identity conflict', [
                    'provider' => $provider,
                    'event_hash' => hash('sha256', $eventId),
                    'internal_reference_hash' => hash('sha256', (string) $merchantReference),
                ]);
                $failed[] = ['event' => $eventCode, 'reason' => 'payment_identity_conflict'];
                continue;
            }

            if ($result === 'unhandled') {
                $receipt->update([
                    'processing_status' => 'failed',
                    'failure_type' => 'unhandled_event_type',
                    'processing_latency_ms' => $this->elapsedMilliseconds($startedAt),
                    'processed_at' => now(),
                ]);
                $failed[] = ['event' => $eventCode, 'reason' => 'unhandled_code'];
                Log::info("{$provider} webhook: unhandled event code", ['event_code' => $eventCode]);
            } else {
                $receipt->update([
                    'processing_status' => $result,
                    'processing_latency_ms' => $this->elapsedMilliseconds($startedAt),
                    'processed_at' => now(),
                ]);
                $handled[] = ['event' => $eventCode, 'result' => $result];
            }
        }

        Log::info("{$provider} webhook batch processed", [
            'handled' => $handled,
            'failed' => $failed,
            'total' => count($events),
        ]);

        return response('OK', 200);
    }

    private function mapWebhookEventToLedgerType(string $eventCode, string $provider, bool $success): ?string
    {
        if ($provider === 'adyen' || $provider === 'staged_test') {
            return match ($eventCode) {
                'AUTHORISATION' => $success ? 'authorization' : 'authorization_failed',
                'CAPTURE' => $success ? 'capture' : 'capture_failed',
                'REFUND' => $success ? 'refund' : 'refund_failed',
                'CANCEL_OR_REFUND' => $success ? 'refund' : null,
                'CAPTURE_FAILED' => 'capture_failed',
                'REFUND_FAILED' => 'refund_failed',
                default => null,
            };
        }

        if ($provider === 'stripe') {
            return match ($eventCode) {
                'checkout.session.completed',
                'payment_intent.succeeded',
                'charge.succeeded' => $success ? 'capture' : 'payment_failed',
                'charge.refunded',
                'refund.succeeded' => 'refund',
                'payment_intent.payment_failed',
                'charge.failed' => 'payment_failed',
                'refund.failed' => 'refund_failed',
                default => null,
            };
        }

        return null;
    }

    private function extractPayload(Request $request, string $provider): array|string
    {
        return match ($provider) {
            'stripe' => $request->getContent(),
            default => $request->all(),
        };
    }

    private function extractHeaders(Request $request, string $provider): array
    {
        return match ($provider) {
            'stripe' => [
                'stripe-signature' => $request->header('stripe-signature', ''),
            ],
            default => [],
        };
    }

    private function processEvent(array $event, OrderAnywhereRequest $requestModel, PaymentGatewayInterface $gateway, UrbanGoodzPaymentService $payments): string
    {
        $provider = $gateway->providerName();
        $eventCode = $event['event_code'] ?? '';
        $success = $event['success'] ?? false;
        $providerReference = $event['provider_reference'] ?? null;
        $amountMinor = $event['amount_minor'] ?? 0;
        $currency = $event['currency'] ?? 'USD';
        $amount = $this->fromMinorUnits($amountMinor, $currency);

        $requestModel->logPaymentEvent("webhook.{$eventCode}", $amount, $providerReference, [
            'source' => 'webhook',
            'provider' => $provider,
            'success' => $success,
        ]);

        $handled = match ($provider) {
            'adyen', 'staged_test' => $this->processAdyenEvent($eventCode, $success, $providerReference, $amount, $currency, $provider, $requestModel, $payments),
            'stripe' => $this->processStripeEvent(
                $eventCode,
                $success,
                $providerReference,
                $amount,
                $currency,
                $requestModel,
                $payments,
                $event['event_id'] ?? null,
                $event['resource_reference'] ?? null,
                $event['merchant_reference'] ?? null
            ),
            default => false,
        };

        if ($handled instanceof PaymentFinalizationResult) {
            return $handled->alreadyProcessed ? 'already_processed' : 'processed';
        }

        return $handled ? 'handled' : 'unhandled';
    }

    private function processAdyenEvent(
        string $eventCode,
        bool $success,
        ?string $pspReference,
        float $amount,
        string $currency,
        string $provider,
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments
    ): bool {
        return match ($eventCode) {
            'AUTHORISATION' => $success
                ? (bool) $payments->authorizeCustomerPayment($request, [
                    'authorized_amount' => $amount,
                    'authorization_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, $payments, 'authorization_failed', $pspReference, $amount, $provider),
            'CAPTURE' => $success
                ? (bool) $this->captureFromWebhook($request, $payments, $amount, $pspReference)->request
                : $this->markFailed($request, $payments, 'capture_failed', $pspReference, $amount, $provider),
            'CAPTURE_FAILED' => $this->markFailed($request, $payments, 'capture_failed', $pspReference, $amount, $provider),
            'REFUND' => $success
                ? (bool) $payments->refundCustomerPayment($request, [
                    'refund_amount' => $amount,
                    'refund_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, $payments, 'refund_failed', $pspReference, $amount, $provider),
            'REFUND_FAILED' => $this->markFailed($request, $payments, 'refund_failed', $pspReference, $amount, $provider),
            'CANCELLATION' => $success && in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])
                ? (bool) $request->transitionTo('cancelled')
                : false,
            'CANCEL_OR_REFUND' => $success && $request->payment_status === 'captured'
                ? (bool) $payments->refundCustomerPayment($request, [
                    'refund_amount' => $amount,
                    'refund_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : ($success ? (bool) $request->transitionTo('cancelled') : false),
            default => false,
        };
    }

    private function processStripeEvent(
        string $eventCode,
        bool $success,
        ?string $providerReference,
        float $amount,
        string $currency,
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments,
        ?string $eventId,
        ?string $resourceReference,
        ?string $internalReference
    ): bool|PaymentFinalizationResult {
        return match ($eventCode) {
            'checkout.session.completed',
            'payment_intent.succeeded',
            'charge.succeeded' => $this->captureFromWebhook(
                $request,
                $payments,
                $amount,
                $providerReference,
                $eventId ? "webhook:stripe:{$eventId}" : null,
                $providerReference,
                str_starts_with($eventCode, 'charge.') ? $resourceReference : null,
                $internalReference
            ),
            'payment_intent.payment_failed',
            'charge.failed' => $this->markFailed($request, $payments, 'payment_failed', $providerReference, $amount, 'stripe'),
            'payment_intent.canceled' => in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])
                ? (bool) $request->transitionTo('cancelled')
                : false,
            'charge.refunded',
            'refund.succeeded' => $this->refundFromStripeWebhook(
                $eventCode,
                $request,
                $payments,
                $amount,
                $providerReference,
                $eventId,
                $resourceReference
            ),
            'refund.failed' => $this->markFailed($request, $payments, 'refund_failed', $providerReference, $amount, 'stripe'),
            'charge.dispute.created' => (bool) $request->update([
                'payment_status' => 'disputed',
            ]),
            default => false,
        };
    }

    private function captureFromWebhook(
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments,
        float $amount,
        ?string $providerReference,
        ?string $idempotencyKey = null,
        ?string $paymentIntentId = null,
        ?string $chargeId = null,
        ?string $internalReference = null
    ): PaymentFinalizationResult {
        return $payments->finalizeCustomerPayment($request, [
            'captured_amount' => $amount,
            'capture_reference' => $providerReference,
            'psp_reference' => $providerReference,
            'payment_intent_id' => $paymentIntentId ?? $providerReference,
            'charge_id' => $chargeId,
            'internal_reference' => $internalReference,
            'source' => 'webhook',
            'capture_idempotency_key' => $idempotencyKey,
        ]);
    }

    private function refundFromStripeWebhook(
        string $eventCode,
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments,
        float $eventAmount,
        ?string $providerReference,
        ?string $eventId,
        ?string $resourceReference
    ): bool {
        if ($request->payment_status === 'refunded'
            || ($eventCode === 'refund.succeeded'
                && $resourceReference
                && $request->refund_reference === $resourceReference)) {
            return true;
        }

        $alreadyRefunded = (float) $request->refunded_amount;
        $amount = $eventCode === 'charge.refunded'
            ? $eventAmount - $alreadyRefunded
            : $eventAmount;

        if ($amount <= 0.001) {
            return true;
        }

        $payments->refundCustomerPayment($request, [
            'refund_amount' => $amount,
            'refund_reference' => $resourceReference ?? $providerReference,
            'refund_idempotency_key' => $eventId ? "webhook:stripe:{$eventId}" : null,
            'psp_reference' => $providerReference,
            'source' => 'webhook',
        ]);

        return true;
    }

    private function markFailed(
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments,
        string $status,
        ?string $reference,
        float $amount,
        string $provider
    ): bool {
        $payments->recordWebhookFailure($request, $status, [
            'provider' => $provider,
            'reference' => $reference,
            'amount' => $amount,
        ]);

        return true;
    }

    private function findRequestByReference(?string $merchantReference, ?string $providerReference): ?OrderAnywhereRequest
    {
        if (! $merchantReference && ! $providerReference) {
            return null;
        }

        $query = OrderAnywhereRequest::query();

        if ($merchantReference) {
            $query->where(function ($q) use ($merchantReference) {
                $q->where('request_number', $merchantReference)
                  ->orWhere('merchant_reference', $merchantReference);
            });
        }

        if ($providerReference) {
            $query->orWhere(function ($q) use ($providerReference) {
                $q->where('psp_reference', $providerReference)
                  ->orWhere('payment_link_id', $providerReference)
                  ->orWhere('authorization_reference', $providerReference)
                  ->orWhere('capture_reference', $providerReference)
                  ->orWhere('refund_reference', $providerReference);
            });
        }

        return $query->first();
    }

    private function recordInvalidReceipt(string $provider, array|string $payload, string $payloadHash): void
    {
        $decoded = is_string($payload) ? json_decode($payload, true) : $payload;
        $decoded = is_array($decoded) ? $decoded : [];
        $object = is_array($decoded['data']['object'] ?? null) ? $decoded['data']['object'] : [];
        $eventType = (string) ($decoded['type'] ?? 'invalid_payload');
        $eventId = (string) ($decoded['id'] ?? ('invalid:' . $payloadHash));
        $paymentIntentId = $object['payment_intent']
            ?? (str_starts_with($eventType, 'payment_intent.') ? ($object['id'] ?? null) : null);
        $chargeId = str_starts_with($eventType, 'charge.') ? ($object['id'] ?? null) : null;
        $internalReference = $object['metadata']['merchant_reference'] ?? $object['client_reference_id'] ?? null;

        $receipt = UrbanGoodzWebhookEvent::firstOrCreate(
            [
                'provider' => $provider,
                'event_id' => $eventId,
            ],
            [
                'event_type' => $eventType,
                'payment_intent_id' => $paymentIntentId,
                'charge_id' => $chargeId,
                'internal_reference' => $internalReference,
                'idempotency_key' => "webhook_event:{$provider}:{$eventId}",
                'processing_status' => 'failed',
                'signature_valid' => false,
                'failure_type' => 'invalid_signature',
                'payload_hash' => $payloadHash,
                'received_at' => now(),
                'processed_at' => now(),
                'processing_latency_ms' => 0,
            ]
        );

        if (! $receipt->wasRecentlyCreated) {
            $receipt->increment('duplicate_count');
            $receipt->update(['last_duplicate_at' => now()]);
        }
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    private function fromMinorUnits(int $amountMinor, string $currency): float
    {
        $minorUnits = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KRW' => 0];
        $exponent = $minorUnits[strtoupper($currency)] ?? 2;

        return $amountMinor / pow(10, $exponent);
    }
}
