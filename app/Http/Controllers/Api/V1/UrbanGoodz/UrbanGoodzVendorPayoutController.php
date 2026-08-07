<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Services\UrbanGoodzPayoutSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Vendor cash-out.
 *
 * The balance is read from `store_wallets`, which is where vendor money
 * actually lives, using the platform's own formula:
 *
 *     total_earning - (total_withdrawn + pending_withdraw)
 *
 * That is lifted from Vendor/WalletController::make_wallet_adjustment rather
 * than invented here, so a vendor sees the same number in this endpoint as
 * they do everywhere else in the platform. Two different definitions of "your
 * balance" is how support tickets start.
 *
 * `collected_cash` is deliberately NOT subtracted. It is cash-on-delivery
 * money already in the vendor's hands, settled through the platform's
 * separate wallet-adjustment flow, not a reduction of what they have earned.
 * It is surfaced alongside the balance so the figure is explainable.
 */
class UrbanGoodzVendorPayoutController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $vendorId = Helpers::get_vendor_id();

        if (!$vendorId) {
            return response()->json(['errors' => [['code' => 'auth', 'message' => 'Unauthorized']]], 401);
        }

        $wallet = StoreWallet::where('vendor_id', $vendorId)->first();

        // No wallet row is a real state -- a vendor who has never earned. It
        // is reported as such rather than as a zero balance, because those
        // mean different things to whoever reads this next.
        if (!$wallet) {
            return response()->json([
                'status' => 'success',
                'has_wallet' => false,
                'message' => 'No earnings recorded for this store yet.',
                'available_balance' => null,
            ]);
        }

        $available = $this->withdrawable($wallet);
        $quote = UrbanGoodzPayoutSettings::quote($available, UrbanGoodzPayoutSettings::PAYEE_VENDOR);

        return response()->json([
            'status' => 'success',
            'has_wallet' => true,
            'available_balance' => $available,
            'currency' => 'USD',
            'breakdown' => [
                'total_earning' => (float) $wallet->total_earning,
                'total_withdrawn' => (float) $wallet->total_withdrawn,
                'pending_withdraw' => (float) $wallet->pending_withdraw,
                // Held by the vendor from COD orders; settled separately.
                'collected_cash' => (float) $wallet->collected_cash,
            ],
            'instant' => [
                'available' => $quote['available'],
                'code' => $quote['code'],
                'message' => $quote['message'],
                'fee' => $quote['fee'],
                'you_receive' => $quote['net'],
                'basis' => $quote['basis'],
            ],
            'weekly' => [
                'available' => $available > 0,
                'fee' => 0.0,
                'you_receive' => $available,
                'message' => 'Free. Paid on the weekly schedule.',
            ],
        ]);
    }

    public function request_(Request $request): JsonResponse
    {
        $vendorId = Helpers::get_vendor_id();

        if (!$vendorId) {
            return response()->json(['errors' => [['code' => 'auth', 'message' => 'Unauthorized']]], 401);
        }

        $validator = Validator::make($request->all(), [
            'payout_type' => 'required|in:instant,weekly',
            'amount' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $wallet = StoreWallet::where('vendor_id', $vendorId)->first();

        if (!$wallet) {
            return response()->json([
                'status' => 'error',
                'code' => 'no_wallet',
                'message' => 'No earnings recorded for this store yet.',
            ], 422);
        }

        $available = $this->withdrawable($wallet);
        $amount = round((float) ($request->input('amount') ?? $available), 2);

        if ($amount <= 0) {
            return response()->json([
                'status' => 'error', 'code' => 'nothing_to_pay',
                'message' => 'There is nothing available to pay out yet.',
            ], 422);
        }

        if ($amount > $available) {
            return response()->json([
                'status' => 'error', 'code' => 'exceeds_balance',
                'message' => 'That is more than your available balance of $' . number_format($available, 2) . '.',
            ], 422);
        }

        $open = UrbanGoodzDriverPayoutRequest::where('payee_type', UrbanGoodzPayoutSettings::PAYEE_VENDOR)
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->exists();

        if ($open) {
            return response()->json([
                'status' => 'error', 'code' => 'request_in_flight',
                'message' => 'You already have a payout being processed.',
            ], 409);
        }

        $instant = $request->input('payout_type') === 'instant';
        $fee = 0.0;
        $basis = null;

        if ($instant) {
            $quote = UrbanGoodzPayoutSettings::quote($amount, UrbanGoodzPayoutSettings::PAYEE_VENDOR);

            if (!$quote['available']) {
                return response()->json([
                    'status' => 'error',
                    'code' => $quote['code'],
                    'message' => $quote['message'],
                    'weekly_alternative' => $quote['weekly_alternative'],
                ], 422);
            }

            $fee = $quote['fee'];
            $basis = $quote['basis'];
        }

        $payout = UrbanGoodzDriverPayoutRequest::create([
            'payee_type' => UrbanGoodzPayoutSettings::PAYEE_VENDOR,
            'vendor_id' => $vendorId,
            'delivery_man_id' => null,
            'payout_type' => $instant ? 'instant' : 'weekly',
            'requested_amount' => $amount,
            'instant_fee' => $fee,
            'fee_percent_bps' => $basis['percent_bps'] ?? 0,
            'fee_minimum' => $basis['minimum'] ?? 0,
            'fee_cap' => $basis['cap'] ?? null,
            'net_amount' => round($amount - $fee, 2),
            'currency' => 'USD',
            'status' => 'pending',
            'driver_notes' => $request->input('notes'),
        ]);

        // Reserve the amount so a second request cannot claim the same money
        // before this one settles. This mirrors what the platform's own
        // withdrawal flow does.
        $wallet->increment('pending_withdraw', $amount);

        return response()->json([
            'status' => 'success',
            'payout' => [
                'id' => $payout->id,
                'type' => $payout->payout_type,
                'requested' => (float) $payout->requested_amount,
                'fee' => (float) $payout->instant_fee,
                'you_receive' => (float) $payout->net_amount,
                'status' => $payout->status,
            ],
        ], 201);
    }

    /**
     * The platform's own definition, not a new one.
     * @see \App\Http\Controllers\Vendor\WalletController::make_wallet_adjustment
     */
    private function withdrawable(StoreWallet $wallet): float
    {
        return round(
            (float) $wallet->total_earning
            - ((float) $wallet->total_withdrawn + (float) $wallet->pending_withdraw),
            2
        );
    }
}
