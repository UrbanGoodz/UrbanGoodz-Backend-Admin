<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodzPayoutSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Instant payout pricing.
 *
 * Rates are entered as percentages because that is how anyone setting a fee
 * thinks about it, and stored as basis points because that is what avoids
 * rounding drift. The conversion happens here and nowhere else.
 *
 * The page shows worked examples against the entered rates, since a
 * percentage with a minimum and a cap does not obviously translate into what
 * a driver actually loses on an $80 cash-out.
 */
class UrbanGoodzPayoutSettingsController extends Controller
{
    public function index(): View
    {
        $samples = [40, 80, 250, 500, 4000];

        return view('admin-views.urban-goodz.payouts.settings', [
            'enabled' => UrbanGoodzPayoutSettings::instantEnabled(),
            'driverPercent' => (float) UrbanGoodzPayoutSettings::raw('ug_instant_payout_driver_bps') / 100,
            'driverMin' => (float) UrbanGoodzPayoutSettings::raw('ug_instant_payout_driver_min'),
            'driverCap' => (float) UrbanGoodzPayoutSettings::raw('ug_instant_payout_driver_cap'),
            'vendorPercent' => (float) UrbanGoodzPayoutSettings::raw('ug_instant_payout_vendor_bps') / 100,
            'vendorMin' => (float) UrbanGoodzPayoutSettings::raw('ug_instant_payout_vendor_min'),
            'vendorCap' => (float) UrbanGoodzPayoutSettings::raw('ug_instant_payout_vendor_cap'),
            'minimumAmount' => UrbanGoodzPayoutSettings::minimumAmount(),
            'samples' => collect($samples)->map(fn ($amount) => [
                'amount' => $amount,
                'driver' => UrbanGoodzPayoutSettings::quote($amount, UrbanGoodzPayoutSettings::PAYEE_DRIVER),
                'vendor' => UrbanGoodzPayoutSettings::quote($amount, UrbanGoodzPayoutSettings::PAYEE_VENDOR),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'driver_percent' => 'required|numeric|min:0|max:50',
            'driver_min' => 'required|numeric|min:0|max:1000',
            'driver_cap' => 'required|numeric|min:0|max:10000',
            'vendor_percent' => 'required|numeric|min:0|max:50',
            'vendor_min' => 'required|numeric|min:0|max:1000',
            'vendor_cap' => 'required|numeric|min:0|max:10000',
            'minimum_amount' => 'required|numeric|min:0|max:10000',
        ]);

        // A cap below the minimum can never be satisfied: every fee would be
        // raised to the minimum and then cut back to the cap, so the cap would
        // silently become the only rate charged.
        $validator->after(function ($v) use ($request) {
            foreach (['driver', 'vendor'] as $who) {
                $cap = (float) $request->input("{$who}_cap");
                $min = (float) $request->input("{$who}_min");
                if ($cap > 0 && $cap < $min) {
                    $v->errors()->add("{$who}_cap", ucfirst($who) . ' cap cannot be lower than the minimum fee.');
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        UrbanGoodzPayoutSettings::put('ug_instant_payout_enabled', $request->boolean('enabled'));
        UrbanGoodzPayoutSettings::put('ug_instant_payout_minimum_amount', $this->money($request->input('minimum_amount')));

        foreach (['driver', 'vendor'] as $who) {
            UrbanGoodzPayoutSettings::put(
                "ug_instant_payout_{$who}_bps",
                (int) round(((float) $request->input("{$who}_percent")) * 100)
            );
            UrbanGoodzPayoutSettings::put("ug_instant_payout_{$who}_min", $this->money($request->input("{$who}_min")));
            UrbanGoodzPayoutSettings::put("ug_instant_payout_{$who}_cap", $this->money($request->input("{$who}_cap")));
        }

        return back()->with('success', 'Payout settings updated.');
    }

    private function money(mixed $value): string
    {
        return number_format(max(0, (float) $value), 2, '.', '');
    }
}
