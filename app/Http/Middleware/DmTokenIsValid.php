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

        // A valid token is not enough: login only ever issues tokens to approved,
        // unsuspended drivers, so a driver who has since been suspended or whose
        // application is no longer approved must lose API access immediately,
        // not only when they next try to log in.
        if (! $dm->status) {
            return response()->json(['errors' => [
                ['code' => 'auth-003', 'message' => translate('messages.your_account_has_been_suspended')]
            ]], 401);
        }
        if ($dm->application_status !== 'approved') {
            return response()->json(['errors' => [
                ['code' => 'auth-003', 'message' => translate('messages.Your_account_is_not_approved_yet.')]
            ]], 401);
        }

        // Several legacy controllers re-resolve the driver with
        // DeliveryMan::where(['auth_token' => $request['token']]). When the token
        // arrived as a Bearer header that value is null, which Eloquent turns into
        // "auth_token IS NULL" and silently selects some other logged-out driver.
        // Pin it to the driver this middleware actually authenticated.
        $request->merge(['token' => $dm->auth_token]);

        auth()->guard('delivery_men')->login($dm);
        return $next($request);
    }
}
