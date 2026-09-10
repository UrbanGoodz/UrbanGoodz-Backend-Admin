<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\UrbanGoodz\Agent\ExecutionRouter;
use App\Services\UrbanGoodz\Agent\MoniqueEntitlementService;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VendorMoniqueController extends Controller
{
    public function __construct(
        private readonly MoniqueEntitlementService $entitlementService,
        private readonly ExecutionRouter $router,
        private readonly UrbanGoodzAIService $ai,
        private ?\App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService $attentionService = null,
        private ?\App\Services\UrbanGoodz\Agent\MoniqueTrialValueTracker $valueTracker = null,
    ) {}

    private function attention(): \App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService
    {
        return $this->attentionService ??= app(\App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService::class);
    }

    private function tracker(): \App\Services\UrbanGoodz\Agent\MoniqueTrialValueTracker
    {
        return $this->valueTracker ??= app(\App\Services\UrbanGoodz\Agent\MoniqueTrialValueTracker::class);
    }

    /**
     * Vendor App Monique Chief of Staff conversational & action endpoint.
     */
    public function chat(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated vendor access.',
            ], 401);
        }

        // 1. Entitlement verification (30-day free trial or active subscription)
        $entitlement = $this->entitlementService->checkEntitlement('vendor', $vendorId);
        if (!$entitlement['allowed']) {
            return response()->json([
                'success' => false,
                'error_code' => 'entitlement_required',
                'message' => $entitlement['message'],
                'entitlement' => $entitlement,
            ], 403);
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        $query = $validated['query'];
        $context = [
            'vendor_id' => $vendorId,
            'actor_role' => 'vendor',
            'confirmed' => (bool) ($validated['confirmed'] ?? false),
        ];

        // 2. Route vendor query through Agent Core
        $actionResult = $this->attemptVendorAction($query, $context);

        // 3. Formulate Monique's Chief of Staff response
        $vendor = Vendor::with('stores')->find($vendorId);
        $storeName = $vendor?->stores->first()?->name ?? 'Your Store';

        $systemPrompt = $this->buildVendorChiefOfStaffPrompt($vendor, $storeName, $actionResult);
        $aiReply = $this->ai->chat($systemPrompt, $query, [
            'action_result' => $actionResult,
            'vendor_id' => $vendorId,
        ]);

        return response()->json([
            'success' => true,
            'reply' => $aiReply,
            'action_result' => $actionResult,
            'entitlement' => [
                'status' => $entitlement['status'],
                'days_remaining' => $entitlement['days_remaining'],
            ],
        ]);
    }

    /**
     * Retrieve vendor's current Monique trial/subscription status.
     */
    public function getSubscription(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $entitlement = $this->entitlementService->checkEntitlement('vendor', $vendorId);
        return response()->json([
            'success' => true,
            'data' => $entitlement,
        ]);
    }

    /**
     * Cancel Monique subscription/trial for the vendor.
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $reason = $request->input('reason', 'Cancelled by vendor');
        $result = $this->entitlementService->cancelSubscription('vendor', $vendorId, $reason);

        return response()->json($result);
    }

    /**
     * Reactivate Monique service.
     */
    public function reactivateSubscription(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $result = $this->entitlementService->reactivateSubscription('vendor', $vendorId);
        return response()->json($result);
    }

    /**
     * Toggle auto-continue preference.
     */
    public function setAutoContinue(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $enable = (bool) $request->input('auto_continue', true);
        $result = $this->entitlementService->setAutoContinue('vendor', $vendorId, $enable);

        return response()->json($result);
    }

    /**
     * Proactive Morning Brief: Monique's daily operational report for the vendor.
     */
    public function morningBrief(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $brief = $this->attention()->getMorningBrief('vendor', $vendorId);
        return response()->json([
            'success' => true,
            'data' => $brief,
        ]);
    }

    /**
     * List active proactive notifications for the vendor.
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $notifications = \App\Models\AiMoniqueNotification::forAccount('vendor', $vendorId)
            ->pending()
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Handle user action on a proactive notification (e.g. 'let_monique_handle_it', 'dismiss').
     */
    public function handleNotificationAction(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $action = $request->input('action', 'let_monique_handle_it');
        $result = $this->attention()->handleNotificationAction($id, $action, [
            'vendor_id' => $vendorId,
            'actor_role' => 'vendor',
        ]);

        return response()->json($result);
    }

    /**
     * Retrieve "Monique's First 30 Days" value dashboard and proof of work.
     */
    public function trialDashboard(Request $request): JsonResponse
    {
        $vendorId = $this->authenticatedVendorId($request);
        if (!$vendorId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated vendor.'], 401);
        }

        $dashboard = $this->tracker()->getTrialDashboard('vendor', $vendorId);
        return response()->json([
            'success' => true,
            'data' => $dashboard,
        ]);
    }

    /**
     * Attempt vendor-scoped action via ExecutionRouter.
     */
    private function attemptVendorAction(string $query, array $context): ?array
    {
        $lower = strtolower($query);

        // 1. Order review / Attention check
        if (str_contains($lower, 'order') && (str_contains($lower, 'attention') || str_contains($lower, 'review') || str_contains($lower, 'pending') || str_contains($lower, 'check'))) {
            return $this->router->execute('vendor_review_orders', ['status' => 'all_active'], $context);
        }

        // 2. Sales / Performance review
        if (str_contains($lower, 'sales') || str_contains($lower, 'performance') || str_contains($lower, 'revenue') || str_contains($lower, 'how are we doing')) {
            return $this->router->execute('vendor_performance_summary', [], $context);
        }

        // 3. Operational problems / alerts
        if (str_contains($lower, 'problem') || str_contains($lower, 'alert') || str_contains($lower, 'issue') || str_contains($lower, 'delay')) {
            return $this->router->execute('vendor_operational_alerts', [], $context);
        }

        // 4. Promotions / Campaigns
        if (str_contains($lower, 'promotion') || str_contains($lower, 'discount') || str_contains($lower, 'campaign') || str_contains($lower, 'coupon')) {
            return $this->router->execute('vendor_promotions_summary', [], $context);
        }

        // 5. Item Update (price or availability)
        if (str_contains($lower, 'item') || str_contains($lower, 'product') || str_contains($lower, 'price')) {
            if (preg_match('/\b(?:item|product)\s*(?:#|id\s*)?(\d+)\b/i', $query, $matches)) {
                $itemId = (int) $matches[1];
                $params = ['item_id' => $itemId];

                if (preg_match('/(?:\$|price\s*(?:to\s*)?)(\d+(?:\.\d{1,2})?)/i', $query, $pMatches)) {
                    $params['price'] = (float) $pMatches[1];
                }

                return $this->router->execute('vendor_update_item', $params, $context);
            }
        }

        return null;
    }

    private function buildVendorChiefOfStaffPrompt(?Vendor $vendor, string $storeName, ?array $actionResult): string
    {
        $vendorName = $vendor ? "{$vendor->f_name} {$vendor->l_name}" : 'Vendor Partner';

        $prompt = "You are Monique, Chief of Staff for Urban Goodz, acting as the executive advisor to {$vendorName} for {$storeName}.
Your voice is polished, professional, intelligent, confident, and direct. You lead with conclusions and use real operational data.

RULES:
1. YOU CAN ACT. Real actions requested by the vendor are executed through the backend action layer and provided in `action_result`.
2. If `action_result.awaiting_confirmation` is true, state the exact action and ask the vendor to confirm.
3. If `action_result.verified` is true, state what was completed and confirmed against the database.
4. If `action_result.success` is false, state why it could not be performed. Never pretend something was completed when it was not.
5. Never invent sales figures, order numbers, or delivery states not present in the records.";

        if ($actionResult) {
            $prompt .= "\n\nACTION LAYER RESULT:\n" . json_encode($actionResult, JSON_PRETTY_PRINT);
        }

        return $prompt;
    }

    private function authenticatedVendorId(Request $request): ?int
    {
        $user = $request->user();
        if ($user && isset($user->vendor_id)) {
            return (int) $user->vendor_id;
        }

        if ($user && isset($user->id)) {
            return (int) $user->id;
        }

        return null;
    }
}
