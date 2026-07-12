<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DispatchTerritoryScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('business')->user();

        if ($user && $user->client && $user->client->isDispatchCompany()) {
            $territoryStates = $user->client->territory_states ?? [];
            $request->attributes->set('dispatch_territory_states', $territoryStates);
            $request->attributes->set('dispatch_company_id', $user->business_client_id);
        }

        return $next($request);
    }
}
