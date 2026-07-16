<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UrbanGoodzImpersonationSession;

class AdminImpersonationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('impersonation_active') && session('impersonation_active') === true) {
            $token = session('impersonation_token');

            $session = UrbanGoodzImpersonationSession::where('session_token', $token)
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->first();

            if ($session) {
                $request->merge(['business_portal_client_id' => $session->business_client_id]);
                $request->attributes->set('business_portal_client_id', $session->business_client_id);

                if (Auth::guard('business')->check()) {
                    $user = Auth::guard('business')->user();
                    $user->business_portal_client_id = $session->business_client_id;
                }
            } else {
                session()->forget(['impersonation_active', 'impersonation_token', 'impersonation_admin_id', 'impersonation_client_id', 'impersonation_mode']);
            }
        }

        return $next($request);
    }
}
