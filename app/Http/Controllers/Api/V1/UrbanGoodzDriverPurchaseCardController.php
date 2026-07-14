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

        $orderRequest = OrderAnywhereRequest::where('id', $requestId)
            ->where('assigned_delivery_man_id', $driverId)
            ->first();

        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or you are not assigned to this order.',
            ], 404);
        }

        if (in_array($orderRequest->status, ['completed', 'rejected', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Order is in [{$orderRequest->status}] status and cannot perform card actions.",
            ], 422);
        }

        $cardRequest = $cardService->getCardForDriver($driverId, $requestId);

        if (! $cardRequest) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active purchase card for this order.',
                'card_status' => 'none',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'card_status' => $cardRequest->card_status,
                'card_status_label' => $cardRequest->statusLabel(),
                'spending_limit' => $cardRequest->spending_limit,
                'remaining_balance' => $cardRequest->remainingBalance(),
                'currency' => $cardRequest->currency,
                'last4' => $cardRequest->last4,
                'expires_at' => $cardRequest->expires_at?->toISOString(),
                'single_use' => $cardRequest->single_use,
                'merchant_name' => $cardRequest->merchant_name,
                'allowed_merchant' => $cardRequest->allowed_merchant,
                'provider_display_url' => null,
                'instructions' => $this->getInstructions($cardRequest),
            ],
        ]);
    }

    public function authorizePurchase(Request $request, int $requestId, OrderAnywhereCardService $cardService): JsonResponse
    {
        if ($cardService->getManager()->isLiveMode()) {
            return response()->json([
                'success' => false,
                'message' => 'Manual authorization is only allowed in staged/sandbox mode.',
            ], 403);
        }

        $driverId = auth('delivery_men')->id();

        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $orderRequest = OrderAnywhereRequest::where('id', $requestId)
            ->where('assigned_delivery_man_id', $driverId)
            ->first();

        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or you are not assigned to this order.',
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
        if ($cardService->getManager()->isLiveMode()) {
            return response()->json([
                'success' => false,
                'message' => 'Manual completion is only allowed in staged/sandbox mode.',
            ], 403);
        }

        $driverId = auth('delivery_men')->id();

        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $orderRequest = OrderAnywhereRequest::where('id', $requestId)
            ->where('assigned_delivery_man_id', $driverId)
            ->first();

        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or you are not assigned to this order.',
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

    private function getInstructions(UrbanGoodzOrderAnywhereCardRequest $cardRequest): string
    {
        return match ($cardRequest->card_status) {
            'requested' => 'Card request submitted. Waiting for admin approval.',
            'provider_pending' => 'Card pending provider activation. Admin will notify when ready.',
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
