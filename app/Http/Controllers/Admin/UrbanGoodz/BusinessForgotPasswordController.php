<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Brian2694\Toastr\Facades\Toastr;

class BusinessForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('business.auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $throttleKey = strtolower($request->email);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('We have sent too many reset links. Please try again later.'),
            ]);
        }

        $response = Password::broker('business_clients')->sendResetLink(
            $request->only('email')
        );

        if ($response === Password::RESET_LINK_SENT) {
            RateLimiter::clear($throttleKey);
            return redirect()->route('business.login')
                ->with('status', 'Password reset link sent to your email.');
        }

        throw ValidationException::withMessages([
            'email' => [trans($response)],
        ]);
    }
}
