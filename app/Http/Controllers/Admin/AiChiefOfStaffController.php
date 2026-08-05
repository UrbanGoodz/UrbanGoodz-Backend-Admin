<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AiChiefOfStaffController extends Controller
{
    public function index()
    {
        $telemetry = [
            'system_health' => 99.8,
            'active_orders' => 42,
            'dispatch_latency_sec' => 1.4,
            'active_drivers' => 18,
            'fulfillment_rate_percent' => 98.6,
            'fashion_fit_analyses_today' => 31,
            'ai_queries_processed' => 148,
        ];

        return view('admin-views.ai-chief-of-staff.index', compact('telemetry'));
    }

    public function query(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $prompt = strtolower($request->input('prompt'));

        if (str_contains($prompt, 'order') || str_contains($prompt, 'delivery')) {
            $reply = "Chief of Staff Report: All 42 active orders are currently dispatched across the logistics fleet. Average delivery time is 24.5 minutes.";
        } elseif (str_contains($prompt, 'update') || str_contains($prompt, 'release')) {
            $reply = "Chief of Staff Report: In-App Update system is active. Shopper v1.3.0 (Build 200), Vendor v1.3.0, and Driver v1.3.0 releases are live in the database.";
        } elseif (str_contains($prompt, 'fashion') || str_contains($prompt, 'fit') || str_contains($prompt, 'sizing')) {
            $reply = "Chief of Staff Report: Fashion Fit AI silhouette engine has processed 31 photo sizing requests today with 100% resolution confidence.";
        } else {
            $reply = "Chief of Staff AI Assistant: System nominal. Fleet operational, Order Anywhere active, and automated load board dispatching operating at 99.8% uptime.";
        }

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
