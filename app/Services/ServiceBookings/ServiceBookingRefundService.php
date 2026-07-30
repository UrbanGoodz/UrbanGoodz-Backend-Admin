<?php

namespace App\Services\ServiceBookings;

use App\Contracts\ServiceBookingPaymentGateway;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzServiceRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceBookingRefundService
{
    public function __construct(private ServiceBookingPaymentGateway $gateway)
    {
    }

    public function refund(UrbanGoodzServiceRequest $booking, int $amountMinor, string $idempotencyKey): int
    {
        $payments = UrbanGoodzPaymentTransaction::query()
            ->where('payable_type', UrbanGoodzServiceRequest::class)
            ->where('payable_id', $booking->id)
            ->where('internal_status', 'succeeded')
            ->whereIn('transaction_type', ['deposit', 'balance', 'full'])
            ->oldest()
            ->get();

        $partKeys = $payments->map(fn ($payment) => $idempotencyKey.'-'.$payment->id);
        $alreadyCompletedForKey = (int) UrbanGoodzPaymentTransaction::query()
            ->whereIn('idempotency_key', $partKeys)
            ->where('transaction_type', 'refund')
            ->where('internal_status', 'succeeded')
            ->sum('amount_minor');
        abort_if($alreadyCompletedForKey > $amountMinor, 409, 'Refund idempotency key was already used for a different amount.');

        $this->synchronizeRefundTotal($booking);
        $booking->refresh();
        if ($alreadyCompletedForKey === $amountMinor && $amountMinor > 0) {
            return $amountMinor;
        }

        $outstanding = $amountMinor - $alreadyCompletedForKey;
        $available = max(0, (int) $booking->amount_paid_minor - (int) $booking->refunded_amount_minor);
        abort_unless($amountMinor > 0 && $outstanding <= $available, 422, 'Refund amount exceeds the refundable balance.');

        $remaining = $amountMinor;
        foreach ($payments as $payment) {
            if ($remaining === 0) {
                break;
            }
            $alreadyRefunded = (int) UrbanGoodzPaymentTransaction::query()
                ->where('parent_transaction_id', $payment->id)
                ->where('transaction_type', 'refund')
                ->where('internal_status', 'succeeded')
                ->sum('amount_minor');
            $paymentAvailable = max(0, (int) $payment->amount_minor - $alreadyRefunded);
            $refundAmount = min($remaining, $paymentAvailable);
            if ($refundAmount === 0) {
                continue;
            }

            $partKey = $idempotencyKey.'-'.$payment->id;
            $existing = UrbanGoodzPaymentTransaction::where('idempotency_key', $partKey)->first();
            if ($existing?->internal_status === 'succeeded') {
                $remaining -= (int) $existing->amount_minor;
                continue;
            }
            if (!$payment->provider_payment_id) {
                throw new RuntimeException('A provider payment ID is required to issue a refund.');
            }

            $result = $this->gateway->refund(
                $payment->provider_payment_id,
                $refundAmount,
                $booking->currency,
                $partKey
            );
            UrbanGoodzPaymentTransaction::updateOrCreate(
                ['idempotency_key' => $partKey],
                [
                    'payable_type' => UrbanGoodzServiceRequest::class,
                    'payable_id' => $booking->id,
                    'provider' => $payment->provider,
                    'environment' => $payment->environment,
                    'transaction_type' => 'refund',
                    'internal_status' => 'succeeded',
                    'provider_status' => $result['status'],
                    'amount_minor' => $refundAmount,
                    'currency' => $booking->currency,
                    'provider_reference' => $result['id'],
                    'provider_payment_id' => $payment->provider_payment_id,
                    'parent_transaction_id' => $payment->id,
                    'processed_at' => now(),
                ]
            );
            $remaining -= $refundAmount;
        }

        if ($remaining !== 0) {
            throw new RuntimeException('The refundable payment records do not cover the requested amount.');
        }

        $this->synchronizeRefundTotal($booking);

        return $amountMinor;
    }

    private function synchronizeRefundTotal(UrbanGoodzServiceRequest $booking): void
    {
        $refunded = (int) UrbanGoodzPaymentTransaction::query()
            ->where('payable_type', UrbanGoodzServiceRequest::class)
            ->where('payable_id', $booking->id)
            ->where('transaction_type', 'refund')
            ->where('internal_status', 'succeeded')
            ->sum('amount_minor');

        DB::transaction(function () use ($booking, $refunded) {
            $locked = UrbanGoodzServiceRequest::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($refunded === 0) {
                return;
            }
            $locked->update([
                'refunded_amount_minor' => $refunded,
                'payment_status' => $refunded >= (int) $locked->amount_paid_minor ? 'refunded' : 'partially_refunded',
            ]);
        });
    }
}
