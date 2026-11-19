<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\Agent\MoniqueBusinessMetrics;
use App\Services\UrbanGoodz\AI\AIProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiChiefOfStaffController extends Controller
{
    public function index()
    {
        // Real numbers only. Anything we cannot measure is null and the page shows a dash, never an invented figure.
        $count = function (callable $fn): ?int {
            try {
                return (int) $fn();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $metrics = [];
        try {
            $metrics = app(MoniqueBusinessMetrics::class)->snapshot();
        } catch (\Throwable $e) {
            // leave empty
        }

        $telemetry = [
            'active_orders' => $count(fn () => DB::table('orders')->whereIn('order_status', ['pending', 'confirmed', 'accepted', 'processing', 'handover', 'picked_up'])->count()),
            'dispatch_minutes' => $metrics['oa_minutes_to_driver']['value'] ?? null,
            'fashion_fit_requests_today' => $count(fn () => Schema::hasTable('urban_goodz_measurement_requests')
                ? DB::table('urban_goodz_measurement_requests')->whereDate('created_at', today())->count() : null),
            'monique_alerts_waiting' => $count(fn () => \App\Models\AiMoniqueNotification::forAccount('admin', auth('admin')->id() ?? 1)->pending()->count()),
        ];

        return view('admin-views.ai-chief-of-staff.index', compact('telemetry'));
    }

    public function query(Request $request)
    {
        $request->validate(['prompt' => 'required|string|max:1000']);

        $snapshot = [];
        try {
            $snapshot = app(MoniqueBusinessMetrics::class)->snapshot();
        } catch (\Throwable $e) {
            Log::warning('Chief of staff query could not read metrics', ['error' => $e->getMessage()]);
        }

        $lines = [];
        foreach ($snapshot as $key => $m) {
            $prior = $m['prior'];
            $lines[] = "{$key}: {$m['label']} = {$m['value']} {$m['unit']}" . ($prior !== null ? " (week before: {$prior})" : '');
        }
        $numbers = $lines ? implode("\n", $lines) : '';

        $reply = null;
        if ($numbers !== '') {
            try {
                $provider = (new AIProviderManager)->resolve();
                if ($provider->isConfigured()) {
                    $result = $provider->chatResult(
                        "You are Monique, chief of staff at Urban Goodz (a delivery company). Answer the owner's question in a few direct sentences using ONLY the figures below. If the figures do not answer the question, say so plainly. Never invent a number, customer or statistic.\n\nFigures (last 7 days):\n" . $numbers,
                        (string) $request->input('prompt')
                    );
                    if (($result['success'] ?? false) && is_string($result['response'] ?? null) && trim($result['response']) !== '') {
                        $reply = trim($result['response']);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Chief of staff query could not reach the AI provider', ['error' => $e->getMessage()]);
            }
            $reply ??= "I can't reach my analysis engine right now, so here are this week's real numbers:\n" . $numbers;
        } else {
            $reply = "I don't have any business numbers to answer from right now. Check back once there's activity, or ask your developer to check the metrics feed.";
        }

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
