<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UrbanGoodzDriverDispatchNotificationController extends Controller
{
    private const DISPATCH_TYPES = [
        'business_courier_assigned',
        'business_courier_updated',
        'package_pool_available',
        'dedicated_route_available',
        'dedicated_route_assigned',
        'package_exception',
        'proof_required',
        'age_verification_required',
        'medical_review_required',
        'order_anywhere_count_available',
    ];

    private const ACTION_REQUIRED_TYPES = [
        'business_courier_assigned',
        'business_courier_updated',
        'package_exception',
        'proof_required',
        'age_verification_required',
        'medical_review_required',
    ];

    private const HIGH_PRIORITY_TYPES = [
        'age_verification_required',
        'medical_review_required',
        'package_exception',
        'proof_required',
    ];

    private function authDriver(Request $request): DeliveryMan
    {
        $driver = $request->user('delivery_man');
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }

        return $driver;
    }

    private function driverNotifications(DeliveryMan $driver)
    {
        return UserNotification::query()
            ->where('delivery_man_id', $driver->id)
            ->orderByDesc('id');
    }

    private function isDismissed(array $data): bool
    {
        return !empty($data['dismissed_at']);
    }

    private function isRead(array $data): bool
    {
        return !empty($data['read_at']);
    }

    private function normalize(UserNotification $notification): array
    {
        $data = $notification->data ?? [];

        $type = $data['type'] ?? 'notification';
        $title = $data['title'] ?? '';
        $body = $data['description'] ?? '';

        $jobId = $data['order_id'] ?? ($data['job_id'] ?? null);
        if ($jobId !== null) {
            $jobId = (int) $jobId;
        }

        $readAt = $data['read_at'] ?? null;
        $status = $readAt ? 'read' : 'unread';

        $reviewFlags = [];
        if ($type === 'medical_review_required' || str_contains((string) $type, 'medical')) {
            $reviewFlags[] = 'medical_review_required';
        }
        if ($type === 'age_verification_required') {
            $reviewFlags[] = 'age_restricted_review';
        }

        return [
            'id' => $notification->id,
            'type' => in_array($type, self::DISPATCH_TYPES, true) ? $type : 'notification',
            'title' => $title,
            'body' => $body,
            'job_type' => $data['job_type'] ?? null,
            'job_id' => $jobId,
            'status' => $status,
            'priority' => in_array($type, self::HIGH_PRIORITY_TYPES, true) ? 'high' : 'normal',
            'requires_action' => in_array($type, self::ACTION_REQUIRED_TYPES, true),
            'review_flags' => $reviewFlags,
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $readAt,
            'can_open' => true,
            'can_dismiss' => true,
        ];
    }

    private function visibleNotifications(DeliveryMan $driver): array
    {
        return $this->driverNotifications($driver)
            ->get()
            ->filter(function (UserNotification $n) {
                return !$this->isDismissed($n->data ?? []);
            })
            ->map(function (UserNotification $n) {
                return $this->normalize($n);
            })
            ->values()
            ->all();
    }

    public function index(Request $request)
    {
        $driver = $this->authDriver($request);

        $notifications = $this->visibleNotifications($driver);
        $unread = count(array_filter($notifications, fn ($n) => $n['status'] === 'unread'));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unread,
            'total' => count($notifications),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $driver = $this->authDriver($request);

        $notifications = $this->visibleNotifications($driver);
        $unread = count(array_filter($notifications, fn ($n) => $n['status'] === 'unread'));

        return response()->json([
            'unread_count' => $unread,
        ]);
    }

    private function findOwnedNotification(DeliveryMan $driver, $id): UserNotification
    {
        $validator = Validator::make(
            ['id' => $id],
            ['id' => ['required', 'integer', 'min:1']]
        );

        if ($validator->fails()) {
            abort(404);
        }

        $notification = $this->driverNotifications($driver)
            ->whereKey((int) $id)
            ->first();

        if (!$notification) {
            abort(404);
        }

        return $notification;
    }

    private function persistData(UserNotification $notification, array $extra): void
    {
        $data = $notification->data ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        foreach ($extra as $key => $value) {
            $data[$key] = $value;
        }
        $notification->data = json_encode($data);
        $notification->save();
    }

    public function markRead(Request $request, $notificationId)
    {
        $driver = $this->authDriver($request);
        $notification = $this->findOwnedNotification($driver, $notificationId);

        $this->persistData($notification, ['read_at' => now()->toDateTimeString()]);

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $this->normalize($notification->fresh()),
        ]);
    }

    public function readAll(Request $request)
    {
        $driver = $this->authDriver($request);

        $now = now()->toDateTimeString();
        foreach ($this->driverNotifications($driver)->get() as $notification) {
            $data = $notification->data ?? [];
            if (!is_array($data)) {
                $data = [];
            }
            if (!empty($data['dismissed_at'])) {
                continue;
            }
            if (!empty($data['read_at'])) {
                continue;
            }
            $data['read_at'] = $now;
            $notification->data = json_encode($data);
            $notification->save();
        }

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    public function dismiss(Request $request, $notificationId)
    {
        $driver = $this->authDriver($request);
        $notification = $this->findOwnedNotification($driver, $notificationId);

        $this->persistData($notification, ['dismissed_at' => now()->toDateTimeString()]);

        return response()->json([
            'message' => 'Notification dismissed',
        ]);
    }
}
