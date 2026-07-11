<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\TotpService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index()
    {
        $admin = Auth::guard('admin')->user();
        return view('admin-views.two-factor.index', compact('admin'));
    }

    public function showSetup(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->two_factor_enabled) {
            return redirect()->route('admin.two-factor.index');
        }

        $secret = TotpService::generateSecret();
        $admin->two_factor_secret = Crypt::encryptString($secret);
        $admin->save();

        $qrCodeUri = TotpService::getUri($secret, $admin->email);
        $recoveryCodes = TotpService::generateRecoveryCodes();

        session([
            'tfa_setup_secret' => $secret,
            'tfa_setup_recovery_codes' => $recoveryCodes,
        ]);

        return view('admin-views.two-factor.setup', compact('admin', 'qrCodeUri', 'recoveryCodes', 'secret'));
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'otp_code' => 'required|string|size:6',
        ]);

        $admin = Auth::guard('admin')->user();
        $secret = session('tfa_setup_secret');

        if (!$secret) {
            return redirect()->route('admin.two-factor.show-setup');
        }

        if (!TotpService::verify($secret, $request->otp_code)) {
            Toastr::error(translate('Invalid verification code. Please try again.'));
            return back();
        }

        $recoveryCodes = session('tfa_setup_recovery_codes', []);

        $admin->two_factor_secret = Crypt::encryptString($secret);
        $admin->two_factor_recovery_codes = TotpService::hashRecoveryCodes($recoveryCodes);
        $admin->two_factor_enabled = true;
        $admin->two_factor_confirmed_at = now();
        $admin->save();

        session()->forget(['tfa_setup_secret', 'tfa_setup_recovery_codes']);

        Toastr::success(translate('Two-factor authentication has been enabled.'));
        return view('admin-views.two-factor.recovery-codes', compact('recoveryCodes'));
    }

    public function showDisable(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin->two_factor_enabled) {
            return redirect()->route('admin.two-factor.index');
        }

        return view('admin-views.two-factor.disable', compact('admin'));
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!Hash::check($request->password, $admin->password)) {
            Toastr::error(translate('Incorrect password.'));
            return back();
        }

        $admin->two_factor_secret = null;
        $admin->two_factor_recovery_codes = null;
        $admin->two_factor_enabled = false;
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        Toastr::success(translate('Two-factor authentication has been disabled.'));
        return redirect()->route('admin.two-factor.index');
    }

    public function showRecoveryCodes()
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin->two_factor_enabled) {
            return redirect()->route('admin.two-factor.index');
        }

        return view('admin-views.two-factor.recovery-codes-view', compact('admin'));
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!Hash::check($request->password, $admin->password)) {
            Toastr::error(translate('Incorrect password.'));
            return back();
        }

        $recoveryCodes = TotpService::generateRecoveryCodes();
        $admin->two_factor_recovery_codes = TotpService::hashRecoveryCodes($recoveryCodes);
        $admin->save();

        Toastr::success(translate('Recovery codes have been regenerated.'));
        return view('admin-views.two-factor.recovery-codes', compact('recoveryCodes'));
    }
}
