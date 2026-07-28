<?php

namespace App\Http\Controllers\Api\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzCompensationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Driver-safe compensation API.
 *
 * Exposes only non-confidential fields. Internal margins, platform splits,
 * and deficit details are never sent to the driver app.
 */
class UrbanGoodzCompensationApiController extends Controller
{
    /**
     * GET /api/urban-goodz/driver/compensation/{calculationId}
     */
    public function show(int $calculationId): JsonResponse
    {
        $result = UrbanGoodzCompensationResult::find($calculationId);

        if ($result === null) {
            return response()->json(['error' => 'Calculation not found'], 404);
        }

        return response()->json($this->safePayload($result));
    }

    /**
     * GET /api/urban-goodz/driver/compensation/latest
     *
     * The driver's most recent finalized or estimated calculation.
     */
    public function latest(Request $request): JsonResponse
    {
        $driverId = $request->input('driver_id');

        if ($driverId === null) {
            return response()->json(['error' => 'Driver identification required'], 401);
        }

        $result = UrbanGoodzCompensationResult::query()
            ->where('driver_id', $driverId)
            ->orderByDesc('id')
            ->first();

        if ($result === null) {
            return response()->json(['error' => 'No compensation records found'], 404);
        }

        return response()->json($this->safePayload($result));
    }

    /**
     * GET /api/urban-goodz/driver/compensation/history
     *
     * Paginated compensation history for the driver.
     */
    public function history(Request $request): JsonResponse
    {
        $driverId = $request->input('driver_id');

        if ($driverId === null) {
            return response()->json(['error' => 'Driver identification required'], 401);
        }

        $results = UrbanGoodzCompensationResult::query()
            ->where('driver_id', $driverId)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => array_map([$this, 'safePayload'], $results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    private function safePayload(UrbanGoodzCompensationResult $result): array
    {
        $splits = (array) $result->splits;

        return [
            'calculation_id' => $result->id,
            'status' => $result->is_final ? 'finalized' : 'estimate',
            'currency' => 'USD',
            'driver_amount_cents' => $result->driver_cents,
            'pass_through_cents' => $splits['driver_pass_through_cents'] ?? 0,
            'tip_cents' => $this->tipCents($result),
            'adjustment_cents' => $this->adjustmentCents($result),
            'total_payable_cents' => $result->driver_cents,
            'rule_id' => $result->rule_id,
            'rule_version' => $result->rule_version,
            'work_type' => $result->context['work_type'] ?? null,
            'components' => $this->componentSummary($result),
            'explanation' => $result->explanation,
            'finalized_at' => $result->finalized_at?->toIso8601String(),
            'payout_status' => $result->is_final ? 'eligible' : 'pending',
        ];
    }

    private function tipCents(UrbanGoodzCompensationResult $result): int
    {
        $breakdown = (array) $result->breakdown;
        $lines = $breakdown['lines'] ?? [];
        $total = 0;

        foreach ($lines as $line) {
            if (($line['code'] ?? '') === 'tips') {
                $total += $line['amount_cents'] ?? 0;
            }
        }

        return $total;
    }

    private function adjustmentCents(UrbanGoodzCompensationResult $result): int
    {
        $context = (array) $result->context;
        return (int) ($context['adjustment_cents'] ?? 0);
    }

    private function componentSummary(UrbanGoodzCompensationResult $result): array
    {
        $breakdown = (array) $result->breakdown;
        $lines = $breakdown['lines'] ?? [];
        $summary = [];

        foreach ($lines as $line) {
            $code = $line['code'] ?? 'unknown';
            // Omit internal/confidential component details
            if (in_array($code, ['vehicle_multiplier', 'peak_surge'], true)) {
                continue;
            }
            $summary[] = [
                'code' => $code,
                'label' => $line['label'] ?? $code,
                'amount_cents' => $line['amount_cents'] ?? 0,
            ];
        }

        return $summary;
    }
}
