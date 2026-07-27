<?php

namespace App\Observers;

use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\UrbanGoodzPaymentTransaction;

class UrbanGoodzPaymentTransactionObserver
{
    public function created(UrbanGoodzPaymentTransaction $transaction): void
    {
        $this->broadcast($transaction);
    }

    public function updated(UrbanGoodzPaymentTransaction $transaction): void
    {
        if ($transaction->wasChanged(['internal_status', 'provider_status'])) {
            $this->broadcast($transaction);
        }
    }

    private function broadcast(UrbanGoodzPaymentTransaction $transaction): void
    {
        $status = (string) ($transaction->internal_status ?: $transaction->provider_status ?: 'pending');
        $accountType = $this->accountType($transaction->payable_type);

        if ($accountType !== null && (int) $transaction->payable_id > 0) {
            event(
                UrbanGoodzRealtimeUpdate::paymentStatus(
                    $accountType,
                    (int) $transaction->payable_id,
                    (int) $transaction->id,
                    $status
                )
            );
        }

        event(
            UrbanGoodzRealtimeUpdate::adminOperation(
                'payment_transaction',
                (int) $transaction->id,
                $status
            )
        );
    }

    private function accountType(?string $payableType): ?string
    {
        return match (class_basename((string) $payableType)) {
            'User' => 'shopper',
            'Vendor' => 'vendor',
            'DeliveryMan' => 'driver',
            'UrbanGoodzBusinessClient' => 'business',
            'UrbanGoodzBusinessClientUser' => 'dispatcher',
            'Admin' => 'admin',
            default => null,
        };
    }
}
