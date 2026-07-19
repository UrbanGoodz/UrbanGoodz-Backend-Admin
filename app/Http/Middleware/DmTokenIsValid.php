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

        if (!$token) {
            return response()->json(['errors' => [
                ['code' => 'unauthorized', 'message' => 'Authentication token required.']
            ]], 401);
        }

        $dm = DeliveryMan::where('auth_token', $token)->first();
        if (!$dm) {
            return response()->json(['errors' => [
                ['code' => 'unauthorized', 'message' => 'Invalid or expired token.']
            ]], 401);
        }

        auth()->guard('delivery_men')->login($dm);
        return $next($request);
    }
}
