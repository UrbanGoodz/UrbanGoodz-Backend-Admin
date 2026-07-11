<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\TotpService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class TwoFactorLoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:admin');
    }

    public function showVerify()
    {
        if (!session('pending_2fa_admin_id')) {
            return redirect()->route('login', ['admin']);
        }

        return view('admin-views.two-factor.verify');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'otp_code' => 'required|string|size:6',
        ]);

        $adminId = session('pending_2fa_admin_id');
        if (!$adminId) {
            return redirect()->route('login', ['admin']);
        }

        $admin = Admin::find($adminId);
        if (!$admin) {
            return redirect()->route('login', ['admin']);
        }

        $secret = Crypt::decryptString($admin->two_factor_secret);

        if (TotpService::verify($secret, $request->otp_code)) {
            session()->forget('pending_2fa_admin_id');
            session(['tfa_verified' => true]);
            Auth::guard('admin')->login($admin);

            if ($admin->role_id == 1) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('admin.business-settings.business-setup');
        }

        Toastr::error(translate('Invalid verification code. Please try again.'));
        return back();
    }

    public function showRecoveryVerify()
    {
        if (!session('pending_2fa_admin_id')) {
            return redirect()->route('login', ['admin']);
        }

        return view('admin-views.two-factor.verify-recovery');
    }

    public function verifyRecovery(Request $request)
    {
        $request->validate([
            'recovery_code' => 'required|string',
        ]);

        $adminId = session('pending_2fa_admin_id');
        if (!$adminId) {
            return redirect()->route('login', ['admin']);
        }

        $admin = Admin::find($adminId);
        if (!$admin || !$admin->two_factor_recovery_codes) {
            return redirect()->route('login', ['admin']);
        }

        $remaining = TotpService::verifyRecoveryCode($admin->two_factor_recovery_codes, $request->recovery_code);

        if ($remaining !== null) {
            $admin->two_factor_recovery_codes = $remaining;
            $admin->save();

            session()->forget('pending_2fa_admin_id');
            session(['tfa_verified' => true]);
            Auth::guard('admin')->login($admin);

            if ($admin->role_id == 1) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('admin.business-settings.business-setup');
        }

        Toastr::error(translate('Invalid recovery code.'));
        return back();
    }
}
