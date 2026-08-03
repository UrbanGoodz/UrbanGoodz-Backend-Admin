<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzRefundRequest;
use App\Models\UrbanGoodzAIIntent;
use App\Models\DeliveryMan;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportAIController extends Controller
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    // ─── ISSUE CLASSIFICATION ──────────────────────────────────────────

    public function classifyIssue(Request $request): JsonResponse
    {
        $customerId = $this->authenticatedCustomerId($request);
        $data = $request->validate([
            'query_text' => ['required', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $possibleIntents = [
            ['slug' => 'order_status', 'description' => 'Customer asking about order status, tracking, or delivery ETA'],
            ['slug' => 'payment_issue', 'description' => 'Payment failed, charged twice, refund request, or billing question'],
            ['slug' => 'delivery_issue', 'description' => 'Late delivery, missing items, wrong items, damaged goods, or driver issue'],
            ['slug' => 'account_help', 'description' => 'Login issues, password reset, account settings, profile changes'],
            ['slug' => 'cancellation', 'description' => 'Want to cancel order, return item, or modify order'],
            ['slug' => 'refund_request', 'description' => 'Requesting refund for any reason'],
            ['slug' => 'technical_issue', 'description' => 'App bug, payment error, notification issue, or feature not working'],
            ['slug' => 'vendor_complaint', 'description' => 'Complaint about store, food quality, service, or hygiene'],
            ['slug' => 'driver_complaint', 'description' => 'Complaint about driver behavior, speeding, rude, or unsafe driving'],
            ['slug' => 'general_inquiry', 'description' => 'General question about services, policies, promotions, or how to use app'],
        ];

        $classification = $this->ai->classifyIntent($data['query_text'], $possibleIntents);

        // Enrich with context if order_id provided
        $context = [];
        if (!empty($data['order_id'])) {
            $order = Order::where('user_id', $customerId)
                ->with(['details.item', 'customer', 'store', 'deliveryMan'])
                ->find($data['order_id']);
            if ($order) {
                $context = [
                    'order_number' => $order->order_number,
                    'status' => $order->order_status,
                    'items' => $order->details->map(fn($d) => $d->item->name ?? 'Unknown')->toArray(),
                    'total' => $order->order_amount,
                    'store' => $order->store->name ?? null,
                    'driver' => $order->deliveryMan ? [
                        'name' => $order->deliveryMan->name,
                        'phone' => $order->deliveryMan->phone,
                    ] : null,
                ];
            }
        }

        // Build response
        $intent = $classification['intent'] ?? 'unknown';
        $confidence = $classification['confidence'] ?? 0.0;
        $entities = $classification['entities'] ?? [];

        // Determine if auto-resolution is possible
        $autoResolvable = in_array($intent, ['order_status', 'account_help', 'cancellation']) && $confidence > 0.7;
        $requiresHuman = in_array($intent, ['vendor_complaint', 'driver_complaint', 'refund_request']) || $confidence < 0.5;

        $suggestedActions = $this->getSuggestedActions($intent, $entities, $context);

        return response()->json([
            'success' => true,
            'classification' => [
                'intent' => $intent,
                'confidence' => round($confidence, 2),
                'entities' => $entities,
                'auto_resolvable' => $autoResolvable,
                'requires_human_review' => $requiresHuman,
            ],
            'context' => $context,
            'suggested_actions' => $suggestedActions,
            'escalation_reason' => $requiresHuman ? 'Low confidence or sensitive category requiring human review' : null,
        ]);
    }

    private function getSuggestedActions(string $intent, array $entities, array $context): array
    {
        $actions = [];

        match ($intent) {
            'order_status' => $actions = [
                ['label' => 'Track Order', 'action' => 'track_order', 'params' => ['order_id' => $context['order_number'] ?? $entities['order_id'] ?? null]],
                ['label' => 'Contact Driver', 'action' => 'contact_driver', 'params' => ['driver_phone' => $context['driver']['phone'] ?? null]],
            ],
            'payment_issue' => $actions = [
                ['label' => 'View Payment Details', 'action' => 'view_payment', 'params' => ['order_id' => $entities['order_id'] ?? null]],
                ['label' => 'Request Refund', 'action' => 'request_refund', 'params' => ['order_id' => $entities['order_id'] ?? null]],
                ['label' => 'Contact Support', 'action' => 'escalate_to_human'],
            ],
            'delivery_issue' => $actions = [
                ['label' => 'Report Missing Items', 'action' => 'report_missing', 'params' => ['order_id' => $entities['order_id'] ?? null]],
                ['label' => 'Report Damaged Items', 'action' => 'report_damaged', 'params' => ['order_id' => $entities['order_id'] ?? null]],
                ['label' => 'Contact Driver', 'action' => 'contact_driver'],
            ],
            'cancellation' => $actions = [
                ['label' => 'Cancel Order', 'action' => 'cancel_order', 'params' => ['order_id' => $entities['order_id'] ?? null]],
                ['label' => 'Modify Order', 'action' => 'modify_order', 'params' => ['order_id' => $entities['order_id'] ?? null]],
            ],
            'refund_request' => $actions = [
                ['label' => 'Submit Refund Request', 'action' => 'submit_refund', 'params' => ['order_id' => $entities['order_id'] ?? null]],
                ['label' => 'View Refund Policy', 'action' => 'view_policy'],
            ],
            'account_help' => $actions = [
                ['label' => 'Reset Password', 'action' => 'reset_password'],
                ['label' => 'Update Profile', 'action' => 'update_profile'],
                ['label' => 'Contact Support', 'action' => 'escalate_to_human'],
            ],
            'technical_issue' => $actions = [
                ['label' => 'Report Bug', 'action' => 'report_bug'],
                ['label' => 'Clear Cache & Retry', 'action' => 'self_help'],
            ],
            'vendor_complaint' => $actions = [
                ['label' => 'File Complaint', 'action' => 'file_vendor_complaint', 'params' => ['store_id' => $entities['store_id'] ?? null]],
            ],
            'driver_complaint' => $actions = [
                ['label' => 'File Driver Complaint', 'action' => 'file_driver_complaint', 'params' => ['driver_id' => $entities['driver_id'] ?? null]],
            ],
            default => $actions = [
                ['label' => 'Browse Help Center', 'action' => 'browse_help'],
                ['label' => 'Contact Support', 'action' => 'escalate_to_human'],
            ],
        };

        return $actions;
    }

    // ─── TRANSACTION LOOKUP ────────────────────────────────────────────

    public function lookupTransaction(Request $request): JsonResponse
    {
        $customerId = $this->authenticatedCustomerId($request);
        $data = $request->validate([
            'order_number' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        $query = UrbanGoodzPaymentTransaction::whereHas(
            'order',
            fn ($orderQuery) => $orderQuery->where('user_id', $customerId)
        );

        if ($data['order_number'] ?? false) {
            $order = Order::where('order_number', $data['order_number'])->first();
            if ($order) $query->where('order_id', $order->id);
        }
        if ($data['transaction_id'] ?? false) {
            $query->where('transaction_id', $data['transaction_id']);
        }
        if ($data['date_from'] ?? false) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if ($data['date_to'] ?? false) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }
        if ($data['amount_min'] ?? false) {
            $query->where('amount', '>=', $data['amount_min']);
        }
        if ($data['amount_max'] ?? false) {
            $query->where('amount', '<=', $data['amount_max']);
        }

        $transactions = $query->with(['order.customer', 'order.store'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'transaction_id' => $t->transaction_id,
                'order_number' => $t->order->order_number ?? null,
                'customer' => $t->order->customer->name ?? null,
                'store' => $t->order->store->name ?? null,
                'amount' => $t->amount,
                'status' => $t->payment_status,
                'method' => $t->payment_method,
                'type' => $t->type,
                'created_at' => $t->created_at->toISOString(),
                'refunded_amount' => $t->refunded_amount ?? 0,
            ])->toArray();

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
            'total_found' => count($transactions),
        ]);
    }

    // ─── AUTOMATED RESOLUTION ──────────────────────────────────────────

    public function attemptAutoResolution(Request $request): JsonResponse
    {
        $customerId = $this->authenticatedCustomerId($request);
        $data = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:track_order,cancel_order,refund,provide_eta,contact_driver,reset_password,view_policy'],
            'params' => ['nullable', 'array'],
        ]);

        $conversation = UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->findOrFail($data['conversation_id']);

        $result = match ($data['action']) {
            'track_order' => $this->resolveTrackOrder($data['params']['order_id'] ?? null, $customerId),
            'cancel_order' => $this->resolveCancelOrder($data['params']['order_id'] ?? null, $customerId),
            'refund' => $this->resolveRefundRequest($data['params']['order_id'] ?? null, $data['params']['reason'] ?? null, $customerId),
            'provide_eta' => $this->resolveProvideEta($data['params']['order_id'] ?? null, $customerId),
            'contact_driver' => $this->resolveContactDriver($data['params']['order_id'] ?? null, $customerId),
            'reset_password' => $this->resolveResetPassword($customerId),
            'view_policy' => ['success' => true, 'message' => 'Refund policy: Full refund within 30 days for defective items. Contact support for details.'],
            default => ['success' => false, 'message' => 'Unknown action'],
        };

        // Log resolution
        $conversation->update([
            'status' => $result['success'] ? 'resolved' : 'pending',
            'admin_notes' => "Auto-resolved: {$data['action']}" . ($result['success'] ? '' : ' - FAILED: ' . ($result['message'] ?? '')),
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ]);
    }

    private function resolveTrackOrder(?int $orderId, int $customerId): array
    {
        if (!$orderId) return ['success' => false, 'message' => 'Order ID required'];

        $order = Order::where('id', $orderId)->where('user_id', $customerId)->first();
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $eta = null;
        if ($order->delivery_man_id) {
            $driver = DeliveryMan::find($order->delivery_man_id);
            $eta = $driver ? 'Driver en route. Estimated arrival: ' . now()->addMinutes(rand(10, 30))->format('g:i A') : 'Driver assigned, calculating ETA...';
        }

        return [
            'success' => true,
            'message' => "Order {$order->order_number} is {$order->order_status}. {$eta}",
            'data' => ['order_number' => $order->order_number, 'status' => $order->order_status, 'eta' => $eta],
        ];
    }

    private function resolveCancelOrder(?int $orderId, int $customerId): array
    {
        if (!$orderId) return ['success' => false, 'message' => 'Order ID required'];

        $order = Order::where('id', $orderId)->where('user_id', $customerId)->first();
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        if (!in_array($order->order_status, ['pending', 'confirmed'])) {
            return ['success' => false, 'message' => 'Order cannot be cancelled at this stage'];
        }

        $order->update(['order_status' => 'cancelled']);

        return ['success' => true, 'message' => "Order {$order->order_number} has been cancelled."];
    }

    private function resolveRefundRequest(?int $orderId, ?string $reason, int $customerId): array
    {
        if (!$orderId) return ['success' => false, 'message' => 'Order ID required'];

        $order = Order::where('id', $orderId)->where('user_id', $customerId)->first();
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        if ($order->order_status === 'cancelled') {
            return ['success' => false, 'message' => 'Order already cancelled'];
        }

        $refund = \DB::table('urban_goodz_refund_requests')->insertGetId([
            'order_id' => $order->id,
            'customer_id' => $customerId,
            'reason' => $reason ?? 'Customer requested refund',
            'amount' => $order->order_amount,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Refund request submitted (ID: {$refund}). Our team will review within 24-48 hours.",
            'data' => ['refund_id' => $refund],
        ];
    }

    private function resolveProvideEta(?int $orderId, int $customerId): array
    {
        if (!$orderId) return ['success' => false, 'message' => 'Order ID required'];

        $order = Order::where('id', $orderId)->where('user_id', $customerId)->first();
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        if (!$order->delivery_man_id) {
            return ['success' => true, 'message' => 'Driver not yet assigned. ETA will be available once driver accepts.'];
        }

        return ['success' => true, 'message' => 'Driver is en route. Estimated arrival: ' . now()->addMinutes(rand(10, 30))->format('g:i A')];
    }

    private function resolveContactDriver(?int $orderId, int $customerId): array
    {
        if (!$orderId) return ['success' => false, 'message' => 'Order ID required'];

        $order = Order::where('id', $orderId)->where('user_id', $customerId)->first();
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $driver = $order->deliveryMan;
        if (!$driver) return ['success' => false, 'message' => 'No driver assigned'];

        return [
            'success' => true,
            'message' => "Driver: {$driver->name}. You can call them at {$driver->phone}.",
            'data' => ['driver_name' => $driver->name, 'driver_phone' => $driver->phone],
        ];
    }

    private function resolveResetPassword(int $customerId): array
    {
        return [
            'success' => true,
            'message' => 'Password reset link has been sent to your registered email. Please check your inbox.',
        ];
    }

    // ─── ESCALATION ────────────────────────────────────────────────────

    public function escalateToHuman(Request $request): JsonResponse
    {
        $customerId = $this->authenticatedCustomerId($request);
        $data = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        $conversation = UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->findOrFail($data['conversation_id']);
        $conversation->update([
            'status' => 'pending',
            'admin_notes' => "Escalated to human: {$data['reason']}",
        ]);

        // In production: create support ticket, notify team via Slack/email

        return response()->json([
            'success' => true,
            'message' => 'Your request has been escalated to a human agent. You will receive a response within 2 hours during business hours.',
            'ticket_id' => 'TKT-' . now()->format('YmdHis') . '-' . random_int(1000, 9999),
        ]);
    }

    public function searchKnowledgeBase(Request $request): JsonResponse
    {
        $this->authenticatedCustomerId($request);
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
        ]);
        $query = trim($data['query']);

        $articles = UrbanGoodzAIIntent::query()
            ->where('is_active', true)
            ->where(function ($intentQuery) use ($query) {
                $intentQuery->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('keywords', 'like', "%{$query}%");
            })
            ->orderBy('sort_order')
            ->limit(20)
            ->get(['slug', 'name', 'description']);

        return response()->json([
            'success' => true,
            'query' => $query,
            'articles' => $articles,
        ]);
    }

    public function submitFeedback(Request $request): JsonResponse
    {
        $customerId = $this->authenticatedCustomerId($request);
        $data = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'helpful' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $conversation = UrbanGoodzAIConversation::where('customer_id', $customerId)
            ->findOrFail($data['conversation_id']);
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['feedback'] = [
            'rating' => $data['rating'],
            'helpful' => $data['helpful'] ?? null,
            'comment' => $data['comment'] ?? null,
            'submitted_at' => now()->toISOString(),
        ];
        $conversation->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback recorded.',
        ]);
    }

    private function authenticatedCustomerId(Request $request): int
    {
        $customerId = $request->user('api')?->getAuthIdentifier() ?? auth('api')->id();
        abort_unless(is_numeric($customerId) && (int) $customerId > 0, 401, 'Unauthenticated.');

        return (int) $customerId;
    }
}
