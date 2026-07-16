<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UrbanGoodzBusinessPortalAuditLog;

class ImpersonationAuditMiddleware
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const SENSITIVE_FIELDS = [
        'password', 'password_confirmation', 'token', 'api_token',
        'secret', 'credit_card', 'card_number', 'cvv', 'ssn',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (session()->has('impersonation_active') && session('impersonation_active') === true) {
            $this->logRequest($request);
        }

        return $response;
    }

    private function logRequest(Request $request): void
    {
        $method = strtoupper($request->method());
        $actionType = in_array($method, self::WRITE_METHODS, true) ? 'write' : 'read';

        $details = null;

        if ($actionType === 'write') {
            $body = $request->except(self::SENSITIVE_FIELDS);
            if (!empty($body)) {
                $details = $body;
            }
        }

        UrbanGoodzBusinessPortalAuditLog::create([
            'admin_id' => session('impersonation_admin_id'),
            'business_client_user_id' => Auth::guard('business')->id(),
            'business_client_id' => session('impersonation_client_id'),
            'action' => $method . ' ' . $request->path(),
            'mode' => session('impersonation_mode', 'read_only'),
            'target_type' => $request->segment(2),
            'target_id' => $request->route('id') ?? $request->route('business_client_user') ?? null,
            'details' => $details,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
