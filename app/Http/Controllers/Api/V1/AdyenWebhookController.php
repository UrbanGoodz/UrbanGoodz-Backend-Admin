<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Services\AdyenPaymentGateway;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AdyenWebhookController extends Controller
{
    public function handle(Request $request, AdyenPaymentGateway $gateway, UrbanGoodzPaymentService $payments): Response
    {
        $payload = $request->all();

        if (! isset($payload['notificationItems'])) {
            Log::warning('Adyen webhook received without notificationItems');

            return response('OK', 200);
        }

        $handled = [];
        $failed = [];

        foreach ($payload['notificationItems'] as $item) {
            $notification = $item['NotificationRequestItem'] ?? $item;

            if (! $gateway->validateWebhook($notification)) {
                Log::warning('Adyen webhook HMAC validation failed', [
                    'psp_reference' => $notification['pspReference'] ?? null,
                ]);

                $failed[] = ['event' => 'unknown', 'reason' => 'hmac_failed'];

                continue;
            }

            $eventCode = $notification['eventCode'] ?? '';
            $success = ($notification['success'] ?? 'false') === 'true';
            $pspReference = $notification['pspReference'] ?? null;
            $merchantReference = $notification['merchantReference'] ?? null;
            $amount = (float) (($notification['amount']['value'] ?? 0) / 100);
            $currency = $notification['amount']['currency'] ?? 'USD';

            Log::info('Adyen webhook event received', [
                'event_code' => $eventCode,
                'success' => $success,
                'psp_reference' => $pspReference,
                'merchant_reference' => $merchantReference,
                'amount' => $amount,
            ]);

            $result = $this->processEvent($eventCode, $success, $pspReference, $merchantReference, $amount, $currency, $notification, $payments);

            if ($result === 'unhandled') {
                $failed[] = ['event' => $eventCode, 'reason' => 'unhandled_code'];
                Log::info('Adyen webhook: unhandled event code', ['event_code' => $eventCode]);
            } else {
                $handled[] = $eventCode;
            }
        }

        Log::info('Adyen webhook batch processed', [
            'handled' => $handled,
            'failed' => $failed,
            'total' => count($payload['notificationItems']),
        ]);

        return response('OK', 200);
    }

    private function processEvent(
        string $eventCode,
        bool $success,
        ?string $pspReference,
        ?string $merchantReference,
        float $amount,
        string $currency,
        array $notification,
        UrbanGoodzPaymentService $payments
    ): string {
        $request = $this->findRequestByReference($merchantReference);

        if (! $request) {
            Log::warning('Adyen webhook: no matching OrderAnywhereRequest found', [
                'merchant_reference' => $merchantReference,
                'psp_reference' => $pspReference,
                'event_code' => $eventCode,
            ]);

            return 'unmatched';
        }

        match ($eventCode) {
            'AUTHORISATION' => $this->handleAuthorization($request, $success, $pspReference, $amount, $payments),
            'CAPTURE' => $this->handleCapture($request, $success, $pspReference, $amount, $payments),
            'CAPTURE_FAILED' => $this->handleCaptureFailed($request, $pspReference, $notification),
            'REFUND' => $this->handleRefund($request, $success, $pspReference, $amount, $payments),
            'REFUND_FAILED' => $this->handleRefundFailed($request, $pspReference, $notification),
            'CANCELLATION' => $this->handleCancellation($request, $success, $pspReference),
            'CANCEL_OR_REFUND' => $this->handleCancelOrRefund($request, $success, $pspReference, $amount, $payments),
            default => 'unhandled',
        };

        return 'handled';
    }

    private function handleAuthorization(OrderAnywhereRequest $request, bool $success, ?string $pspReference, float $amount, UrbanGoodzPaymentService $payments): void
    {
        if (! $success) {
            $request->update([
                'payment_status' => 'authorization_failed',
                'metadata' => array_merge($request->metadata ?? [], [
                    'authorization_failed_at' => now()->toISOString(),
                    'psp_reference' => $pspReference,
                ]),
            ]);
            $request->logPaymentEvent('authorization_failed', $amount, $pspReference, ['source' => 'webhook']);
            $payments->recordWebhookFailure($request, 'adyen_authorization_failed', [
                'provider_reference' => $pspReference,
                'amount' => $amount,
            ]);

            return;
        }

        $payments->authorizeOrderAnywhere($request, [
            'authorized_amount' => $amount,
            'authorization_reference' => $pspReference,
            'psp_reference' => $pspReference,
            'source' => 'webhook',
        ]);
    }

    private function handleCapture(OrderAnywhereRequest $request, bool $success, ?string $pspReference, float $amount, UrbanGoodzPaymentService $payments): void
    {
        if (! $success) {
            $request->update([
                'payment_status' => 'capture_failed',
                'metadata' => array_merge($request->metadata ?? [], [
                    'capture_failed_at' => now()->toISOString(),
                    'psp_reference' => $pspReference,
                ]),
            ]);
            $request->logPaymentEvent('capture_failed', $amount, $pspReference, ['source' => 'webhook']);

            return;
        }

        $payments->captureOrderAnywhere($request, [
            'captured_amount' => $amount,
            'capture_reference' => $pspReference,
            'psp_reference' => $pspReference,
            'source' => 'webhook',
        ]);
    }

    private function handleCaptureFailed(OrderAnywhereRequest $request, ?string $pspReference, array $notification): void
    {
        $request->update([
            'payment_status' => 'capture_failed',
            'metadata' => array_merge($request->metadata ?? [], [
                'capture_failed_at' => now()->toISOString(),
                'failure_reason' => $notification['reason'] ?? null,
                'psp_reference' => $pspReference,
            ]),
        ]);
        $request->logPaymentEvent('capture_failed', 0, $pspReference, [
            'source' => 'webhook',
            'reason' => $notification['reason'] ?? null,
        ]);
        app(UrbanGoodzPaymentService::class)->recordWebhookFailure($request, 'adyen_capture_failed', [
            'provider_reference' => $pspReference,
            'reason' => $notification['reason'] ?? null,
        ]);
    }

    private function handleRefund(OrderAnywhereRequest $request, bool $success, ?string $pspReference, float $amount, UrbanGoodzPaymentService $payments): void
    {
        if (! $success) {
            $request->update([
                'metadata' => array_merge($request->metadata ?? [], [
                    'refund_failed_at' => now()->toISOString(),
                    'psp_reference' => $pspReference,
                ]),
            ]);
            $request->logPaymentEvent('refund_failed', $amount, $pspReference, ['source' => 'webhook']);

            return;
        }

        $payments->refundOrderAnywhere($request, [
            'refund_amount' => $amount,
            'refund_reference' => $pspReference,
            'psp_reference' => $pspReference,
            'source' => 'webhook',
        ]);
    }

    private function handleRefundFailed(OrderAnywhereRequest $request, ?string $pspReference, array $notification): void
    {
        $request->update([
            'metadata' => array_merge($request->metadata ?? [], [
                'refund_failed_at' => now()->toISOString(),
                'failure_reason' => $notification['reason'] ?? null,
                'psp_reference' => $pspReference,
            ]),
        ]);
        $request->logPaymentEvent('refund_failed', 0, $pspReference, [
            'source' => 'webhook',
            'reason' => $notification['reason'] ?? null,
        ]);
        app(UrbanGoodzPaymentService::class)->recordWebhookFailure($request, 'adyen_refund_failed', [
            'provider_reference' => $pspReference,
            'reason' => $notification['reason'] ?? null,
        ]);
    }

    private function handleCancellation(OrderAnywhereRequest $request, bool $success, ?string $pspReference): void
    {
        if ($success && in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])) {
            $request->transitionTo('cancelled');
        }
    }

    private function handleCancelOrRefund(OrderAnywhereRequest $request, bool $success, ?string $pspReference, float $amount, UrbanGoodzPaymentService $payments): void
    {
        if (! $success) {
            return;
        }

        if ($request->payment_status === 'captured') {
            $this->handleRefund($request, true, $pspReference, $amount, $payments);
        } else {
            $this->handleCancellation($request, true, $pspReference);
        }
    }

    private function findRequestByReference(?string $reference): ?OrderAnywhereRequest
    {
        if (! $reference) {
            return null;
        }

        return OrderAnywhereRequest::where('request_number', $reference)
            ->orWhere('authorization_reference', $reference)
            ->orWhere('capture_reference', $reference)
            ->orWhere('refund_reference', $reference)
            ->orWhere('merchant_reference', $reference)
            ->orWhere('psp_reference', $reference)
            ->first();
    }
}
