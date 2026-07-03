<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryManWebMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('delivery_men')->check()) {
            return $next($request);
        }

        return redirect('authentication-failed');
    }
}
