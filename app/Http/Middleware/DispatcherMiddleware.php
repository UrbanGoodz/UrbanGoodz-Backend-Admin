<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DispatcherMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('business')->user();

        if (!$user) {
            return redirect()->route('business.login');
        }

        if (!$user->is_active || $user->status !== 'active') {
            auth('business')->logout();
            return redirect()->route('business.login')->with('error', 'Account is inactive.');
        }

        if (!$user->client || $user->client->status !== 'approved') {
            auth('business')->logout();
            return redirect()->route('business.login')->with('error', 'Account not approved.');
        }

        if (!$user->client->isDispatchCompany()) {
            abort(403, 'Access denied. This portal is for dispatch companies only.');
        }

        if (!$user->isDispatchRole()) {
            abort(403, 'Access denied. You do not have dispatcher permissions.');
        }

        return $next($request);
    }
}
