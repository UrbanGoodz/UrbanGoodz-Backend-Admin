<?php

namespace App\Jobs;

use App\Models\DeliveryMan;
use App\Models\User;
use App\Models\UserNotification;
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
        public int $recipientId
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(FirebaseNotificationTransport $transport): void
    {
        $notification = UserNotification::findOrFail($this->notificationId);
        $token = $this->recipientToken();

        if (! $token) {
            throw new RuntimeException('Firebase recipient token is unavailable.');
        }

        $payload = is_array($notification->data) ? $notification->data : [];
        $payload['title'] = $notification->title ?: ($payload['title'] ?? 'Urban Goodz');
        $payload['description'] = $notification->description ?: ($payload['description'] ?? '');

        if (! $transport->send($token, $payload)) {
            throw new RuntimeException('Firebase provider rejected the notification.');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Firebase notification delivery exhausted retries.', [
            'notification_id' => $this->notificationId,
            'recipient_type' => $this->recipientType,
            'recipient_id' => $this->recipientId,
            'exception' => $exception::class,
        ]);
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
