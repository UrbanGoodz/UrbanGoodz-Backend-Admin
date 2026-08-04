<?php

namespace App\Services;

use App\Jobs\SendFirebaseNotification;
use App\Models\DeliveryMan;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UrbanGoodzNotification;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

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

    /**
     * Create an UrbanGoodzNotification record (used by NotificationAIController).
     */
    public function create(array $data): UrbanGoodzNotification
    {
        return UrbanGoodzNotification::create([
            'recipient_type' => $data['recipient_type'],
            'recipient_id' => $data['recipient_id'],
            'channel' => $data['channel'],
            'event_type' => $data['event_type'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'data' => $data['data'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => $data['status'] ?? 'pending',
        ]);
    }

    /**
     * Queue notifications for async delivery via Firebase (used by NotificationAIController).
     */
    public function queueForDelivery(array $notifications): void
    {
        foreach ($notifications as $notification) {
            if (!$notification instanceof UrbanGoodzNotification) {
                continue;
            }

            if ($notification->status === 'failed') {
                continue;
            }

            $token = $this->resolveFirebaseToken($notification->recipient_type, $notification->recipient_id);

            if (empty($token)) {
                $notification->update(['status' => 'failed']);
                continue;
            }

            SendFirebaseNotification::dispatchViaChannel(
                $notification->id,
                $notification->recipient_type,
                $notification->recipient_id,
                $notification->channel
            );
        }
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
            'customer' => DB::table('users')->where('id', $recipientId)->exists(),
            'vendor' => DB::table('vendors')->where('id', $recipientId)->exists(),
            'driver' => DB::table('delivery_men')->where('id', $recipientId)->exists(),
            default => false,
        };
    }

    private function recipientHasToken(string $recipientType, int $recipientId): bool
    {
        $token = $this->resolveFirebaseToken($recipientType, $recipientId);

        return is_string($token) && trim($token) !== '';
    }

    private function resolveFirebaseToken(string $recipientType, int $recipientId): ?string
    {
        return match ($recipientType) {
            'customer' => DB::table('users')->where('id', $recipientId)->value('cm_firebase_token'),
            'vendor' => DB::table('vendors')->where('id', $recipientId)->value('firebase_token'),
            'driver' => DB::table('delivery_men')->where('id', $recipientId)->value('fcm_token'),
            default => null,
        };
    }
}
