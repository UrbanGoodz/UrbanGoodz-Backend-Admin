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
    /** Channels that physically require an FCM device token. */
    public const PUSH_CHANNELS = ['push', 'firebase_push'];

    /**
     * Channels that are satisfied the moment the row is persisted. These are
     * read out of the database by the apps and the admin panel, so they must
     * never depend on - or be failed by - a missing FCM device token.
     */
    public const IN_APP_CHANNELS = ['in_app', 'database', 'websocket', 'admin_alert'];

    public static function isPushChannel(?string $channel): bool
    {
        return in_array(strtolower(trim((string) $channel)), self::PUSH_CHANNELS, true);
    }

    public static function isInAppChannel(?string $channel): bool
    {
        return in_array(strtolower(trim((string) $channel)), self::IN_APP_CHANNELS, true);
    }

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
     *
     * Only push channels consult the FCM device token. An in-app notification
     * is delivered as soon as it is persisted, so a recipient with no FCM
     * token still receives it - it is never marked failed for that reason.
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

            if (self::isInAppChannel($notification->channel)) {
                $notification->update(['status' => 'delivered']);
                continue;
            }

            if (strtolower((string) $notification->channel) === 'email') {
                $this->deliverEmail($notification);
                continue;
            }

            if (!self::isPushChannel($notification->channel)) {
                // sms / webhook have no transport in this codebase. They used to stay `pending`
                // forever while the API reported success; say plainly that they were not sent.
                $notification->update([
                    'status' => 'failed',
                    'data' => array_merge((array) $notification->data, ['delivery_note' => "No '{$notification->channel}' transport is configured."]),
                ]);
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

    /** Real email delivery through the configured mailer; never throws into the caller. */
    private function deliverEmail(UrbanGoodzNotification $notification): void
    {
        $address = match ($notification->recipient_type) {
            'customer' => DB::table('users')->where('id', $notification->recipient_id)->value('email'),
            'vendor' => DB::table('vendors')->where('id', $notification->recipient_id)->value('email'),
            'driver' => DB::table('delivery_men')->where('id', $notification->recipient_id)->value('email'),
            default => null,
        };

        if (! $address || ! filter_var($address, FILTER_VALIDATE_EMAIL) || ! config('mail.status', true)) {
            $notification->update(['status' => 'failed', 'data' => array_merge((array) $notification->data, ['delivery_note' => 'No deliverable email address or mail is disabled.'])]);

            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw((string) $notification->body, function ($m) use ($address, $notification) {
                $m->to($address)->subject((string) ($notification->subject ?: 'Urban Goodz'));
            });
            $notification->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notification email failed', ['notification_id' => $notification->id, 'error' => $e->getMessage()]);
            $notification->update(['status' => 'failed']);
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
            'driver' => DB::table('delivery_men')->where('id', $recipientId)->value('firebase_token'),
            default => null,
        };
    }
}
