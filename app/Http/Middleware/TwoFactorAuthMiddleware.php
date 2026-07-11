<?php

namespace App\Http\Middleware;

use App\Services\TotpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class TwoFactorAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return redirect()->route('login', ['admin']);
        }

        if ($admin->two_factor_enabled && !$admin->two_factor_confirmed_at) {
            Auth::guard('admin')->logout();
            return redirect()->route('login', ['admin'])
                ->withErrors(['Two-factor authentication requires confirmation.']);
        }

        if ($admin->two_factor_enabled && !session('tfa_verified')) {
            return redirect()->route('admin.two-factor.verify');
        }

        return $next($request);
    }
}
