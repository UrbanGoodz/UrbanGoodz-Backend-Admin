<?php

namespace App\Broadcasting;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class UrbanGoodzChannelAuthorizer
{
    public function shopper(Authenticatable $actor, int $customerId): bool
    {
        return $actor instanceof User && (int) $actor->getAuthIdentifier() === $customerId;
    }

    public function vendor(Authenticatable $actor, int $vendorId): bool
    {
        if ($actor instanceof Vendor) {
            return (int) $actor->getAuthIdentifier() === $vendorId;
        }

        return $actor instanceof VendorEmployee && (int) $actor->vendor_id === $vendorId;
    }

    public function driver(Authenticatable $actor, int $deliveryManId): bool
    {
        if ($actor instanceof DeliveryMan) {
            return (int) $actor->getAuthIdentifier() === $deliveryManId;
        }

        // The legacy delivery_men guard uses the database provider and returns
        // GenericUser. Verify both the active guard and identifier in that case.
        return $actor instanceof GenericUser
            && Auth::guard('delivery_men')->check()
            && (int) Auth::guard('delivery_men')->id() === $deliveryManId
            && (int) $actor->getAuthIdentifier() === $deliveryManId;
    }

    public function business(Authenticatable $actor, int $businessClientId): bool
    {
        return $actor instanceof UrbanGoodzBusinessClientUser
            && $actor->is_active
            && (int) $actor->business_client_id === $businessClientId;
    }

    public function dispatcher(Authenticatable $actor, int $dispatcherId): bool
    {
        return $actor instanceof UrbanGoodzBusinessClientUser
            && $actor->is_active
            && (int) $actor->getAuthIdentifier() === $dispatcherId
            && $actor->isDispatchRole();
    }

    public function payment(
        Authenticatable $actor,
        string $accountType,
        int $accountId
    ): bool {
        return match ($accountType) {
            'shopper' => $this->shopper($actor, $accountId),
            'vendor' => $this->vendor($actor, $accountId),
            'driver' => $this->driver($actor, $accountId),
            'business' => $this->business($actor, $accountId),
            'dispatcher' => $this->dispatcher($actor, $accountId),
            'admin' => $this->admin($actor)
                && (int) $actor->getAuthIdentifier() === $accountId,
            default => false,
        };
    }

    public function supportConversation(
        Authenticatable $actor,
        int $conversationId
    ): bool {
        if ($this->admin($actor)) {
            return true;
        }

        $userInfoColumn = match (true) {
            $actor instanceof User => 'user_id',
            $actor instanceof Vendor => 'vendor_id',
            $actor instanceof VendorEmployee => 'vendor_id',
            $actor instanceof DeliveryMan => 'deliveryman_id',
            $actor instanceof GenericUser
                && Auth::guard('delivery_men')->check() => 'deliveryman_id',
            default => null,
        };

        if ($userInfoColumn === null) {
            return false;
        }

        $actorId = $actor instanceof VendorEmployee
            ? (int) $actor->vendor_id
            : (int) $actor->getAuthIdentifier();

        $userInfoIds = UserInfo::query()
            ->where($userInfoColumn, $actorId)
            ->pluck('id');

        if ($userInfoIds->isEmpty()) {
            return false;
        }

        return Conversation::query()
            ->whereKey($conversationId)
            ->where(function ($query) use ($userInfoIds) {
                $query->whereIn('sender_id', $userInfoIds)
                    ->orWhereIn('receiver_id', $userInfoIds);
            })
            ->exists();
    }

    public function admin(Authenticatable $actor): bool
    {
        return $actor instanceof Admin;
    }
}
