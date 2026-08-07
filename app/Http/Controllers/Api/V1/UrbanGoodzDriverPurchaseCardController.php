<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Services\OrderAnywhereCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrbanGoodzDriverPurchaseCardController extends Controller
{
    public function getCard(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        $driverId = auth('delivery_men')->id();

        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $orderRequest = OrderAnywhereRequest::find($requestId);

        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }
        if ((int) $orderRequest->assigned_delivery_man_id !== (int) $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not the currently assigned driver.',
            ], 403);
        }

        $cardRequest = $cardService->getCardStatusForDriver($driverId, $requestId);

        if (! $cardRequest) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active purchase card for this order.',
                'card_status' => 'none',
                'workflow_status' => $this->workflowStatus($orderRequest, null),
                'provider_configuration_status' => app(\App\Services\Payments\CardIssuingProviderManager::class)
                    ->configurationStatus(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'card_status' => $cardRequest->card_status,
                'card_status_label' => $cardRequest->statusLabel(),
                'provider' => $cardRequest->provider,
                'spending_limit' => $cardRequest->spending_limit,
                'remaining_balance' => $cardRequest->remainingBalance(),
                'currency' => $cardRequest->currency,
                'last4' => $cardRequest->last4,
                'expires_at' => $cardRequest->expires_at?->toISOString(),
                'payment_count_limit' => $cardRequest->payment_count_limit,
                'merchant_name' => $cardRequest->merchant_name,
                'allowed_merchant' => $cardRequest->allowed_merchant,
                'provider_display_url' => null,
                'secure_reveal_available' => $cardRequest->provider === 'stripe_issuing'
                    && in_array($cardRequest->card_status, ['issued', 'active', 'authorized'], true),
                'receipt_submitted' => $cardRequest->receipt_submitted_at !== null,
                'receipt_total' => $cardRequest->receipt_total,
                'failure_category' => $cardRequest->failure_category,
                'provider_configuration_status' => $cardRequest->provider_configuration_status,
                'workflow_status' => $this->workflowStatus($orderRequest, $cardRequest),
                'instructions' => $this->getInstructions($cardRequest),
            ],
        ]);
    }

    public function authorizePurchase(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        $driverId = auth('delivery_men')->id();

        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        [, $orderRequest] = $this->assignedOrder($requestId);

        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        if (in_array($orderRequest->status, ['completed', 'rejected', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Order is in [{$orderRequest->status}] status and cannot perform card actions.",
            ], 422);
        }

        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::findUsableForDriver($driverId, $requestId);

        if (! $cardRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No active purchase card available for this order.',
            ], 404);
        }
        if ($cardRequest->provider !== 'staged_test_issuing') {
            return response()->json([
                'success' => false,
                'message' => 'Stripe Issuing authorizations are confirmed by the provider webhook.',
            ], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'merchant_category_code' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $cardRequest = $cardService->authorizeCardPurchase($cardRequest, $data);

            return response()->json([
                'success' => true,
                'message' => 'Purchase authorized.',
                'data' => [
                    'card_status' => $cardRequest->card_status,
                    'authorized_amount' => $cardRequest->authorized_amount,
                    'remaining_balance' => $cardRequest->remainingBalance(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function completePurchase(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        $driverId = auth('delivery_men')->id();

        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        [, $orderRequest] = $this->assignedOrder($requestId);

        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        if (in_array($orderRequest->status, ['completed', 'rejected', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Order is in [{$orderRequest->status}] status and cannot perform card actions.",
            ], 422);
        }

        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::where('delivery_man_id', $driverId)
            ->where('order_anywhere_request_id', $requestId)
            ->where('card_status', 'authorized')
            ->first();

        if (! $cardRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No authorized purchase to complete.',
            ], 404);
        }
        if ($cardRequest->provider !== 'staged_test_issuing') {
            return response()->json([
                'success' => false,
                'message' => 'Stripe Issuing purchases are completed by the provider webhook.',
            ], 403);
        }

        $data = $request->validate([
            'captured_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $cardRequest = $cardService->completeCardPurchase($cardRequest, (float) $data['captured_amount']);

            return response()->json([
                'success' => true,
                'message' => 'Purchase completed.',
                'data' => [
                    'card_status' => $cardRequest->card_status,
                    'captured_amount' => $cardRequest->captured_amount,
                    'remaining_balance' => $cardRequest->remainingBalance(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function uploadReceipt(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        [$driverId, $orderRequest] = $this->assignedOrder($requestId);
        if (! $driverId || ! $orderRequest) {
            return response()->json(['success' => false, 'message' => 'Order not found or not assigned to you.'], 404);
        }
        $data = $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'receipt_total' => ['required', 'numeric', 'min:0.01'],
            'receipt_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $card = $cardService->getCardStatusForDriver($driverId, $requestId);
        if (! $card) {
            return response()->json(['success' => false, 'message' => 'Purchase card not found.'], 404);
        }
        $card = $cardService->attachReceipt(
            $card,
            $request->file('receipt'),
            (float) $data['receipt_total'],
            $data['receipt_notes'] ?? null
        );
        return response()->json([
            'success' => true,
            'message' => 'Receipt submitted.',
            'data' => ['receipt_submitted' => true, 'receipt_total' => $card->receipt_total],
        ]);
    }

    public function reportFailure(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        [$driverId, $orderRequest] = $this->assignedOrder($requestId);
        if (! $driverId || ! $orderRequest) {
            return response()->json(['success' => false, 'message' => 'Order not found or not assigned to you.'], 404);
        }
        $data = $request->validate([
            'category' => ['required', 'in:declined,reveal_failed,merchant_restricted,expired,damaged,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $card = $cardService->getCardStatusForDriver($driverId, $requestId);
        if (! $card) {
            return response()->json(['success' => false, 'message' => 'Purchase card not found.'], 404);
        }
        $cardService->reportFailure($card, $data['category'], $data['details'] ?? null);
        return response()->json(['success' => true, 'message' => 'Card failure reported.']);
    }

    public function secureReveal(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        [$driverId, $orderRequest] = $this->assignedOrder($requestId);
        if (! $driverId || ! $orderRequest) {
            return response()->json(['success' => false, 'message' => 'Order not found or not assigned to you.'], 404);
        }
        $card = $cardService->getCardStatusForDriver($driverId, $requestId);
        if (! $card) {
            return response()->json(['success' => false, 'message' => 'Purchase card not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $cardService->createRevealSession($card)]);
    }

    private function assignedOrder(int $requestId): array
    {
        $driverId = auth('delivery_men')->id();
        if (! $driverId) {
            return [null, null];
        }
        $order = OrderAnywhereRequest::find($requestId);
        if ($order && (int) $order->assigned_delivery_man_id !== (int) $driverId) {
            abort(403, 'You are not the currently assigned driver.');
        }
        if ($order && in_array($order->status, ['completed', 'rejected', 'cancelled'], true)) {
            abort(422, "Order is in [{$order->status}] status.");
        }
        return [$driverId, $order];
    }

    private function workflowStatus(
        OrderAnywhereRequest $order,
        ?UrbanGoodzOrderAnywhereCardRequest $card
    ): string {
        if (! $order->assigned_delivery_man_id) {
            return 'awaiting_driver_assignment';
        }
        if (! in_array($order->payment_status, ['authorized', 'captured'], true)) {
            return 'awaiting_customer_payment';
        }
        if (! $card) {
            return 'issuance_pending';
        }

        return match ($card->card_status) {
            'awaiting_provider_configuration' => 'awaiting_provider_configuration',
            'requested', 'provider_pending', 'issuance_pending' => 'issuance_pending',
            'issuance_retry_pending' => 'support_required',
            'issued', 'active' => 'card_available',
            'authorized' => 'purchase_authorized',
            'used' => $card->receipt_submitted_at ? 'reconciliation_pending' : 'receipt_required',
            'reconciled' => 'reconciled',
            'frozen' => 'frozen',
            'cancelled' => 'canceled',
            'expired' => 'expired',
            'failed' => 'issuance_failed',
            'revocation_pending' => 'support_required',
            default => 'support_required',
        };
    }

    private function getInstructions(UrbanGoodzOrderAnywhereCardRequest $cardRequest): string
    {
        return match ($cardRequest->card_status) {
            'requested' => 'Card request submitted. Waiting for admin approval.',
            'provider_pending' => 'Card pending provider activation. Admin will notify when ready.',
            'awaiting_provider_configuration' => 'Card automation is ready. The issuing provider is not configured yet.',
            'issuance_pending' => 'Your purchase card is being issued automatically.',
            'issuance_retry_pending' => 'Automatic issuance will retry. Contact support if this persists.',
            'issued' => 'Card is ready for use. You may proceed with the purchase.',
            'active' => 'Card is active. Use it for the assigned purchase.',
            'authorized' => 'Purchase in progress. Complete the transaction and report back.',
            'used' => 'Card has been used. Purchase complete.',
            'frozen' => 'Card is frozen. Contact admin for assistance.',
            'expired' => 'Card has expired. Contact admin for a new card.',
            'cancelled' => 'Card has been cancelled.',
            'failed' => 'Card request failed. Contact admin.',
            'reconciled' => 'Card has been reconciled. Thank you.',
            default => 'Contact admin for assistance.',
        };
    }
}
