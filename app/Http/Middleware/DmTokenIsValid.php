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

        // This middleware accepts the token from an Authorization: Bearer header,
        // but 36 of the DeliverymanController actions still resolve the rider with
        // `DeliveryMan::where(['auth_token' => $request['token']])`. With a Bearer
        // header and no `token` field in the body, that lookup returns null, and
        // the null then flows into checks like `$dm->active != 1` - so a perfectly
        // valid driver was told "You can not accept order on offline" and could
        // never accept an order at all.
        //
        // Putting the resolved token where those actions already look fixes every
        // one of them at the source, rather than patching 36 call sites.
        $request->merge(['token' => $token]);

        return $next($request);
    }
}
