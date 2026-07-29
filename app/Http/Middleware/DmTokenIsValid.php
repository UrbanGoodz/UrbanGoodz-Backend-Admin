<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;
use App\Models\DeliveryMan;

class DmTokenIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $token = null;

        $bearer = $request->bearerToken();
        if ($bearer) {
            $token = $bearer;
        }

        if (!$token && $request->has('token')) {
            $token = $request->input('token');
        }

        if (!$token && $request->query('token')) {
            $token = $request->query('token');
        }

        if (!is_string($token) || $token === '') {
            return response()->json(['errors' => [
                ['code' => 'unauthorized', 'message' => 'Authentication token required.']
            ]], 401);
        }

        $dm = DeliveryMan::whereNotNull('auth_token')->where('auth_token', $token)->first();
        if (!$dm) {
            return response()->json(['errors' => [
                ['code' => 'unauthorized', 'message' => 'Invalid or expired token.']
            ]], 401);
        }

        // Every DeliverymanController action resolves the driver with
        // DeliveryMan::where(['auth_token' => $request['token']]), so `token`
        // must be present as request input. Before bearer support was added the
        // middleware validated `token` as required input, which guaranteed it.
        // With a bearer-only request the input is absent and Eloquent compiles
        // where(['auth_token' => null]) to `auth_token IS NULL`, which resolves
        // to an unrelated driver instead of failing. Publish the authenticated
        // token so those lookups always resolve to this driver.
        $request->merge(['token' => $token]);

        auth()->guard('delivery_men')->login($dm);
        return $next($request);
    }
}
