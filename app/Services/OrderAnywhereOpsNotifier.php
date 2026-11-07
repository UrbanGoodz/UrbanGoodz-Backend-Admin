<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\UrbanGoodzNotification;
use Illuminate\Support\Facades\Log;

/**
 * Operational notices for Order Anywhere: staff alerts (stuck dispatch, failed capture) and the
 * customer messages that go with them. In-app rows are the source of truth; a missing push token
 * never loses a notice.
 */
class OrderAnywhereOpsNotifier
{
    public function __construct(private UrbanGoodzNotificationService $notifications)
    {
    }

    /** Alert every super-admin (role_id 1). */
    public function admins(string $subject, string $body, array $data = [], string $event = 'order_anywhere_alert'): int
    {
        $count = 0;
        foreach (Admin::where('role_id', 1)->pluck('id') as $adminId) {
            try {
                UrbanGoodzNotification::create([
                    'recipient_type' => 'admin', 'recipient_id' => $adminId, 'channel' => 'admin_alert', 'event_type' => $event,
                    'subject' => $subject, 'body' => $body, 'data' => $data, 'priority' => 'high', 'status' => 'delivered',
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('Order Anywhere admin alert failed', ['admin_id' => $adminId, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    public function customer(?int $customerId, string $title, string $body, array $data = []): void
    {
        if (! $customerId) {
            return;
        }
        try {
            $this->notifications->notifyCustomer($customerId, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('Order Anywhere customer notice failed', ['customer_id' => $customerId, 'error' => $e->getMessage()]);
        }
    }
}
