<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UrbanGoodzBusinessClient;

class BusinessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('business')->check()) {
            return redirect()->route('business.login');
        }

        $user = auth('business')->user();

        if (!$user->is_active || $user->status !== 'active') {
            auth()->guard('business')->logout();
            return redirect()->route('business.login')
                ->withErrors(['Your account is not active. Please contact support.']);
        }

        $client = $user->client;
        if (!$client || $client->status !== 'approved') {
            auth()->guard('business')->logout();
            return redirect()->route('business.login')
                ->withErrors(['Your company account is not active. Please contact support.']);
        }

        return $next($request);
    }
}
