<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessPortalRoleMiddleware
{
    private const ROLE_HIERARCHY = [
        'business_owner' => [
            'business.*',
        ],
        'business_admin' => [
            'business.*',
        ],
        'dispatcher' => [
            'business.loads.',
            'business.drivers.',
            'business.dispatch.',
            'business.dispatcher.',
            'business.routes.',
        ],
        'operations_manager' => [
            'business.routes.',
            'business.packages.',
            'business.locations.',
            'business.staff.',
            'business.operations.',
            'business.workflows.',
        ],
        'billing_manager' => [
            'business.invoices.',
            'business.payments.',
            'business.billing.',
            'business.finance.',
        ],
        'employee' => [
            'business.scan.',
            'business.packages.',
            'business.routes.',
            'business.documents.',
        ],
        'viewer' => [
            'business.dashboard.',
            'business.reports.',
            'business.jobs.',
            'business.invoices.',
            'business.locations.',
        ],
    ];

    private const EXCLUDED_PREFIXES_FOR_BUSINESS_ADMIN = [
        'business.billing.credentials.',
        'business.billing.methods.manage',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('business')->user();

        if (!$user) {
            abort(403, 'Unauthenticated business user.');
        }

        $role = $user->portal_role ?? 'viewer';

        if ($role === 'owner_admin') {
            return $next($request);
        }

        $allowedPrefixes = self::ROLE_HIERARCHY[$role] ?? self::ROLE_HIERARCHY['viewer'];

        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                if ($role === 'business_admin') {
                    foreach (self::EXCLUDED_PREFIXES_FOR_BUSINESS_ADMIN as $excluded) {
                        if (str_starts_with($routeName, $excluded)) {
                            abort(403, 'Your role does not have permission to access this resource.');
                        }
                    }
                }

                return $next($request);
            }
        }

        abort(403, 'Your role does not have permission to access this resource.');
    }
}
