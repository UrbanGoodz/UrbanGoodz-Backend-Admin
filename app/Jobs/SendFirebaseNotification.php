<?php

namespace App\Jobs;

use App\Models\DeliveryMan;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UrbanGoodzNotification;
use App\Models\Vendor;
use App\Services\FirebaseNotificationTransport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendFirebaseNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $notificationId,
        public string $recipientType,
        public int $recipientId,
        public ?string $channel = null
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(FirebaseNotificationTransport $transport): void
    {
        $notification = $this->resolveNotification();

        if (! $notification) {
            Log::warning('Firebase notification record not found.', [
                'notification_id' => $this->notificationId,
                'recipient_type' => $this->recipientType,
            ]);
            return;
        }

        $token = $this->recipientToken();

        if (! $token) {
            $this->markAsFailed($notification);
            throw new RuntimeException('Firebase recipient token is unavailable.');
        }

        $payload = $this->buildPayload($notification);

        if (! $transport->send($token, $payload)) {
            $this->markAsFailed($notification);
            throw new RuntimeException('Firebase provider rejected the notification.');
        }

        $this->markAsDelivered($notification);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Firebase notification delivery exhausted retries.', [
            'notification_id' => $this->notificationId,
            'recipient_type' => $this->recipientType,
            'recipient_id' => $this->recipientId,
            'channel' => $this->channel,
            'exception' => $exception::class,
        ]);
    }

    /**
     * Dispatch for UrbanGoodzNotification with channel awareness.
     */
    public static function dispatchViaChannel(
        int $notificationId,
        string $recipientType,
        int $recipientId,
        ?string $channel = null
    ): static {
        if ($channel === 'push' || $channel === null) {
            return static::dispatch($notificationId, $recipientType, $recipientId, $channel);
        }

        Log::info('Non-push channel skipped for Firebase delivery.', [
            'notification_id' => $notificationId,
            'channel' => $channel,
        ]);

        return static::dispatch($notificationId, $recipientType, $recipientId, $channel);
    }

    private function resolveNotification(): UserNotification|UrbanGoodzNotification|null
    {
        if ($this->channel !== null) {
            return UrbanGoodzNotification::find($this->notificationId);
        }

        return UserNotification::find($this->notificationId);
    }

    private function buildPayload(UserNotification|UrbanGoodzNotification|null $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'title' => $notification->title ?? ($data['title'] ?? 'Urban Goodz'),
            'description' => $notification->description ?? ($data['description'] ?? ''),
            'image' => $data['image'] ?? '',
            'type' => $data['event_type'] ?? $data['type'] ?? 'general',
            'order_id' => $data['order_id'] ?? '',
        ];
    }

    private function markAsFailed(UserNotification|UrbanGoodzNotification|null $notification): void
    {
        if ($notification instanceof UrbanGoodzNotification) {
            $notification->update(['status' => 'failed']);
        }
    }

    private function markAsDelivered(UserNotification|UrbanGoodzNotification|null $notification): void
    {
        if ($notification instanceof UrbanGoodzNotification) {
            $notification->update(['status' => 'sent']);
        }
    }

    private function recipientToken(): ?string
    {
        return match ($this->recipientType) {
            'customer' => User::whereKey($this->recipientId)->value('cm_firebase_token'),
            'vendor' => Vendor::whereKey($this->recipientId)->value('firebase_token'),
            'driver' => DeliveryMan::whereKey($this->recipientId)->value('fcm_token'),
            default => null,
        };
    }
}
