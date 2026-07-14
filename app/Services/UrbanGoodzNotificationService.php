<?php

namespace App\Services;

use App\Jobs\SendFirebaseNotification;
use App\Models\DeliveryMan;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;

class UrbanGoodzNotificationService
{
    public function notifyCustomer(int $customerId, string $title, string $description, array $payload = []): ?UserNotification
    {
        return $this->persistAndDispatch('customer', $customerId, $title, $description, $payload);
    }

    public function notifyVendor(int $vendorId, string $title, string $description, array $payload = []): ?UserNotification
    {
        return $this->persistAndDispatch('vendor', $vendorId, $title, $description, $payload);
    }

    public function notifyDriver(int $driverId, string $title, string $description, array $payload = []): ?UserNotification
    {
        return $this->persistAndDispatch('driver', $driverId, $title, $description, $payload);
    }

    private function persistAndDispatch(
        string $recipientType,
        int $recipientId,
        string $title,
        string $description,
        array $payload
    ): ?UserNotification {
        if ($recipientId <= 0 || ! $this->recipientExists($recipientType, $recipientId)) {
            return null;
        }

        $payload = array_merge($payload, [
            'title' => $title,
            'description' => $description,
        ]);
        $recipientColumn = match ($recipientType) {
            'customer' => 'user_id',
            'vendor' => 'vendor_id',
            'driver' => 'delivery_man_id',
        };
        $notification = UserNotification::create([
            $recipientColumn => $recipientId,
            'title' => $title,
            'description' => $description,
            'data' => json_encode($payload),
        ]);

        if ($this->recipientHasToken($recipientType, $recipientId)) {
            SendFirebaseNotification::dispatch($notification->id, $recipientType, $recipientId);
        }

        return $notification;
    }

    private function recipientExists(string $recipientType, int $recipientId): bool
    {
        return match ($recipientType) {
            'customer' => User::whereKey($recipientId)->exists(),
            'vendor' => Vendor::whereKey($recipientId)->exists(),
            'driver' => DeliveryMan::whereKey($recipientId)->exists(),
        };
    }

    private function recipientHasToken(string $recipientType, int $recipientId): bool
    {
        $token = match ($recipientType) {
            'customer' => User::whereKey($recipientId)->value('cm_firebase_token'),
            'vendor' => Vendor::whereKey($recipientId)->value('firebase_token'),
            'driver' => DeliveryMan::whereKey($recipientId)->value('fcm_token'),
        };

        return is_string($token) && trim($token) !== '';
    }
}
