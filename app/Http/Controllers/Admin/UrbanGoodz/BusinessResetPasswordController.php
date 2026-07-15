<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Brian2694\Toastr\Facades\Toastr;

class BusinessResetPasswordController extends Controller
{
    public function showResetForm(Request $request, $token)
    {
        $email = DB::table('password_resets')
            ->where('token', $token)
            ->where('created_at', '>=', now()->subMinutes(60))
            ->value('email');

        if (!$email) {
            Toastr::error(translate('Password reset link has expired.'));
            return redirect()->route('business.login');
        }

        return view('business.auth.reset-password', compact('token', 'email'));
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resetRecord = DB::table('password_resets')
            ->where('token', $request->token)
            ->where('email', $request->email)
            ->where('created_at', '>=', now()->subMinutes(60))
            ->first();

        if (!$resetRecord) {
            Toastr::error(translate('Password reset link has expired or is invalid.'));
            return back();
        }

        $user = \App\Models\UrbanGoodzBusinessClientUser::where('email', $request->email)->first();
        if (!$user) {
            Toastr::error(translate('No account found with this email.'));
            return back();
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_resets')
            ->where('token', $request->token)
            ->delete();

        Toastr::success(translate('Password reset successfully. You can now log in.'));
        return redirect()->route('business.login');
    }
}
