<?php

namespace App\Services\UrbanGoodz\Payouts;

use App\Models\UrbanGoodzConnectedAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PayoutActorResolver
{
    public function resolve(Request $request): array
    {
        [$actorType, $actorId, $defaultRole, $defaultOwnerId] = $this->authenticatedActor($request);
        $role = (string) $request->header('X-Urban-Goodz-Earning-Role', $defaultRole);
        $ownerId = (int) $request->header('X-Urban-Goodz-Earning-Entity-Id', $defaultOwnerId);

        abort_unless(in_array($role, UrbanGoodzConnectedAccount::ROLES, true), 422, 'Unsupported earning role.');
        abort_unless($ownerId > 0, 422, 'A valid earning entity is required.');

        $isNativeOwner = $role === $defaultRole && $ownerId === $defaultOwnerId;
        $isBound = DB::table('urban_goodz_payout_actor_bindings')
            ->where('owner_id', $ownerId)
            ->where('owner_role', $role)
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('can_manage', true)
            ->exists();

        abort_unless($isNativeOwner || $isBound, 403, 'Payout account ownership could not be verified.');

        return [
            'role' => $role,
            'id' => $ownerId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ];
    }

    private function authenticatedActor(Request $request): array
    {
        if ($vendor = $request->get('vendor')) {
            return ['vendor', (int) $vendor->id, 'vendor', (int) $vendor->id];
        }
        if ($driver = auth('delivery_men')->user()) {
            return ['driver', (int) $driver->id, 'driver', (int) $driver->id];
        }
        if ($business = auth('business')->user()) {
            $role = $business->isDispatchRole() ? 'dispatcher' : 'business';
            $ownerId = $role === 'dispatcher' ? (int) $business->id : (int) $business->business_client_id;

            return ['business_user', (int) $business->id, $role, $ownerId];
        }
        if ($user = $request->user('api')) {
            return ['user', (int) $user->id, '', 0];
        }

        throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }
}
