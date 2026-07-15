<?php

namespace App\Services\UrbanGoodz;

use App\Models\Admin;
use App\Models\AiActionLog;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\UrbanGoodzPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportAIService
{
    private UrbanGoodzAIService $ai;
    private UrbanGoodzPaymentService $paymentService;

    public const ISSUE_TYPES = [
        'refund_request',
        'delivery_delay',
        'wrong_item',
        'damaged',
        'billing_error',
        'technical_issue',
        'complaint',
        'other',
    ];

    public const URGENCY_LEVELS = ['low', 'medium', 'high', 'critical'];

    public const RESOLUTION_ACTIONS = [
        'full_refund',
        'partial_refund',
        'credit',
        'replacement',
        'investigate_further',
    ];

    public const RESPONSE_TONES = ['professional', 'empathetic', 'firm', 'friendly', 'concise'];

    public function __construct(
        ?UrbanGoodzAIService $ai = null,
        ?UrbanGoodzPaymentService $paymentService = null
    ) {
        $this->ai = $ai ?? app(UrbanGoodzAIService::class);
        $this->paymentService = $paymentService ?? app(UrbanGoodzPaymentService::class);
    }

    // ─── AI Analysis ─────────────────────────────────────────────────────

    public function analyzeCustomerIssue(string $issueText, array $context = []): array
    {
        $systemPrompt = "You are a support issue analyst for Urban Goodz, a delivery and logistics platform.
Classify the customer support issue and extract structured data.

Return ONLY valid JSON with this exact schema:
{
  \"issue_type\": \"one of: refund_request, delivery_delay, wrong_item, damaged, billing_error, technical_issue, complaint, other\",
  \"confidence\": 0.0-1.0,
  \"urgency\": \"one of: low, medium, high, critical\",
  \"urgency_reasoning\": \"brief explanation of urgency assessment\",
  \"entities\": {
    \"order_id\": \"number or null if not mentioned\",
    \"amount\": \"dollar amount mentioned or null\",
    \"dates\": [\"any dates mentioned\"],
    \"delivery_man_name\": \"driver name if mentioned or null\",
    \"store_name\": \"store/vendor name if mentioned or null\"
  },
  \"summary\": \"one sentence summary of the issue\",
  \"suggested_resolution\": \"one of: full_refund, partial_refund, credit, replacement, investigate_further\",
  \"resolution_reasoning\": \"brief explanation for suggested resolution\"
}";
        $userMessage = "Classify this customer support issue:\n\n\"{$issueText}\"";

        if (!empty($context)) {
            $userMessage .= "\n\nAdditional context:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }

        $result = $this->ai->chat($systemPrompt, $userMessage);
        $analysis = json_decode(trim($result), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($analysis['issue_type'])) {
            Log::warning('SupportAIService: AI analysis parse failed', ['raw' => $result]);
            return $this->fallbackAnalysis($issueText);
        }

        $analysis['issue_type'] = in_array($analysis['issue_type'], self::ISSUE_TYPES)
            ? $analysis['issue_type']
            : 'other';
        $analysis['urgency'] = in_array($analysis['urgency'] ?? '', self::URGENCY_LEVELS)
            ? $analysis['urgency']
            : 'medium';
        $analysis['suggested_resolution'] = in_array($analysis['suggested_resolution'] ?? '', self::RESOLUTION_ACTIONS)
            ? $analysis['suggested_resolution']
            : 'investigate_further';
        $analysis['entities'] = $analysis['entities'] ?? [];
        $analysis['confidence'] = max(0.0, min(1.0, (float) ($analysis['confidence'] ?? 0.5)));

        return $analysis;
    }

    public function suggestRefundAction(array $issueAnalysis, array $orderData): array
    {
        $systemPrompt = "You are a refund specialist for Urban Goodz, a delivery and logistics platform.
Based on the issue analysis and order data, recommend the specific refund action.

Return ONLY valid JSON:
{
  \"action\": \"one of: full_refund, partial_refund, credit, replacement, investigate_further\",
  \"amount\": \"recommended refund amount in dollars (0 if credit/replacement/investigate)\",
  \"credit_amount\": \"store credit amount if action is credit, else 0\",
  \"reasoning\": \"detailed explanation of why this action is recommended\",
  \"conditions\": [\"any conditions or notes for the admin\"],
  \"risk_flags\": [\"any risk or fraud indicators\"],
  \"requires_senior_approval\": true/false
}";
        $userMessage = "Recommend a refund action for this case:\n\nIssue Analysis: " . json_encode($issueAnalysis, JSON_PRETTY_PRINT) . "\n\nOrder Data: " . json_encode($orderData, JSON_PRETTY_PRINT);

        $result = $this->ai->chat($systemPrompt, $userMessage);
        $suggestion = json_decode(trim($result), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($suggestion['action'])) {
            Log::warning('SupportAIService: Refund suggestion parse failed', ['raw' => $result]);
            return $this->fallbackRefundSuggestion($issueAnalysis, $orderData);
        }

        $suggestion['action'] = in_array($suggestion['action'], self::RESOLUTION_ACTIONS)
            ? $suggestion['action']
            : 'investigate_further';
        $suggestion['amount'] = max(0, (float) ($suggestion['amount'] ?? 0));
        $suggestion['credit_amount'] = max(0, (float) ($suggestion['credit_amount'] ?? 0));
        $suggestion['conditions'] = $suggestion['conditions'] ?? [];
        $suggestion['risk_flags'] = $suggestion['risk_flags'] ?? [];
        $suggestion['requires_senior_approval'] = (bool) ($suggestion['requires_senior_approval'] ?? false);

        return $suggestion;
    }

    public function generateCustomerResponse(array $issueAnalysis, string $tone = 'professional'): array
    {
        $tone = in_array($tone, self::RESPONSE_TONES) ? $tone : 'professional';

        $toneInstructions = match ($tone) {
            'professional' => 'Use a professional, business-appropriate tone. Be clear and solution-focused.',
            'empathetic' => 'Lead with empathy and understanding. Acknowledge the customer\'s frustration before offering solutions.',
            'firm' => 'Be direct and clear about policies while remaining respectful. Do not make promises outside policy.',
            'friendly' => 'Use a warm, friendly tone. Be conversational while remaining professional.',
            'concise' => 'Be brief and to the point. Eliminate unnecessary words. Get straight to the resolution.',
            default => 'Use a professional tone.',
        };

        $systemPrompt = "You are a customer support writer for Urban Goodz, a delivery and logistics platform.
Generate a customer-facing response based on the issue analysis.

Tone guidelines: {$toneInstructions}

Rules:
- Do NOT include any internal data, amounts, or reasoning in the response
- Address the customer by name if available in the analysis
- Reference the order number if available
- Provide a clear next step or resolution
- Keep it under 150 words
- Do not make commitments you cannot guarantee

Return ONLY valid JSON:
{
  \"subject\": \"email/message subject line\",
  \"body\": \"the full response message\",
  \"channel\": \"email or in_app or sms\",
  \"internal_note\": \"brief note for the admin about what was communicated\"
}";
        $userMessage = "Generate a customer response for this issue:\n\n" . json_encode($issueAnalysis, JSON_PRETTY_PRINT);

        $result = $this->ai->chat($systemPrompt, $userMessage);
        $response = json_decode(trim($result), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($response['body'])) {
            Log::warning('SupportAIService: Response generation parse failed', ['raw' => $result]);
            return [
                'subject' => 'Regarding Your Recent Order',
                'body' => 'We have received your support request and a team member will follow up with you shortly. We apologize for any inconvenience.',
                'channel' => 'email',
                'internal_note' => 'Auto-generated fallback response. AI generation failed.',
            ];
        }

        $response['channel'] = $response['channel'] ?? 'email';
        $response['internal_note'] = $response['internal_note'] ?? '';

        return $response;
    }

    // ─── Execution Methods ───────────────────────────────────────────────

    public function executeRefundAction(
        string $orderId,
        string $actionType,
        float $amount = 0,
        string $reason = '',
        int $adminId = 0
    ): array {
        $adminId = $adminId ?: Auth::id() ?? 0;

        if (!$this->validateAdmin($adminId)) {
            return ['success' => false, 'error' => 'Unauthorized: invalid admin ID.'];
        }

        if ($amount < 0) {
            return ['success' => false, 'error' => 'Refund amount cannot be negative.'];
        }

        if (!in_array($actionType, self::RESOLUTION_ACTIONS)) {
            return ['success' => false, 'error' => "Invalid action type: {$actionType}"];
        }

        $order = Order::find($orderId);
        if (!$order) {
            return ['success' => false, 'error' => "Order #{$orderId} not found."];
        }

        try {
            return DB::transaction(function () use ($order, $actionType, $amount, $reason, $adminId) {
                $beforeSnapshot = [
                    'order_status' => $order->order_status,
                    'order_amount' => $order->order_amount,
                ];

                $refundResult = match ($actionType) {
                    'full_refund' => $this->processFullRefund($order, $reason, $adminId),
                    'partial_refund' => $this->processPartialRefund($order, $amount, $reason, $adminId),
                    'credit' => $this->processCredit($order, $amount, $reason, $adminId),
                    'replacement' => $this->processReplacement($order, $reason, $adminId),
                    'investigate_further' => [
                        'success' => true,
                        'action' => 'investigate_further',
                        'message' => 'Issue flagged for further investigation.',
                    ],
                    default => ['success' => false, 'error' => 'Unhandled action type.'],
                };

                $this->logAiAction(
                    actionTaken: "Refund action: {$actionType} — {$reason}",
                    module: 'support_refund',
                    affectedUserType: 'App\Models\Order',
                    affectedUserId: (int) $order->id,
                    beforeValue: $beforeSnapshot,
                    afterValue: $refundResult,
                    reason: $reason,
                    adminId: $adminId,
                    rollbackAvailable: false,
                );

                return $refundResult;
            });
        } catch (\Exception $e) {
            Log::error('SupportAIService: Refund execution failed', [
                'order_id' => $orderId,
                'action' => $actionType,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Refund processing failed: ' . $e->getMessage()];
        }
    }

    public function executeOrderReplacement(
        string $orderId,
        string $reason = '',
        int $adminId = 0
    ): array {
        $adminId = $adminId ?: Auth::id() ?? 0;

        if (!$this->validateAdmin($adminId)) {
            return ['success' => false, 'error' => 'Unauthorized: invalid admin ID.'];
        }

        $originalOrder = Order::find($orderId);
        if (!$originalOrder) {
            return ['success' => false, 'error' => "Order #{$orderId} not found."];
        }

        if (in_array($originalOrder->order_status, ['canceled', 'refunded'])) {
            return ['success' => false, 'error' => 'Cannot replace a canceled or refunded order.'];
        }

        try {
            return DB::transaction(function () use ($originalOrder, $reason, $adminId) {
                $replacementOrder = $originalOrder->replicate();
                $replacementOrder->order_status = 'pending';
                $replacementOrder->created_at = now();
                $replacementOrder->updated_at = now();
                $replacementOrder->delivery_man_id = null;
                $replacementOrder->save();

                $details = $originalOrder->details()->get();
                foreach ($details as $detail) {
                    $newDetail = $detail->replicate();
                    $newDetail->order_id = $replacementOrder->id;
                    $newDetail->created_at = now();
                    $newDetail->updated_at = now();
                    $newDetail->save();
                }

                $beforeSnapshot = [
                    'order_status' => $originalOrder->order_status,
                    'replacement_created' => false,
                ];

                $originalOrder->order_status = 'replacement_ordered';
                $originalOrder->save();

                $afterSnapshot = [
                    'order_status' => $originalOrder->order_status,
                    'replacement_order_id' => $replacementOrder->id,
                ];

                $this->logAiAction(
                    actionTaken: "Order replacement created from #{$originalOrder->id}: {$reason}",
                    module: 'support_replacement',
                    affectedUserType: 'App\Models\Order',
                    affectedUserId: (int) $originalOrder->id,
                    beforeValue: $beforeSnapshot,
                    afterValue: $afterSnapshot,
                    reason: $reason,
                    adminId: $adminId,
                    rollbackAvailable: false,
                );

                return [
                    'success' => true,
                    'action' => 'replacement',
                    'original_order_id' => (int) $originalOrder->id,
                    'replacement_order_id' => (int) $replacementOrder->id,
                    'message' => "Replacement order #{$replacementOrder->id} created successfully.",
                ];
            });
        } catch (\Exception $e) {
            Log::error('SupportAIService: Replacement execution failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Replacement creation failed: ' . $e->getMessage()];
        }
    }

    public function executeDriverReassignment(
        string $orderId,
        string $reason = '',
        int $adminId = 0
    ): array {
        $adminId = $adminId ?: Auth::id() ?? 0;

        if (!$this->validateAdmin($adminId)) {
            return ['success' => false, 'error' => 'Unauthorized: invalid admin ID.'];
        }

        $order = Order::find($orderId);
        if (!$order) {
            return ['success' => false, 'error' => "Order #{$orderId} not found."];
        }

        if (in_array($order->order_status, ['delivered', 'canceled', 'refunded'])) {
            return ['success' => false, 'error' => 'Cannot reassign driver for a completed, canceled, or refunded order.'];
        }

        try {
            return DB::transaction(function () use ($order, $reason, $adminId) {
                $oldDriverId = $order->delivery_man_id;

                $availableDrivers = DeliveryMan::where('active', 1)
                    ->where('application_status', 'approved')
                    ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?? 1))
                    ->where('id', '!=', $oldDriverId)
                    ->orderBy('current_orders')
                    ->get();

                if ($availableDrivers->isEmpty()) {
                    return ['success' => false, 'error' => 'No available drivers for reassignment.'];
                }

                $newDriver = $availableDrivers->first();

                if ($oldDriverId) {
                    DeliveryMan::where('id', $oldDriverId)->decrement('current_orders');
                }

                $order->delivery_man_id = $newDriver->id;
                if (in_array($order->order_status, ['pending', 'confirmed'])) {
                    $order->order_status = 'accepted';
                }
                $order->save();

                $newDriver->increment('current_orders');
                $newDriver->increment('assigned_order_count');

                $beforeSnapshot = ['delivery_man_id' => $oldDriverId];
                $afterSnapshot = [
                    'delivery_man_id' => $newDriver->id,
                    'driver_name' => trim(($newDriver->f_name ?? '') . ' ' . ($newDriver->l_name ?? '')),
                ];

                $this->logAiAction(
                    actionTaken: "Driver reassigned for order #{$order->id}: {$reason}",
                    module: 'support_driver_reassignment',
                    affectedUserType: 'App\Models\DeliveryMan',
                    affectedUserId: $newDriver->id,
                    beforeValue: $beforeSnapshot,
                    afterValue: $afterSnapshot,
                    reason: $reason,
                    adminId: $adminId,
                    rollbackAvailable: true,
                );

                return [
                    'success' => true,
                    'action' => 'driver_reassignment',
                    'order_id' => (int) $order->id,
                    'old_driver_id' => $oldDriverId,
                    'new_driver_id' => $newDriver->id,
                    'new_driver_name' => $afterSnapshot['driver_name'],
                    'message' => "Driver reassigned to {$afterSnapshot['driver_name']}.",
                ];
            });
        } catch (\Exception $e) {
            Log::error('SupportAIService: Driver reassignment failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Driver reassignment failed: ' . $e->getMessage()];
        }
    }

    public function generateSupportSummary(int $adminId, string $dateRange = 'today'): array
    {
        if (!$this->validateAdmin($adminId)) {
            return ['success' => false, 'error' => 'Unauthorized: invalid admin ID.'];
        }

        $startDate = match ($dateRange) {
            'today' => now()->startOfDay(),
            'yesterday' => now()->subDay()->startOfDay()->copy()->endOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $endDate = match ($dateRange) {
            'yesterday' => now()->subDay()->endOfDay(),
            'today' => now()->endOfDay(),
            default => now()->endOfDay(),
        };

        $logs = AiActionLog::where('module', 'like', 'support_%')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        $refundLogs = $logs->filter(fn($l) => str_contains($l->module, 'refund'));
        $replacementLogs = $logs->filter(fn($l) => str_contains($l->module, 'replacement'));
        $reassignmentLogs = $logs->filter(fn($l) => str_contains($l->module, 'reassignment'));

        $totalRefundAmount = 0;
        foreach ($refundLogs as $log) {
            $after = json_decode($log->after_value, true);
            if (is_array($after) && isset($after['refund_amount'])) {
                $totalRefundAmount += (float) $after['refund_amount'];
            }
        }

        $supportData = [
            'date_range' => $dateRange,
            'period_start' => $startDate->toDateTimeString(),
            'period_end' => $endDate->toDateTimeString(),
            'total_tickets_handled' => $logs->count(),
            'refund_actions' => $refundLogs->count(),
            'replacement_actions' => $replacementLogs->count(),
            'driver_reassignments' => $reassignmentLogs->count(),
            'total_refund_amount' => round($totalRefundAmount, 2),
            'admin_id' => $adminId,
        ];

        $systemPrompt = "You are a support operations analyst for Urban Goodz, a delivery and logistics platform.
Generate a concise support activity summary based on this data. Be specific with numbers.
Include: key metrics, trends, common issues, revenue impact, and recommendations.
Format for admin dashboard display. Use bullet points for readability.";
        $userMessage = "Generate a support summary for this period:\n\n" . json_encode($supportData, JSON_PRETTY_PRINT);

        $summary = $this->ai->chat($systemPrompt, $userMessage);

        return [
            'success' => true,
            'summary' => $summary,
            'metrics' => $supportData,
        ];
    }

    public function batchProcessRefunds(array $refundRequests, int $adminId): array
    {
        if (!$this->validateAdmin($adminId)) {
            return ['success' => false, 'error' => 'Unauthorized: invalid admin ID.', 'processed' => [], 'failed' => []];
        }

        $processed = [];
        $failed = [];

        foreach ($refundRequests as $index => $request) {
            $orderId = $request['order_id'] ?? null;
            $actionType = $request['action_type'] ?? 'partial_refund';
            $amount = (float) ($request['amount'] ?? 0);
            $reason = $request['reason'] ?? 'Batch refund processing';

            if (!$orderId) {
                $failed[] = [
                    'index' => $index,
                    'order_id' => null,
                    'error' => 'Missing order_id.',
                ];
                continue;
            }

            $result = $this->executeRefundAction(
                orderId: $orderId,
                actionType: $actionType,
                amount: $amount,
                reason: $reason,
                adminId: $adminId,
            );

            if ($result['success']) {
                $processed[] = [
                    'index' => $index,
                    'order_id' => $orderId,
                    'result' => $result,
                ];
            } else {
                $failed[] = [
                    'index' => $index,
                    'order_id' => $orderId,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        $this->logAiAction(
            actionTaken: "Batch refund processed: " . count($processed) . " succeeded, " . count($failed) . " failed",
            module: 'support_batch_refund',
            affectedUserType: 'App\Models\Admin',
            affectedUserId: $adminId,
            beforeValue: ['total_requests' => count($refundRequests)],
            afterValue: ['processed' => count($processed), 'failed' => count($failed)],
            reason: "Batch of " . count($refundRequests) . " refund requests",
            adminId: $adminId,
            rollbackAvailable: false,
        );

        return [
            'success' => true,
            'total' => count($refundRequests),
            'processed_count' => count($processed),
            'failed_count' => count($failed),
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function processFullRefund(Order $order, string $reason, int $adminId): array
    {
        $refundAmount = (float) $order->order_amount;

        Refund::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'refund_amount' => $refundAmount,
            'refund_note' => $reason,
        ]);

        $order->order_status = 'refunded';
        $order->save();

        $customer = User::find($order->user_id);
        if ($customer) {
            $customer->increment('balance', $refundAmount);
        }

        return [
            'success' => true,
            'action' => 'full_refund',
            'refund_amount' => $refundAmount,
            'order_id' => (int) $order->id,
            'message' => "Full refund of \${$refundAmount} processed.",
        ];
    }

    private function processPartialRefund(Order $order, float $amount, string $reason, int $adminId): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Partial refund amount must be greater than zero.'];
        }

        if ($amount > (float) $order->order_amount) {
            return ['success' => false, 'error' => "Partial refund amount \${$amount} exceeds order total \${$order->order_amount}."];
        }

        Refund::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'refund_amount' => $amount,
            'refund_note' => $reason,
        ]);

        $customer = User::find($order->user_id);
        if ($customer) {
            $customer->increment('balance', $amount);
        }

        return [
            'success' => true,
            'action' => 'partial_refund',
            'refund_amount' => $amount,
            'order_id' => (int) $order->id,
            'message' => "Partial refund of \${$amount} processed.",
        ];
    }

    private function processCredit(Order $order, float $amount, string $reason, int $adminId): array
    {
        if ($amount <= 0) {
            $amount = (float) $order->order_amount;
        }

        $customer = User::find($order->user_id);
        if (!$customer) {
            return ['success' => false, 'error' => 'Customer not found for credit.'];
        }

        $customer->increment('balance', $amount);

        return [
            'success' => true,
            'action' => 'credit',
            'credit_amount' => $amount,
            'user_id' => (int) $customer->id,
            'order_id' => (int) $order->id,
            'message' => "\${$amount} store credit issued to customer.",
        ];
    }

    private function processReplacement(Order $order, string $reason, int $adminId): array
    {
        return $this->executeOrderReplacement(
            orderId: (string) $order->id,
            reason: $reason,
            adminId: $adminId,
        );
    }

    private function fallbackAnalysis(string $issueText): array
    {
        return [
            'issue_type' => 'other',
            'confidence' => 0.1,
            'urgency' => 'medium',
            'urgency_reasoning' => 'AI analysis unavailable; defaulting to medium urgency for manual review.',
            'entities' => ['order_id' => null, 'amount' => null, 'dates' => [], 'delivery_man_name' => null, 'store_name' => null],
            'summary' => 'Issue requires manual review. AI could not parse the input.',
            'suggested_resolution' => 'investigate_further',
            'resolution_reasoning' => 'Fallback: AI analysis failed, manual review required.',
        ];
    }

    private function fallbackRefundSuggestion(array $issueAnalysis, array $orderData): array
    {
        $orderAmount = (float) ($orderData['order_amount'] ?? 0);
        return [
            'action' => 'investigate_further',
            'amount' => 0,
            'credit_amount' => 0,
            'reasoning' => 'AI refund suggestion unavailable. Recommend manual review by a senior admin.',
            'conditions' => ['Requires manual review'],
            'risk_flags' => [],
            'requires_senior_approval' => true,
        ];
    }

    private function validateAdmin(int $adminId): bool
    {
        if ($adminId <= 0) {
            return false;
        }
        return Admin::where('id', $adminId)->exists();
    }

    private function logAiAction(
        string $actionTaken,
        ?string $module = null,
        ?string $affectedUserType = null,
        ?int $affectedUserId = null,
        mixed $beforeValue = null,
        mixed $afterValue = null,
        ?string $reason = null,
        int $adminId = 0,
        bool $rollbackAvailable = false,
    ): void {
        AiActionLog::create([
            'action_taken' => $actionTaken,
            'module' => $module,
            'affected_user_type' => $affectedUserType,
            'affected_user_id' => $affectedUserId,
            'before_value' => $beforeValue ? (is_string($beforeValue) ? $beforeValue : json_encode($beforeValue)) : null,
            'after_value' => $afterValue ? (is_string($afterValue) ? $afterValue : json_encode($afterValue)) : null,
            'reason' => $reason,
            'automation_mode' => 'ai_support',
            'recommendation_id' => null,
            'approved_by' => $adminId ?: null,
            'rollback_available' => $rollbackAvailable,
        ]);
    }
}
