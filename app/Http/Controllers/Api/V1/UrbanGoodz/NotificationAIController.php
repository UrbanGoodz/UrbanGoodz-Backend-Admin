<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzActivityLog;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use App\Services\UrbanGoodzNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationAIController extends Controller
{
    public function __construct(
        private UrbanGoodzAIService $ai,
        private UrbanGoodzNotificationService $notificationService
    ) {}

    // ─── GENERATE PERSONALIZED NOTIFICATION ──────────────────────────────

    public function generateNotification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'in:order_created,order_confirmed,order_ready,order_picked_up,order_delivered,order_cancelled,order_refunded,quote_received,quote_accepted,quote_rejected,driver_assigned,driver_arrived,delivery_exception,service_booked,service_completed,rental_confirmed,rental_returned,load_posted,load_assigned,load_delivered,dispute_opened,dispute_resolved,fraud_alert,eta_updated,promotion_available'],
            'recipient_type' => ['required', 'string', 'in:customer,vendor,driver,business,dispatcher,admin'],
            'recipient_id' => ['required', 'integer'],
            'context' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,push,email,sms,webhook'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        // Verify recipient authorization
        $authorized = $this->checkAuthorization($data['recipient_type'], $data['recipient_id']);
        if (!$authorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: recipient not found or not accessible',
            ], 403);
        }

        // Get base template
        $template = $this->getBaseTemplate($data['event_type'], $data['recipient_type']);

        // AI personalization
        $personalized = $this->personalizeWithAI($template, $data['context'] ?? [], $data['recipient_type']);

        // Determine channels
        $channels = $data['channels'] ?? $this->getDefaultChannels($data['event_type'], $data['priority'] ?? 'normal');

        // Create notification records
        $notifications = [];
        foreach ($channels as $channel) {
            $notification = $this->notificationService->create([
                'recipient_type' => $data['recipient_type'],
                'recipient_id' => $data['recipient_id'],
                'channel' => $channel,
                'event_type' => $data['event_type'],
                'subject' => $personalized['subject'] ?? null,
                'body' => $personalized['body'],
                'data' => array_merge($data['context'] ?? [], [
                    'event_type' => $data['event_type'],
                    'personalized' => true,
                    'ai_generated' => true,
                ]),
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'pending',
            ]);
            $notifications[] = $notification;
        }

        // Queue for delivery (async via queue worker)
        $this->notificationService->queueForDelivery($notifications);

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'personalized' => $personalized,
            'channels' => $channels,
        ]);
    }

    // ─── BATCH NOTIFICATION ──────────────────────────────────────────────

    public function generateBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string'],
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*.recipient_type' => ['required', 'string', 'in:customer,vendor,driver,business,dispatcher,admin'],
            'recipients.*.recipient_id' => ['required', 'integer'],
            'context' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,push,email,sms,webhook'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        $authorizedRecipients = [];
        foreach ($data['recipients'] as $recipient) {
            if ($this->checkAuthorization($recipient['recipient_type'], $recipient['recipient_id'])) {
                $authorizedRecipients[] = $recipient;
            }
        }

        if (empty($authorizedRecipients)) {
            return response()->json([
                'success' => false,
                'message' => 'No authorized recipients found',
            ], 403);
        }

        $template = $this->getBaseTemplate($data['event_type'], $authorizedRecipients[0]['recipient_type']);
        $context = $data['context'] ?? [];

        $notifications = [];
        foreach ($authorizedRecipients as $recipient) {
            $personalized = $this->personalizeWithAI($template, $context, $recipient['recipient_type']);
            $channels = $data['channels'] ?? $this->getDefaultChannels($data['event_type'], $data['priority'] ?? 'normal');

            foreach ($channels as $channel) {
                $notifications[] = $this->notificationService->create([
                    'recipient_type' => $recipient['recipient_type'],
                    'recipient_id' => $recipient['recipient_id'],
                    'channel' => $channel,
                    'event_type' => $data['event_type'],
                    'subject' => $personalized['subject'] ?? null,
                    'body' => $personalized['body'],
                    'data' => array_merge($context, [
                        'event_type' => $data['event_type'],
                        'personalized' => true,
                        'ai_generated' => true,
                    ]),
                    'priority' => $data['priority'] ?? 'normal',
                    'status' => 'pending',
                ]);
            }
        }

        $this->notificationService->queueForDelivery($notifications);

        return response()->json([
            'success' => true,
            'created' => count($notifications),
            'recipients' => count($authorizedRecipients),
        ]);
    }

    // ─── NOTIFICATION HISTORY ────────────────────────────────────────────

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_type' => ['required', 'string', 'in:customer,vendor,driver,business,dispatcher,admin'],
            'recipient_id' => ['required', 'integer'],
            'event_type' => ['nullable', 'string'],
            'channel' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,sent,delivered,failed,read'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = \App\Models\UrbanGoodzNotification::where('recipient_type', $data['recipient_type'])
            ->where('recipient_id', $data['recipient_id']);

        if (!empty($data['event_type'])) {
            $query->where('event_type', $data['event_type']);
        }
        if (!empty($data['channel'])) {
            $query->where('channel', $data['channel']);
        }
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (!empty($data['date_from'])) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }

        $notifications = $query->orderByDesc('created_at')
            ->limit($data['limit'] ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
        ]);
    }

    // ─── MARK AS READ ────────────────────────────────────────────────────

    public function markAsRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1'],
            'notification_ids.*' => ['integer'],
        ]);

        \App\Models\UrbanGoodzNotification::whereIn('id', $data['notification_ids'])
            ->where('recipient_type', auth('api')->user()?->getMorphClass() ?? 'customer')
            ->where('recipient_id', auth('api')->id())
            ->update(['status' => 'read', 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read',
        ]);
    }

    // ─── TEMPLATE PREVIEW ────────────────────────────────────────────────

    public function previewTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string'],
            'recipient_type' => ['required', 'string', 'in:customer,vendor,driver,business,dispatcher,admin'],
            'context' => ['nullable', 'array'],
        ]);

        $template = $this->getBaseTemplate($data['event_type'], $data['recipient_type']);
        $personalized = $this->personalizeWithAI($template, $data['context'] ?? [], $data['recipient_type']);

        return response()->json([
            'success' => true,
            'template' => $template,
            'personalized' => $personalized,
        ]);
    }

    // ─── PRIVATE HELPERS ────────────────────────────────────────────────

    private function checkAuthorization(string $recipientType, int $recipientId): bool
    {
        return match ($recipientType) {
            'customer' => \App\Models\User::where('id', $recipientId)->exists(),
            'vendor' => \App\Models\Vendor::where('id', $recipientId)->exists(),
            'driver' => \App\Models\DeliveryMan::where('id', $recipientId)->exists(),
            'business' => \App\Models\UrbanGoodzBusinessClientUser::where('id', $recipientId)->exists(),
            'dispatcher' => \App\Models\UrbanGoodzBusinessClientUser::where('id', $recipientId)
                ->where('role', 'dispatcher')
                ->exists(),
            'admin' => \App\Models\Admin::where('id', $recipientId)->exists(),
            default => false,
        };
    }

    private function getBaseTemplate(string $eventType, string $recipientType): array
    {
        $templates = [
            'order_created' => [
                'customer' => ['subject' => 'Order Confirmed', 'body' => 'Your order {{order_number}} has been confirmed. Estimated delivery: {{eta}}.'],
                'vendor' => ['subject' => 'New Order', 'body' => 'New order {{order_number}} for {{store_name}}. Items: {{items}}. Please confirm.'],
                'driver' => ['subject' => 'New Delivery Available', 'body' => 'Order {{order_number}} is ready for pickup at {{store_name}}.'],
            ],
            'order_confirmed' => [
                'customer' => ['subject' => 'Order Confirmed', 'body' => 'Your order {{order_number}} is being prepared.'],
                'vendor' => ['subject' => 'Order Confirmed', 'body' => 'Order {{order_number}} confirmed by customer.'],
            ],
            'order_ready' => [
                'customer' => ['subject' => 'Order Ready', 'body' => 'Your order {{order_number}} is ready for pickup.'],
                'driver' => ['subject' => 'Pickup Ready', 'body' => 'Order {{order_number}} is ready for pickup at {{store_name}}.'],
            ],
            'order_picked_up' => [
                'customer' => ['subject' => 'Order Picked Up', 'body' => 'Your order {{order_number}} has been picked up by {{driver_name}}. ETA: {{eta}}.'],
            ],
            'order_delivered' => [
                'customer' => ['subject' => 'Delivered', 'body' => 'Your order {{order_number}} has been delivered. Enjoy!'],
                'vendor' => ['subject' => 'Order Delivered', 'body' => 'Order {{order_number}} delivered successfully.'],
                'driver' => ['subject' => 'Delivery Complete', 'body' => 'Order {{order_number}} marked as delivered.'],
            ],
            'order_cancelled' => [
                'customer' => ['subject' => 'Order Cancelled', 'body' => 'Your order {{order_number}} has been cancelled. Reason: {{reason}}. Refund: {{refund_amount}}.'],
                'vendor' => ['subject' => 'Order Cancelled', 'body' => 'Order {{order_number}} cancelled. Reason: {{reason}}.'],
            ],
            'order_refunded' => [
                'customer' => ['subject' => 'Refund Processed', 'body' => 'Your refund of {{refund_amount}} for order {{order_number}} has been processed.'],
            ],
            'quote_received' => [
                'customer' => ['subject' => 'Quote Received', 'body' => 'You received a quote of {{quote_amount}} for your Order Anywhere request {{request_number}}.'],
            ],
            'quote_accepted' => [
                'customer' => ['subject' => 'Quote Accepted', 'body' => 'Your quote of {{quote_amount}} for request {{request_number}} has been accepted.'],
                'vendor' => ['subject' => 'Quote Accepted', 'body' => 'Customer accepted your quote for request {{request_number}}.'],
            ],
            'quote_rejected' => [
                'customer' => ['subject' => 'Quote Updated', 'body' => 'The vendor updated the quote for request {{request_number}}. New amount: {{quote_amount}}.'],
            ],
            'driver_assigned' => [
                'customer' => ['subject' => 'Driver Assigned', 'body' => 'Driver {{driver_name}} has been assigned to your order {{order_number}}.'],
                'driver' => ['subject' => 'New Assignment', 'body' => 'You have been assigned to order {{order_number}}. Pickup at {{pickup_location}}.'],
            ],
            'driver_arrived' => [
                'customer' => ['subject' => 'Driver Arrived', 'body' => 'Your driver {{driver_name}} has arrived at the pickup location.'],
            ],
            'delivery_exception' => [
                'customer' => ['subject' => 'Delivery Update', 'body' => 'There was an issue with your delivery: {{exception_reason}}. We are working on it.'],
                'driver' => ['subject' => 'Exception Reported', 'body' => 'Exception for order {{order_number}}: {{exception_reason}}. Please follow up.'],
            ],
            'service_booked' => [
                'customer' => ['subject' => 'Service Booked', 'body' => 'Your {{service_name}} appointment is confirmed for {{date}} at {{time}}.'],
                'vendor' => ['subject' => 'New Service Booking', 'body' => 'New {{service_name}} booking for {{date}} at {{time}}. Customer: {{customer_name}}.'],
            ],
            'service_completed' => [
                'customer' => ['subject' => 'Service Completed', 'body' => 'Your {{service_name}} service has been completed. Please rate your experience.'],
                'vendor' => ['subject' => 'Service Completed', 'body' => 'Service {{service_name}} completed for {{customer_name}}.'],
            ],
            'rental_confirmed' => [
                'customer' => ['subject' => 'Rental Confirmed', 'body' => 'Your {{vehicle_type}} rental is confirmed for {{start_date}} to {{end_date}}. Pickup: {{pickup_location}}.'],
            ],
            'rental_returned' => [
                'customer' => ['subject' => 'Rental Returned', 'body' => 'Your rental has been returned. Final charges: {{final_amount}}.'],
            ],
            'load_posted' => [
                'dispatcher' => ['subject' => 'New Load Posted', 'body' => 'New load {{load_number}}: {{origin}} to {{destination}}. Rate: {{rate}}.'],
            ],
            'load_assigned' => [
                'driver' => ['subject' => 'Load Assigned', 'body' => 'Load {{load_number}} assigned to you. Pickup: {{pickup_location}}. Delivery: {{delivery_location}}.'],
                'dispatcher' => ['subject' => 'Load Assigned', 'body' => 'Load {{load_number}} assigned to driver {{driver_name}}.'],
            ],
            'load_delivered' => [
                'driver' => ['subject' => 'Load Delivered', 'body' => 'Load {{load_number}} delivered successfully.'],
                'dispatcher' => ['subject' => 'Load Delivered', 'body' => 'Load {{load_number}} delivered by {{driver_name}}.'],
            ],
            'dispute_opened' => [
                'customer' => ['subject' => 'Dispute Opened', 'body' => 'Your dispute for transaction {{transaction_id}} has been opened. We will review within 48 hours.'],
                'admin' => ['subject' => 'New Dispute', 'body' => 'New dispute for transaction {{transaction_id}} by customer {{customer_id}}. Reason: {{reason}}.'],
            ],
            'dispute_resolved' => [
                'customer' => ['subject' => 'Dispute Resolved', 'body' => 'Your dispute for transaction {{transaction_id}} has been {{resolution}}. Refund: {{refund_amount}}.'],
            ],
            'fraud_alert' => [
                'admin' => ['subject' => 'Fraud Alert', 'body' => 'Suspicious activity detected: {{alert_type}} for {{entity_type}} {{entity_id}}. Risk score: {{risk_score}}.'],
            ],
            'eta_updated' => [
                'customer' => ['subject' => 'ETA Updated', 'body' => 'Your order {{order_number}} ETA is now {{eta}}.'],
            ],
            'promotion_available' => [
                'customer' => ['subject' => 'Special Offer', 'body' => '{{promotion_title}}: {{promotion_description}}. Use code {{code}}. Expires {{expiry}}.'],
            ],
        ];

        return $templates[$eventType][$recipientType] ?? [
            'subject' => 'Urban Goodz Notification',
            'body' => 'You have a new notification regarding {{event_type}}.',
        ];
    }

    private function personalizeWithAI(array $template, array $context, string $recipientType): array
    {
        if (empty($context)) {
            return $template;
        }

        $prompt = "You are personalizing a notification template for Urban Goodz.
Template: Subject: {$template['subject']}, Body: {$template['body']}
Context: " . json_encode($context, JSON_PRETTY_PRINT) . "
Recipient type: {$recipientType}

Return ONLY a JSON object with 'subject' and 'body' fields, with all {{placeholders}} replaced with actual values from context.
If a placeholder has no value, use a sensible default or remove the placeholder text.
Keep the tone friendly and professional.";

        $result = $this->ai->chat($prompt, 'Personalize this notification', $context);
        $parsed = json_decode(trim($result), true);

        if ($parsed && isset($parsed['subject'], $parsed['body'])) {
            return $parsed;
        }

        // Fallback: simple string replacement
        $subject = $template['subject'];
        $body = $template['body'];
        foreach ($context as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    private function getDefaultChannels(string $eventType, string $priority): array
    {
        $channels = ['in_app'];

        if (in_array($eventType, ['order_delivered', 'order_cancelled', 'dispute_resolved', 'fraud_alert', 'delivery_exception'], true)) {
            $channels[] = 'push';
        }

        if (in_array($eventType, ['order_created', 'order_confirmed', 'quote_received', 'service_booked', 'rental_confirmed', 'rental_returned'], true)) {
            $channels[] = 'email';
        }

        if ($priority === 'urgent' || in_array($eventType, ['delivery_exception', 'fraud_alert', 'dispute_opened'], true)) {
            $channels[] = 'sms';
        }

        return array_unique($channels);
    }
}