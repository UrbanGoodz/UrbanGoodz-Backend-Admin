<?php

namespace App\Services\UrbanGoodz\Agent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The facts Monique reasons from: last 7 days versus the 7 before, computed straight from the
 * database. Every number she is allowed to cite comes from here, so nothing she says about the
 * business can be invented. A metric that cannot be computed is simply absent.
 */
class MoniqueBusinessMetrics
{
    /** @return array<string, array{label: string, value: float, prior: float|null, unit: string}> */
    public function snapshot(): array
    {
        $m = [];
        $now = now();
        $weekAgo = $now->copy()->subDays(7);
        $twoWeeksAgo = $now->copy()->subDays(14);

        $put = function (string $key, string $label, string $unit, ?float $value, ?float $prior = null) use (&$m) {
            if ($value !== null) {
                $m[$key] = ['label' => $label, 'value' => round($value, 2), 'prior' => $prior === null ? null : round($prior, 2), 'unit' => $unit];
            }
        };
        $guard = function (callable $fn, string $what) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::warning("Monique metrics: {$what} unavailable", ['error' => $e->getMessage()]);
            }
        };
        $between = fn ($q, string $col, $from, $to) => $q->where($col, '>=', $from)->where($col, '<', $to);

        $guard(function () use ($put, $between, $weekAgo, $twoWeeksAgo, $now) {
            if (! Schema::hasTable('order_anywhere_requests')) {
                return;
            }
            $t = fn () => DB::table('order_anywhere_requests');
            $created = fn ($from, $to) => $between($t(), 'created_at', $from, $to);
            $put('oa_requests', 'Order Anywhere requests', 'count', $created($weekAgo, $now)->count(), $created($twoWeeksAgo, $weekAgo)->count());

            $done = fn ($from, $to) => $between($t()->whereIn('status', ['delivered', 'completed']), 'created_at', $from, $to);
            $put('oa_completed', 'Order Anywhere completed', 'count', $done($weekAgo, $now)->count(), $done($twoWeeksAgo, $weekAgo)->count());

            $cancelled = fn ($from, $to) => $between($t()->where('status', 'cancelled'), 'created_at', $from, $to);
            $put('oa_cancelled', 'Order Anywhere cancelled', 'count', $cancelled($weekAgo, $now)->count(), $cancelled($twoWeeksAgo, $weekAgo)->count());

            $revenue = fn ($from, $to) => (float) $between($t()->whereIn('status', ['delivered', 'completed']), 'created_at', $from, $to)->sum('urban_goodz_revenue');
            $put('oa_platform_revenue', 'Order Anywhere platform revenue', 'usd', $revenue($weekAgo, $now), $revenue($twoWeeksAgo, $weekAgo));

            $aov = fn ($from, $to) => (float) ($between($t()->whereIn('status', ['delivered', 'completed']), 'created_at', $from, $to)->avg('captured_amount') ?? 0);
            $put('oa_avg_order_value', 'Order Anywhere average order value', 'usd', $aov($weekAgo, $now), $aov($twoWeeksAgo, $weekAgo));

            $wait = function ($from, $to) use ($t, $between) {
                $rows = $between($t()->whereNotNull('driver_accepted_at'), 'created_at', $from, $to)->get(['created_at', 'driver_accepted_at']);
                if ($rows->isEmpty()) {
                    return null;
                }

                return $rows->avg(fn ($r) => max(0, (strtotime($r->driver_accepted_at) - strtotime($r->created_at)) / 60));
            };
            $w = $wait($weekAgo, $now);
            if ($w !== null) {
                $put('oa_minutes_to_driver', 'Order Anywhere minutes until a driver accepts', 'minutes', $w, $wait($twoWeeksAgo, $weekAgo));
            }
        }, 'order anywhere');

        $guard(function () use ($put, $between, $weekAgo, $twoWeeksAgo, $now) {
            if (! Schema::hasTable('orders')) {
                return;
            }
            $o = fn ($from, $to) => $between(DB::table('orders'), 'created_at', $from, $to);
            $put('marketplace_orders', 'Marketplace orders', 'count', $o($weekAgo, $now)->count(), $o($twoWeeksAgo, $weekAgo)->count());
            $gmv = fn ($from, $to) => (float) $o($from, $to)->where('order_status', 'delivered')->sum('order_amount');
            $put('marketplace_delivered_value', 'Marketplace delivered order value', 'usd', $gmv($weekAgo, $now), $gmv($twoWeeksAgo, $weekAgo));
        }, 'marketplace');

        $guard(function () use ($put, $between, $weekAgo, $twoWeeksAgo, $now) {
            if (! Schema::hasTable('users')) {
                return;
            }
            $u = fn ($from, $to) => $between(DB::table('users'), 'created_at', $from, $to);
            $put('new_customers', 'New customer accounts', 'count', $u($weekAgo, $now)->count(), $u($twoWeeksAgo, $weekAgo)->count());
        }, 'customers');

        $guard(function () use ($put) {
            if (Schema::hasTable('delivery_men') && Schema::hasColumn('delivery_men', 'application_status')) {
                $put('drivers_active', 'Approved active drivers', 'count', (float) DB::table('delivery_men')->where('application_status', 'approved')->where('status', 1)->count());
                $put('driver_applications_waiting', 'Driver applications waiting', 'count', (float) DB::table('delivery_men')->where('application_status', 'pending')->count());
            }
            if (Schema::hasTable('urban_goodz_driver_payout_requests')) {
                $put('driver_payouts_waiting', 'Driver payout requests waiting', 'count', (float) DB::table('urban_goodz_driver_payout_requests')->where('status', 'pending')->count());
            }
            if (Schema::hasTable('failed_jobs')) {
                $put('failed_jobs', 'Failed background jobs', 'count', (float) DB::table('failed_jobs')->count());
            }
        }, 'operations');

        return $m;
    }

    /** True when the volume is too small for any trend to mean anything. */
    public function isThin(array $metrics): bool
    {
        $activity = ($metrics['oa_requests']['value'] ?? 0) + ($metrics['marketplace_orders']['value'] ?? 0);

        return $activity < 10;
    }
}
