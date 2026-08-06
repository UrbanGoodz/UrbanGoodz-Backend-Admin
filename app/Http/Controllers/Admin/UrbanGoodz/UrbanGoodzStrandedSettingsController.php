<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Administrator controls for Urban Goodz Stranded pricing and dispatch.
 *
 * Money is entered in dollars because that is what an administrator thinks
 * in, and stored in minor units because that is what the rest of the platform
 * uses. The conversion happens here and nowhere else.
 */
class UrbanGoodzStrandedSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin-views.urban-goodz.stranded.settings', [
            'feeEnabled' => UrbanGoodzStrandedSettings::feeEnabled(),
            'feeMinor' => (int) UrbanGoodzStrandedSettings::raw(UrbanGoodzStrandedSettings::KEY_FEE_MINOR),
            'memberFeeMinor' => (int) UrbanGoodzStrandedSettings::raw(UrbanGoodzStrandedSettings::KEY_MEMBER_FEE_MINOR),
            'priorityUpgradeMinor' => UrbanGoodzStrandedSettings::priorityUpgradeMinor(),
            'providerCommissionBps' => UrbanGoodzStrandedSettings::providerCommissionBps(),
            'radiusLadder' => implode(', ', UrbanGoodzStrandedSettings::radiusLadder()),
            'offerTtlSeconds' => UrbanGoodzStrandedSettings::offerTtlSeconds(),
            'escalationMinutes' => UrbanGoodzStrandedSettings::escalationMinutes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'help_request_fee' => 'required|numeric|min:0|max:1000',
            'member_help_request_fee' => 'required|numeric|min:0|max:1000',
            'priority_upgrade' => 'required|numeric|min:0|max:1000',
            'provider_commission_percent' => 'required|numeric|min:0|max:100',
            'radius_ladder' => ['required', 'string', 'max:60', 'regex:/^\s*\d+\s*(,\s*\d+\s*)*$/'],
            'offer_ttl_seconds' => 'required|integer|min:5|max:600',
            'escalation_minutes' => 'required|integer|min:1|max:1440',
        ], [
            'radius_ladder.regex' => 'Broadcast radius must be a comma-separated list of whole numbers, for example 10, 15, 20, 25.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // The member fee is meant to be a discount. Silently accepting a
        // higher one would quietly punish the people paying for membership.
        if ((float) $request->input('member_help_request_fee') > (float) $request->input('help_request_fee')) {
            return back()
                ->withErrors(['member_help_request_fee' => 'The Urban Goodz+ fee cannot be higher than the standard fee.'])
                ->withInput();
        }

        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_FEE_ENABLED,
            $request->boolean('help_request_fee_enabled')
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_FEE_MINOR,
            $this->toMinor($request->input('help_request_fee'))
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_MEMBER_FEE_MINOR,
            $this->toMinor($request->input('member_help_request_fee'))
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_PRIORITY_UPGRADE_MINOR,
            $this->toMinor($request->input('priority_upgrade'))
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_PROVIDER_COMMISSION_BPS,
            (int) round(((float) $request->input('provider_commission_percent')) * 100)
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_RADIUS_LADDER,
            preg_replace('/\s+/', '', (string) $request->input('radius_ladder'))
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_OFFER_TTL_SECONDS,
            (int) $request->input('offer_ttl_seconds')
        );
        UrbanGoodzStrandedSettings::put(
            UrbanGoodzStrandedSettings::KEY_ESCALATION_MINUTES,
            (int) $request->input('escalation_minutes')
        );

        return back()->with('success', 'Stranded settings updated.');
    }

    /** Dollars in, minor units out. Rounded, never truncated. */
    private function toMinor(mixed $dollars): int
    {
        return max(0, (int) round(((float) $dollars) * 100));
    }
}
