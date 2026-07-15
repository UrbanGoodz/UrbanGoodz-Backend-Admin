<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\UrbanGoodzRefundRequest;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzFraudFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FraudDetectionController extends Controller
{
    // ─── TRANSACTION SCANNING ──────────────────────────────────────────

    public function scanTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer'],
        ]);

        $transaction = OrderTransaction::with('order', 'order.customer', 'order.vendor')->findOrFail($data['transaction_id']);

        $flags = [];
        $riskScore = 0;

        // Check for unusual amount
        $avgAmount = OrderTransaction::where('order_id', $transaction->order_id)
            ->where('id', '!=', $transaction->id)
            ->avg('amount') ?? 0;

        if ($avgAmount > 0 && $transaction->amount > $avgAmount * 3) {
            $flags[] = [
                'type' => 'unusual_amount',
                'severity' => 'high',
                'message' => "Transaction amount \${$transaction->amount} is 3x the average (\${$avgAmount})",
                'details' => ['amount' => $transaction->amount, 'avg_amount' => $avgAmount],
            ];
            $riskScore += 30;
        }

        // Check for rapid successive transactions
        $recentCount = OrderTransaction::where('order_id', $transaction->order_id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentCount > 3) {
            $flags[] = [
                'type' => 'rapid_transactions',
                'severity' => 'medium',
                'message' => "{$recentCount} transactions in last 10 minutes",
                'details' => ['count' => $recentCount, 'window_minutes' => 10],
            ];
            $riskScore += 20;
        }

        // Check for mismatched payment method
        $order = $transaction->order;
        if ($order && $order->payment_method !== $transaction->payment_method) {
            $flags[] = [
                'type' => 'payment_method_mismatch',
                'severity' => 'medium',
                'message' => "Order payment method ({$order->payment_method}) differs from transaction ({$transaction->payment_method})",
            ];
            $riskScore += 15;
        }

        // Check for high-risk payment methods
        $highRiskMethods = ['crypto', 'gift_card', 'prepaid_card'];
        if (in_array(strtolower($transaction->payment_method ?? ''), $highRiskMethods)) {
            $flags[] = [
                'type' => 'high_risk_payment_method',
                'severity' => 'high',
                'message' => "High-risk payment method used: {$transaction->payment_method}",
            ];
            $riskScore += 25;
        }

        // Check for refund abuse pattern
        $refundCount = \App\Models\UrbanGoodzRefundRequest::where('customer_id', $order->user_id ?? 0)
            ->where('status', 'approved')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($refundCount > 5) {
            $flags[] = [
                'type' => 'refund_abuse',
                'severity' => 'high',
                'message' => "Customer has {$refundCount} approved refunds in last 30 days",
                'details' => ['refund_count_30d' => $refundCount],
            ];
            $riskScore += 30;
        }

        // Check for chargeback history
        $chargebackCount = OrderTransaction::where('order_id', $transaction->order_id)
            ->where('status', 'chargeback')
            ->count();

        if ($chargebackCount > 0) {
            $flags[] = [
                'type' => 'chargeback_history',
                'severity' => 'critical',
                'message' => "Order has {$chargebackCount} previous chargeback(s)",
                'details' => ['chargeback_count' => $chargebackCount],
            ];
            $riskScore += 40;
        }

        // Determine overall risk level
        $riskLevel = match (true) {
            $riskScore >= 70 => 'critical',
            $riskScore >= 40 => 'high',
            $riskScore >= 20 => 'medium',
            default => 'low',
        };

        // Store flags
        foreach ($flags as $flag) {
            UrbanGoodzFraudFlag::create([
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'flag_type' => $flag['type'],
                'severity' => $flag['severity'],
                'message' => $flag['message'],
                'details' => $flag['details'] ?? null,
                'risk_score' => $riskScore,
                'status' => 'open',
            ]);
        }

        return response()->json([
            'success' => true,
            'transaction_id' => $transaction->id,
            'risk_score' => min(100, $riskScore),
            'risk_level' => $riskLevel,
            'flags' => $flags,
            'recommendation' => $this->getRecommendation($riskLevel, $flags),
        ]);
    }

    // ─── ACCOUNT SCANNING ──────────────────────────────────────────────

    public function scanAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string', 'in:customer,vendor,driver'],
            'entity_id' => ['required', 'integer'],
        ]);

        $model = match ($data['entity_type']) {
            'customer' => User::class,
            'vendor' => Vendor::class,
            'driver' => DeliveryMan::class,
            default => null,
        };

        abort_unless($model, 422, 'Invalid entity type');

        $entity = $model::findOrFail($data['entity_id']);
        $flags = [];
        $riskScore = 0;

        // Check for duplicate accounts (same phone/email)
        if ($data['entity_type'] === 'customer') {
            $duplicates = User::where('phone', $entity->phone)
                ->orWhere('email', $entity->email)
                ->where('id', '!=', $entity->id)
                ->count();

            if ($duplicates > 0) {
                $flags[] = [
                    'type' => 'duplicate_account',
                    'severity' => 'high',
                    'message' => "{$duplicates} other account(s) with same phone/email",
                ];
                $riskScore += 30;
            }

            // Check for rapid order placement
            $recentOrders = Order::where('user_id', $entity->id)
                ->where('created_at', '>=', now()->subHours(1))
                ->count();

            if ($recentOrders > 5) {
                $flags[] = [
                    'type' => 'rapid_ordering',
                    'severity' => 'medium',
                    'message' => "{$recentOrders} orders placed in last hour",
                ];
                $riskScore += 15;
            }
        }

        if ($data['entity_type'] === 'vendor') {
            // Check for fake reviews
            $reviewStats = \App\Models\Review::where('store_id', $entity->stores->pluck('id'))
                ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating, SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as low_count')
                ->first();

            if ($reviewStats && $reviewStats->total > 10) {
                $lowRatio = $reviewStats->low_count / $reviewStats->total;
                if ($lowRatio > 0.4 && $reviewStats->avg_rating > 4.5) {
                    $lowPct = round($lowRatio * 100);
                    $flags[] = [
                        'type' => 'suspicious_reviews',
                        'severity' => 'high',
                        'message' => "High rating ({$reviewStats->avg_rating}) with {$lowPct}% low ratings - possible review manipulation",
                    ];
                    $riskScore += 25;
                }
            }

            // Check for unusual pricing
            $avgPrice = \App\Models\OrderDetail::whereIn('order_id', 
                Order::whereIn('store_id', $entity->stores->pluck('id'))->pluck('id')
            )->avg('price') ?? 0;

            $anomalousItems = \App\Models\OrderDetail::whereIn('order_id',
                Order::whereIn('store_id', $entity->stores->pluck('id'))->pluck('id')
            )->where('price', '>', $avgPrice * 3)->count();

            if ($anomalousItems > 3) {
                $flags[] = [
                    'type' => 'price_manipulation',
                    'severity' => 'medium',
                    'message' => "{$anomalousItems} items priced 3x above store average",
                ];
                $riskScore += 20;
            }
        }

        if ($data['entity_type'] === 'driver') {
            // Check for off-route activity
            $recentLocations = \App\Models\DriverLocationTrack::where('dm_id', $entity->id)
                ->where('created_at', '>=', now()->subHours(2))
                ->get();

            if ($recentLocations->count() > 1) {
                $route = \App\Models\UrbanGoodzRouteBatch::where('delivery_man_id', $entity->id)
                    ->where('status', 'in_progress')
                    ->first();

                if ($route) {
                    $offRouteCount = 0;
                    foreach ($recentLocations as $loc) {
                        $distance = $this->haversine(
                            $loc->latitude, $loc->longitude,
                            $route->start_latitude ?? 0, $route->start_longitude ?? 0
                        );
                        if ($distance > 5) $offRouteCount++; // 5 miles off route
                    }

                    if ($offRouteCount > 2) {
                        $flags[] = [
                            'type' => 'off_route_activity',
                            'severity' => 'high',
                            'message' => "Driver {$offRouteCount} times more than 5 miles off assigned route in last 2 hours",
                        ];
                        $riskScore += 35;
                    }
                }
            }

            // Check for duplicate proof images
            $duplicateProofs = \DB::table('urban_goodz_route_packages')
                ->where('delivery_man_id', $entity->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('proof_image, COUNT(*) as count')
                ->groupBy('proof_image')
                ->having('count', '>', 1)
                ->count();

            if ($duplicateProofs > 0) {
                $flags[] = [
                    'type' => 'duplicate_proof_images',
                    'severity' => 'critical',
                    'message' => "{$duplicateProofs} duplicate delivery proof images detected in last 7 days",
                ];
                $riskScore += 50;
            }
        }

        // Store flags
        foreach ($flags as $flag) {
            UrbanGoodzFraudFlag::create([
                'entity_type' => $data['entity_type'],
                'entity_id' => $entity->id,
                'flag_type' => $flag['type'],
                'severity' => $flag['severity'],
                'message' => $flag['message'],
                'risk_score' => $riskScore,
                'status' => 'open',
            ]);
        }

        return response()->json([
            'success' => true,
            'entity_type' => $data['entity_type'],
            'entity_id' => $entity->id,
            'risk_score' => min(100, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'flags' => $flags,
        ]);
    }

    // ─── FLAGS MANAGEMENT ──────────────────────────────────────────────

    public function getFlags(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['nullable', 'string'],
            'entity_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:open,reviewed,resolved,dismissed'],
            'severity' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = UrbanGoodzFraudFlag::with(['entity' => fn($q) => $q->morphTo()]);

        if ($data['entity_type'] ?? false) {
            $query->where('entity_type', $data['entity_type']);
        }
        if ($data['entity_id'] ?? false) {
            $query->where('entity_id', $data['entity_id']);
        }
        if ($data['status'] ?? false) {
            $query->where('status', $data['status']);
        }
        if ($data['severity'] ?? false) {
            $query->where('severity', $data['severity']);
        }

        $flags = $query->orderByDesc('created_at')
            ->limit($data['limit'] ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'flags' => $flags->map(fn($f) => [
                'id' => $f->id,
                'entity_type' => $f->entity_type,
                'entity_id' => $f->entity_id,
                'flag_type' => $f->flag_type,
                'severity' => $f->severity,
                'message' => $f->message,
                'details' => $f->details,
                'status' => $f->status,
                'created_at' => $f->created_at,
                'resolved_at' => $f->resolved_at,
                'resolved_by' => $f->resolved_by,
            ]),
        ]);
    }

    public function reviewFlag(Request $request): JsonResponse
    {
        $data = $request->validate([
            'flag_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:resolve,dismiss,escalate'],
            'notes' => ['nullable', 'string'],
        ]);

        $flag = UrbanGoodzFraudFlag::findOrFail($data['flag_id']);

        $flag->update([
            'status' => $data['action'] === 'resolve' ? 'resolved' : ($data['action'] === 'dismiss' ? 'dismissed' : 'escalated'),
            'resolved_at' => now(),
            'resolved_by' => auth('admin')->id() ?? null,
            'resolution_notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Flag {$data['action']}d",
            'flag' => $flag->fresh(),
        ]);
    }

    public function getRiskScore(Request $request): JsonResponse
    {
        $entityType = $request->route('entity_type');
        $entityId = $request->route('entity_id');

        $flags = UrbanGoodzFraudFlag::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'open')
            ->get();

        $riskScore = $flags->sum('risk_score') ?? 0;
        $riskLevel = $this->getRiskLevel($riskScore);

        return response()->json([
            'success' => true,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'risk_score' => min(100, $riskScore),
            'risk_level' => $riskLevel,
            'open_flags_count' => $flags->count(),
        ]);
    }

    public function getDashboard(Request $request): JsonResponse
    {
        $stats = [
            'total_open_flags' => UrbanGoodzFraudFlag::where('status', 'open')->count(),
            'critical_flags' => UrbanGoodzFraudFlag::where('status', 'open')->where('severity', 'critical')->count(),
            'high_flags' => UrbanGoodzFraudFlag::where('status', 'open')->where('severity', 'high')->count(),
            'flags_by_type' => UrbanGoodzFraudFlag::where('status', 'open')
                ->groupBy('flag_type')
                ->selectRaw('flag_type, COUNT(*) as count')
                ->pluck('count', 'flag_type')
                ->toArray(),
            'flags_by_entity' => UrbanGoodzFraudFlag::where('status', 'open')
                ->groupBy('entity_type')
                ->selectRaw('entity_type, COUNT(*) as count')
                ->pluck('count', 'entity_type')
                ->toArray(),
            'recent_flags' => UrbanGoodzFraudFlag::with('entity')
                ->where('status', 'open')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn($f) => [
                    'id' => $f->id,
                    'type' => $f->flag_type,
                    'severity' => $f->severity,
                    'message' => $f->message,
                    'entity' => $f->entity ? "{$f->entity_type}:{$f->entity_id}" : null,
                    'created_at' => $f->created_at->diffForHumans(),
                ]),
        ];

        return response()->json([
            'success' => true,
            'dashboard' => $stats,
        ]);
    }

    private function getRecommendation(string $level, array $flags): string
    {
        return match ($level) {
            'critical' => 'IMMEDIATE ACTION REQUIRED: Block transaction, freeze account, escalate to security team immediately.',
            'high' => 'HIGH RISK: Review manually within 1 hour. Consider blocking transaction and flagging account for review.',
            'medium' => 'MODERATE RISK: Review within 4 hours. Monitor account activity closely.',
            'low' => 'LOW RISK: Log for pattern analysis. No immediate action required.',
        };
    }

    private function getRiskLevel(int $score): string
    {
        if ($score >= 70) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 20) return 'medium';
        return 'low';
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $h = sin($dlat/2)**2 + cos($lat1)*cos($lat2)*sin($dlon/2)**2;
        return 3959 * 2 * asin(sqrt($h)); // miles
    }
}