<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzHistoricalReconstructionConfiguration;
use App\Models\UrbanGoodzHistoricalMonthlySnapshot;
use App\Models\UrbanGoodzHistoricalSourceRecord;
use App\Models\UrbanGoodzHistoricalReconstructionAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HistoricalReconstructionService
{
    private UrbanGoodzHistoricalReconstructionAuditTrail $auditService;

    public function __construct()
    {
        $this->auditService = new UrbanGoodzHistoricalReconstructionAuditTrail();
    }

    public function createConfiguration(array $data, ?int $adminId = null): UrbanGoodzHistoricalReconstructionConfiguration
    {
        $data['created_by'] = $adminId;
        $data['updated_by'] = $adminId;

        if (empty($data['evidentiary_disclaimer'])) {
            $data['evidentiary_disclaimer'] = 'IMPORTANT: The original production database was lost during a subsequent application rebuild. This report reconstructs historical business operations using surviving business records and owner-provided historical operating assumptions. Reconstructed values are estimates and are not represented as recovered original database records.';
        }

        $config = UrbanGoodzHistoricalReconstructionConfiguration::create($data);

        $this->logAudit(null, $config->id, null, 'configuration_created', UrbanGoodzHistoricalReconstructionConfiguration::class, $config->id, null, $config->toArray(), 'Historical reconstruction configuration created', $adminId);

        return $config;
    }

    public function updateConfiguration(int $id, array $data, ?int $adminId = null): UrbanGoodzHistoricalReconstructionConfiguration
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($id);
        $oldValues = $config->toArray();

        $data['updated_by'] = $adminId;
        $config->update($data);

        $this->logAudit(null, $config->id, null, 'configuration_updated', UrbanGoodzHistoricalReconstructionConfiguration::class, $config->id, $oldValues, $config->toArray(), 'Historical reconstruction configuration updated', $adminId);

        return $config->fresh();
    }

    public function runFullReconstruction(int $configurationId, ?int $adminId = null): Collection
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($configurationId);

        DB::beginTransaction();

        try {
            $config->snapshots()->delete();

            $months = $this->generateMonthRange(
                $config->reconstruction_start_date,
                $config->reconstruction_end_date
            );

            $snapshots = collect();

            foreach ($months as $monthDate) {
                $snapshot = $this->reconstructMonth($config, $monthDate, $adminId);
                $snapshots->push($snapshot);
            }

            $this->applyOwnerDeliveriesEstimate($snapshots, $config);

            DB::commit();

            $this->logAudit(null, $config->id, null, 'full_reconstruction_completed', UrbanGoodzHistoricalReconstructionConfiguration::class, $config->id, null, [
                'month_count' => $snapshots->count(),
                'date_range' => $config->reconstruction_start_date->format('Y-m') . ' to ' . $config->reconstruction_end_date->format('Y-m'),
            ], 'Full historical reconstruction completed for ' . $snapshots->count() . ' months', $adminId);

            return $snapshots;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function reconstructMonth(
        UrbanGoodzHistoricalReconstructionConfiguration $config,
        Carbon $monthDate,
        ?int $adminId = null
    ): UrbanGoodzHistoricalMonthlySnapshot {
        $assumptions = $this->generateMonthlyAssumptions($config, $monthDate);

        $orders = $assumptions['orders'];
        $aov = $assumptions['average_order_value'];
        $deliveryFee = $assumptions['delivery_fee'];
        $activeDrivers = $assumptions['active_driver_count'];

        $totalOrderValue = round($orders * $aov, 2);
        $orderCommissionRevenue = round($totalOrderValue * ($config->baseline_order_commission_pct / 100), 2);
        $deliveryFeeRevenue = round($orders * $deliveryFee, 2);
        $platformDeliveryFeeRevenue = round($deliveryFeeRevenue * ($config->baseline_platform_delivery_fee_pct / 100), 2);
        $totalPlatformRevenue = round($orderCommissionRevenue + $platformDeliveryFeeRevenue, 2);

        $driverPayouts = round($deliveryFeeRevenue * 0.85, 2);
        $operatingExpenses = round($totalPlatformRevenue * ($config->operating_expense_ratio / 100), 2);
        $calculatedNetIncome = round($totalPlatformRevenue - $driverPayouts - $operatingExpenses, 2);
        $variance = round($calculatedNetIncome - $config->baseline_avg_monthly_net, 2);

        $calcLog = [
            'orders' => $orders,
            'aov' => $aov,
            'total_order_value' => $totalOrderValue,
            'commission_pct' => $config->baseline_order_commission_pct,
            'order_commission_revenue' => $orderCommissionRevenue,
            'delivery_fee_per_order' => $deliveryFee,
            'delivery_fee_revenue' => $deliveryFeeRevenue,
            'platform_delivery_pct' => $config->baseline_platform_delivery_fee_pct,
            'platform_delivery_fee_revenue' => $platformDeliveryFeeRevenue,
            'total_platform_revenue' => $totalPlatformRevenue,
            'active_drivers' => $activeDrivers,
            'driver_payouts' => $driverPayouts,
            'operating_expense_ratio' => $config->operating_expense_ratio,
            'operating_expenses' => $operatingExpenses,
            'calculated_net_income' => $calculatedNetIncome,
            'baseline_net' => $config->baseline_avg_monthly_net,
            'variance' => $variance,
        ];

        $snapshot = UrbanGoodzHistoricalMonthlySnapshot::create([
            'reconstruction_id' => Str::uuid(),
            'configuration_id' => $config->id,
            'snapshot_month' => $monthDate->format('Y-m-d'),
            'snapshot_year' => $monthDate->year,
            'snapshot_month_number' => $monthDate->month,
            'estimated_orders' => $orders,
            'estimated_average_order_value' => $aov,
            'estimated_total_order_value' => $totalOrderValue,
            'estimated_order_commission_revenue' => $orderCommissionRevenue,
            'estimated_delivery_fee_per_order' => $deliveryFee,
            'estimated_delivery_fee_revenue' => $deliveryFeeRevenue,
            'estimated_platform_delivery_fee_revenue' => $platformDeliveryFeeRevenue,
            'estimated_total_platform_revenue' => $totalPlatformRevenue,
            'estimated_active_driver_count' => $activeDrivers,
            'estimated_owner_deliveries' => 0,
            'estimated_driver_payouts' => $driverPayouts,
            'estimated_operating_expenses' => $operatingExpenses,
            'estimated_net_income' => $calculatedNetIncome,
            'calculated_net_income' => $calculatedNetIncome,
            'net_income_variance_from_baseline' => $variance,
            'source_type' => 'historical_reconstruction',
            'reconstruction_method' => 'mathematical_estimation',
            'reconstruction_version' => '1.0',
            'confidence' => 'estimated',
            'assumptions_used' => $assumptions,
            'calculation_log' => $calcLog,
            'created_by' => $adminId,
        ]);

        $this->logAudit($snapshot->reconstruction_id, $config->id, $snapshot->id, 'month_reconstructed', UrbanGoodzHistoricalMonthlySnapshot::class, $snapshot->id, null, $snapshot->toArray(), 'Month ' . $monthDate->format('F Y') . ' reconstructed', $adminId);

        return $snapshot;
    }

    public function generateMonthlyAssumptions(UrbanGoodzHistoricalReconstructionConfiguration $config, Carbon $monthDate): array
    {
        $monthSeed = $monthDate->year * 100 + $monthDate->month;
        $seed = crc32($config->id . '-' . $monthSeed);

        $orders = $this->varyValue(
            $config->baseline_monthly_orders,
            $config->orders_variation_pct,
            $seed
        );

        $aov = $this->varyValue(
            $config->baseline_average_order_value,
            $config->aov_variation_pct,
            $seed + 1
        );

        $deliveryFee = $this->varyValue(
            $config->baseline_delivery_fee,
            $config->delivery_fee_variation_pct,
            $seed + 2
        );

        $activeDrivers = $this->varyIntegerValue(
            $config->baseline_active_drivers,
            $config->driver_count_variation_pct,
            $seed + 3
        );

        $ownerDeliveries = $this->estimateOwnerDeliveries($orders, $activeDrivers, $seed + 4);

        $driverPayoutsBase = round($orders * $deliveryFee * 0.85, 2);
        $operatingExpensesBase = round(
            ($orders * $aov * ($config->baseline_order_commission_pct / 100))
            * ($config->operating_expense_ratio / 100),
            2
        );

        return [
            'orders' => $orders,
            'average_order_value' => $aov,
            'delivery_fee' => $deliveryFee,
            'active_driver_count' => $activeDrivers,
            'owner_deliveries_estimate' => $ownerDeliveries,
            'driver_payouts' => $driverPayoutsBase,
            'operating_expenses' => $operatingExpensesBase,
            'month_date' => $monthDate->format('Y-m-d'),
            'seed' => $seed,
        ];
    }

    private function varyValue(float $baseline, float $variationPct, int $seed): float
    {
        $variationRange = $variationPct / 100;
        $hash = $this->seededRandom($seed);
        $factor = 1.0 + (($hash * 2 - 1) * $variationRange);
        return round($baseline * $factor, 2);
    }

    private function varyIntegerValue(float $baseline, float $variationPct, int $seed): int
    {
        $floatVal = $this->varyValue($baseline, $variationPct, $seed);
        return max(1, (int) round($floatVal));
    }

    private function seededRandom(int $seed): float
    {
        $x = sin($seed) * 10000;
        return $x - floor($x);
    }

    private function estimateOwnerDeliveries(float $orders, int $activeDrivers, int $seed): int
    {
        $driverShare = 1.0 / max($activeDrivers, 1);
        $ownerBase = round($orders * $driverShare, 0);
        $hash = $this->seededRandom($seed);
        $variation = round($ownerBase * 0.2 * ($hash * 2 - 1));
        return max(0, (int) ($ownerBase + $variation));
    }

    private function isOwnerDeliveryMonth(Carbon $monthDate, UrbanGoodzHistoricalReconstructionConfiguration $config): bool
    {
        $nonDeliveryMonths = $config->owner_non_delivery_months ?? [12, 1, 2];
        return !in_array($monthDate->month, $nonDeliveryMonths);
    }

    private function applyOwnerDeliveriesEstimate(Collection $snapshots, UrbanGoodzHistoricalReconstructionConfiguration $config): void
    {
        $ownerName = $config->owner_name ?? 'Owner/Founder';

        foreach ($snapshots as $snapshot) {
            $monthDate = $snapshot->snapshot_month;

            if (!$this->isOwnerDeliveryMonth($monthDate, $config)) {
                $snapshot->update([
                    'estimated_owner_deliveries' => 0,
                    'notes' => "Owner ({$ownerName}) did not deliver during configured non-delivery months.",
                ]);
                continue;
            }

            $ownerDeliveries = $snapshot->assumptions_used['owner_deliveries_estimate'] ?? 0;
            $snapshot->update([
                'estimated_owner_deliveries' => $ownerDeliveries,
                'notes' => "Owner ({$ownerName}) active as delivery driver this month. Earnings included in platform net income.",
            ]);
        }
    }

    public function generateMonthRange(Carbon $start, Carbon $end): array
    {
        $months = [];
        $current = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($current->lte($last)) {
            $months[] = $current->copy();
            $current->addMonth();
        }

        return $months;
    }

    public function getReconstructionSummary(int $configurationId): array
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($configurationId);
        $snapshots = $config->snapshots()->orderBy('snapshot_month')->get();

        if ($snapshots->isEmpty()) {
            return [
                'configuration' => $config,
                'snapshots' => collect(),
                'summary' => null,
            ];
        }

        $totalOrders = $snapshots->sum('estimated_orders');
        $totalPlatformRevenue = $snapshots->sum('estimated_total_platform_revenue');
        $totalNetIncome = $snapshots->sum('estimated_net_income');
        $totalOwnerDeliveries = $snapshots->sum('estimated_owner_deliveries');
        $monthCount = $snapshots->count();

        $summary = [
            'total_orders' => round($totalOrders, 0),
            'total_platform_revenue' => round($totalPlatformRevenue, 2),
            'total_estimated_net' => round($totalNetIncome, 2),
            'total_owner_deliveries' => $totalOwnerDeliveries,
            'average_monthly_orders' => round($totalOrders / $monthCount, 0),
            'average_monthly_revenue' => round($totalPlatformRevenue / $monthCount, 2),
            'average_monthly_net' => round($totalNetIncome / $monthCount, 2),
            'median_monthly_orders' => $snapshots->median('estimated_orders'),
            'median_monthly_net' => $snapshots->median('estimated_net_income'),
            'average_order_value' => round($snapshots->avg('estimated_average_order_value'), 2),
            'average_delivery_fee' => round($snapshots->avg('estimated_delivery_fee_per_order'), 2),
            'average_active_drivers' => round($snapshots->avg('estimated_active_driver_count'), 0),
            'month_count' => $monthCount,
            'date_range' => $snapshots->first()->snapshot_month->format('M Y') . ' - ' . $snapshots->last()->snapshot_month->format('M Y'),
        ];

        $summary['reconciliation'] = $this->reconcileAgainstBaseline($summary, $config);

        return [
            'configuration' => $config,
            'snapshots' => $snapshots,
            'summary' => $summary,
        ];
    }

    public function reconcileAgainstBaseline(array $summary, UrbanGoodzHistoricalReconstructionConfiguration $config): array
    {
        $checks = [];

        $ordersDiff = abs($summary['average_monthly_orders'] - $config->baseline_monthly_orders);
        $ordersPct = ($ordersDiff / $config->baseline_monthly_orders) * 100;
        $checks['orders'] = [
            'reconstructed' => $summary['average_monthly_orders'],
            'baseline' => $config->baseline_monthly_orders,
            'variance_pct' => round($ordersPct, 2),
            'status' => $ordersPct <= 5 ? 'MATCH' : ($ordersPct <= 15 ? 'CLOSE' : 'DOES NOT RECONCILE'),
        ];

        $aovDiff = abs($summary['average_order_value'] - $config->baseline_average_order_value);
        $aovPct = ($aovDiff / $config->baseline_average_order_value) * 100;
        $checks['average_order_value'] = [
            'reconstructed' => $summary['average_order_value'],
            'baseline' => $config->baseline_average_order_value,
            'variance_pct' => round($aovPct, 2),
            'status' => $aovPct <= 5 ? 'MATCH' : ($aovPct <= 15 ? 'CLOSE' : 'DOES NOT RECONCILE'),
        ];

        $feeDiff = abs($summary['average_delivery_fee'] - $config->baseline_delivery_fee);
        $feePct = ($feeDiff / $config->baseline_delivery_fee) * 100;
        $checks['delivery_fee'] = [
            'reconstructed' => $summary['average_delivery_fee'],
            'baseline' => $config->baseline_delivery_fee,
            'variance_pct' => round($feePct, 2),
            'status' => $feePct <= 5 ? 'MATCH' : ($feePct <= 15 ? 'CLOSE' : 'DOES NOT RECONCILE'),
        ];

        $driversDiff = abs($summary['average_active_drivers'] - $config->baseline_active_drivers);
        $driversPct = ($driversDiff / $config->baseline_active_drivers) * 100;
        $checks['active_drivers'] = [
            'reconstructed' => $summary['average_active_drivers'],
            'baseline' => $config->baseline_active_drivers,
            'variance_pct' => round($driversPct, 2),
            'status' => $driversPct <= 10 ? 'MATCH' : ($driversPct <= 25 ? 'CLOSE' : 'DOES NOT RECONCILE'),
        ];

        $netDiff = abs($summary['average_monthly_net'] - $config->baseline_avg_monthly_net);
        $netPct = ($netDiff / $config->baseline_avg_monthly_net) * 100;
        $checks['net_income'] = [
            'reconstructed' => $summary['average_monthly_net'],
            'baseline' => $config->baseline_avg_monthly_net,
            'variance_pct' => round($netPct, 2),
            'status' => $netPct <= 10 ? 'MATCH' : ($netPct <= 25 ? 'CLOSE' : 'DOES NOT RECONCILE'),
        ];

        $statuses = array_column($checks, 'status');
        $checks['overall'] = in_array('DOES NOT RECONCILE', $statuses)
            ? 'DOES NOT RECONCILE'
            : (in_array('CLOSE', $statuses) ? 'CLOSE' : 'MATCH');

        return $checks;
    }

    public function getTruckPurchaseTimeline(int $configurationId): array
    {
        $config = UrbanGoodzHistoricalReconstructionConfiguration::findOrFail($configurationId);
        $truckPurchaseDate = Carbon::parse('2025-10-08');
        $truckPurchaseMonth = $truckPurchaseDate->copy()->startOfMonth();

        $prePurchase = $config->snapshots()
            ->where('snapshot_month', '<', $truckPurchaseMonth)
            ->orderBy('snapshot_month')
            ->get();

        $purchaseMonth = $config->snapshots()
            ->whereYear('snapshot_month', $truckPurchaseDate->year)
            ->whereMonth('snapshot_month', $truckPurchaseDate->month)
            ->first();

        $postPurchase = $config->snapshots()
            ->where('snapshot_month', '>', $truckPurchaseMonth)
            ->orderBy('snapshot_month')
            ->get();

        return [
            'truck_purchase_date' => $truckPurchaseDate->format('Y-m-d'),
            'pre_purchase_months' => $prePurchase,
            'purchase_month' => $purchaseMonth,
            'post_purchase_months' => $postPurchase,
            'pre_purchase_avg_orders' => $prePurchase->isNotEmpty() ? round($prePurchase->avg('estimated_orders'), 0) : null,
            'pre_purchase_avg_revenue' => $prePurchase->isNotEmpty() ? round($prePurchase->avg('estimated_total_platform_revenue'), 2) : null,
            'post_purchase_avg_orders' => $postPurchase->isNotEmpty() ? round($postPurchase->avg('estimated_orders'), 0) : null,
            'post_purchase_avg_revenue' => $postPurchase->isNotEmpty() ? round($postPurchase->avg('estimated_total_platform_revenue'), 2) : null,
        ];
    }

    public function importSourceRecord(int $configurationId, array $data, ?int $snapshotId = null, ?int $adminId = null): UrbanGoodzHistoricalSourceRecord
    {
        $record = UrbanGoodzHistoricalSourceRecord::create([
            'configuration_id' => $configurationId,
            'snapshot_id' => $snapshotId,
            'source_type' => $data['source_type'],
            'source_description' => $data['source_description'] ?? null,
            'source_date' => $data['source_date'] ?? null,
            'source_data' => $data['source_data'] ?? null,
            'confidence_score' => $data['confidence_score'] ?? 0.5,
            'confidence_label' => $data['confidence_label'] ?? 'estimated',
            'notes' => $data['notes'] ?? null,
            'overrides_reconstruction' => $data['overrides_reconstruction'] ?? false,
            'imported_by' => $adminId,
        ]);

        if ($record->overrides_reconstruction && $snapshotId) {
            $this->applySourceRecordOverride($record);
        }

        $this->logAudit(null, $configurationId, $snapshotId, 'source_record_imported', UrbanGoodzHistoricalSourceRecord::class, $record->id, null, $record->toArray(), 'Source record imported: ' . ($data['source_description'] ?? $data['source_type']), $adminId);

        return $record;
    }

    private function applySourceRecordOverride(UrbanGoodzHistoricalSourceRecord $record): void
    {
        if (!$record->snapshot_id || !$record->source_data) {
            return;
        }

        $snapshot = UrbanGoodzHistoricalMonthlySnapshot::find($record->snapshot_id);
        if (!$snapshot) {
            return;
        }

        $updates = [];
        $fieldMap = [
            'orders' => 'estimated_orders',
            'average_order_value' => 'estimated_average_order_value',
            'total_order_value' => 'estimated_total_order_value',
            'delivery_fee' => 'estimated_delivery_fee_per_order',
            'active_drivers' => 'estimated_active_driver_count',
            'owner_deliveries' => 'estimated_owner_deliveries',
        ];

        foreach ($fieldMap as $sourceKey => $snapshotField) {
            if (isset($record->source_data[$sourceKey])) {
                $updates[$snapshotField] = $record->source_data[$sourceKey];
            }
        }

        if (!empty($updates)) {
            $updates['source_type'] = $record->source_type;
            $updates['confidence'] = $record->confidence_label;
            $snapshot->update($updates);
        }
    }

    public function exportCsv(int $configurationId): string
    {
        $data = $this->getReconstructionSummary($configurationId);
        $config = $data['configuration'];
        $snapshots = $data['snapshots'];

        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'MONTH', 'ORDERS', 'AVERAGE ORDER', 'GROSS ORDER VALUE',
            '23% ORDER REVENUE', 'DELIVERY FEES', '3% DELIVERY-FEE REVENUE',
            'TOTAL PLATFORM REVENUE', 'ACTIVE DRIVERS', 'OWNER DELIVERIES',
            'DRIVER PAYOUTS', 'OPERATING EXPENSES', 'ESTIMATED NET', 'CONFIDENCE',
        ]);

        foreach ($snapshots as $s) {
            fputcsv($output, [
                $s->snapshot_month->format('M Y'),
                $s->estimated_orders,
                number_format($s->estimated_average_order_value, 2),
                number_format($s->estimated_total_order_value, 2),
                number_format($s->estimated_order_commission_revenue, 2),
                number_format($s->estimated_delivery_fee_revenue, 2),
                number_format($s->estimated_platform_delivery_fee_revenue, 2),
                number_format($s->estimated_total_platform_revenue, 2),
                $s->estimated_active_driver_count,
                $s->estimated_owner_deliveries,
                number_format($s->estimated_driver_payouts, 2),
                number_format($s->estimated_operating_expenses, 2),
                number_format($s->estimated_net_income, 2),
                strtoupper($s->confidence),
            ]);
        }

        if ($data['summary']) {
            fputcsv($output, []);
            fputcsv($output, ['24-MONTH TOTALS']);
            fputcsv($output, ['Total Orders', $data['summary']['total_orders']]);
            fputcsv($output, ['Total Platform Revenue', number_format($data['summary']['total_platform_revenue'], 2)]);
            fputcsv($output, ['Total Estimated Net', number_format($data['summary']['total_estimated_net'], 2)]);
            fputcsv($output, ['Total Owner Deliveries', $data['summary']['total_owner_deliveries']]);
            fputcsv($output, []);
            fputcsv($output, ['24-MONTH AVERAGES']);
            fputcsv($output, ['Average Monthly Orders', $data['summary']['average_monthly_orders']]);
            fputcsv($output, ['Average Monthly Net', number_format($data['summary']['average_monthly_net'], 2)]);
            fputcsv($output, ['Average Order Value', number_format($data['summary']['average_order_value'], 2)]);
            fputcsv($output, ['Average Delivery Fee', number_format($data['summary']['average_delivery_fee'], 2)]);
            fputcsv($output, ['Average Active Drivers', $data['summary']['average_active_drivers']]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function exportJson(int $configurationId): array
    {
        $data = $this->getReconstructionSummary($configurationId);

        return [
            'configuration' => $data['configuration']->toArray(),
            'evidentiary_disclaimer' => $data['configuration']->evidentiary_disclaimer,
            'snapshots' => $data['snapshots']->map(fn ($s) => [
                'month' => $s->snapshot_month->format('M Y'),
                'orders' => (float) $s->estimated_orders,
                'average_order_value' => (float) $s->estimated_average_order_value,
                'total_order_value' => (float) $s->estimated_total_order_value,
                'order_commission_revenue' => (float) $s->estimated_order_commission_revenue,
                'delivery_fee_revenue' => (float) $s->estimated_delivery_fee_revenue,
                'platform_delivery_fee_revenue' => (float) $s->estimated_platform_delivery_fee_revenue,
                'total_platform_revenue' => (float) $s->estimated_total_platform_revenue,
                'active_drivers' => $s->estimated_active_driver_count,
                'owner_deliveries' => $s->estimated_owner_deliveries,
                'driver_payouts' => (float) $s->estimated_driver_payouts,
                'operating_expenses' => (float) $s->estimated_operating_expenses,
                'estimated_net_income' => (float) $s->estimated_net_income,
                'confidence' => $s->confidence,
                'source_type' => $s->source_type,
                'reconstruction_method' => $s->reconstruction_method,
                'reconstruction_version' => $s->reconstruction_version,
            ])->toArray(),
            'summary' => $data['summary'],
            'exported_at' => now()->toIso8601String(),
        ];
    }

    private function logAudit(
        ?string $reconstructionId,
        ?int $configurationId,
        ?int $snapshotId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $description,
        ?int $adminId
    ): void {
        UrbanGoodzHistoricalReconstructionAuditTrail::create([
            'reconstruction_id' => $reconstructionId,
            'configuration_id' => $configurationId,
            'snapshot_id' => $snapshotId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'admin_id' => $adminId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
