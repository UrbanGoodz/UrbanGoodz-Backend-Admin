<?php

namespace App\Services;

use App\Models\DeliveryMan;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use Illuminate\Support\Facades\DB;

/**
 * Creates payout requests atomically.
 *
 * Every payout path used to read the balance, compare, then insert, with no
 * lock between the read and the write. Two simultaneous requests could both
 * pass the check and together withdraw more than the payee was owed. Here the
 * payee's row is locked for the duration of the transaction and the balance is
 * recomputed inside the lock, so concurrent requests serialize.
 */
class UrbanGoodzPayoutGuard
{
    public const OPEN_STATUSES = ['pending', 'approved', 'processing'];

    /** What a driver can still request: pending earnings minus open payouts. */
    public static function driverAvailable(int $deliveryManId): float
    {
        return round(
            (float) UrbanGoodzDriverEarning::where('delivery_man_id', $deliveryManId)
                ->where('status', 'pending')->sum('amount')
            - (float) UrbanGoodzDriverPayoutRequest::where('delivery_man_id', $deliveryManId)
                ->whereIn('status', self::OPEN_STATUSES)->sum('requested_amount'),
            2
        );
    }

    /**
     * @return UrbanGoodzDriverPayoutRequest|null null when the balance no longer covers the amount
     */
    public static function createDriverPayout(int $deliveryManId, float $amount, array $attributes): ?UrbanGoodzDriverPayoutRequest
    {
        return DB::transaction(function () use ($deliveryManId, $amount, $attributes) {
            // The lock is the serialization point; the row itself is not used.
            DeliveryMan::whereKey($deliveryManId)->lockForUpdate()->first();

            if (self::driverAvailable($deliveryManId) + 0.00001 < $amount) {
                return null;
            }

            return UrbanGoodzDriverPayoutRequest::create($attributes);
        });
    }

    /**
     * @return UrbanGoodzDriverPayoutRequest|string the request, or 'exceeds_balance' | 'request_in_flight' | 'no_wallet'
     */
    public static function createVendorPayout(int $vendorId, float $amount, array $attributes)
    {
        return DB::transaction(function () use ($vendorId, $amount, $attributes) {
            $wallet = StoreWallet::where('vendor_id', $vendorId)->lockForUpdate()->first();

            if (!$wallet) {
                return 'no_wallet';
            }

            $withdrawable = round(
                (float) $wallet->total_earning - ((float) $wallet->total_withdrawn + (float) $wallet->pending_withdraw),
                2
            );

            if ($amount > $withdrawable + 0.00001) {
                return 'exceeds_balance';
            }

            $open = UrbanGoodzDriverPayoutRequest::where('payee_type', UrbanGoodzPayoutSettings::PAYEE_VENDOR)
                ->where('vendor_id', $vendorId)
                ->whereIn('status', self::OPEN_STATUSES)
                ->exists();

            if ($open) {
                return 'request_in_flight';
            }

            $payout = UrbanGoodzDriverPayoutRequest::create($attributes);
            $wallet->increment('pending_withdraw', $amount);

            return $payout;
        });
    }
}
