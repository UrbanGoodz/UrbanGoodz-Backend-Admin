<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiApproval;
use App\Models\AiTask;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiChiefOfStaffService
{
    public function getCommandCenterSummary(): array
    {
        return [
            'completed' => AiTask::where('status', 'completed')->count(),
            'in_progress' => AiTask::where('status', 'running')->count(),
            'planned' => AiTask::whereIn('status', ['pending', 'scheduled'])->count(),
            'business_needs' => BusinessNeed::where('status', 'open')->count(),
            'human_actions_required' => HumanActionItem::where('status', 'pending')->count(),
            'blocked' => AiTask::where('status', 'failed')->count()
                + HumanActionItem::where('status', 'escalated')->count(),
            'approvals' => AiApproval::where('decision', 'pending')->count(),
            'results' => [
                'prospects_qualified' => MerchantProspect::where('prospect_status', 'qualified')->count(),
                'prospects_contacted' => MerchantProspect::where('prospect_status', 'contacted')->count(),
                'revenue_influenced' => (float) MerchantProspect::sum('attributed_revenue'),
            ],
        ];
    }

    public function generateExecutiveDailyBrief(): array
    {
        return [
            'title' => 'Executive Daily Brief',
            'date' => today()->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'data_source' => 'live_database',
            'metrics' => $this->getCommandCenterSummary(),
            'operational_alerts' => $this->getOperationalAlerts(),
            'completed_tasks' => AiTask::with('agent')->where('status', 'completed')->latest()->take(5)->get(),
            'business_needs' => BusinessNeed::where('status', 'open')->orderBy('severity', 'desc')->take(5)->get(),
            'urgent_actions' => HumanActionItem::where('status', 'pending')
                ->where('priority', 'urgent')
                ->take(5)
                ->get(),
        ];
    }

    public function generateRoleBrief(string $role): array
    {
        $role = ucwords(str_replace('_', ' ', $role));

        return [
            'role' => $role,
            'date' => today()->toDateString(),
            'actions_required' => HumanActionItem::where('status', 'pending')
                ->where('assigned_role', $role)
                ->get(),
            'business_needs' => BusinessNeed::where('status', 'open')
                ->where('assigned_human_role', $role)
                ->get(),
            'completed_today' => AiTask::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }

    /**
     * The dashboard scan is intentionally read-only. A GET request must not
     * create operational records or turn a guessed condition into a fact.
     */
    public function runDiagnosticScan(): array
    {
        $alerts = $this->getOperationalAlerts();

        return [
            'alerts_detected' => collect($alerts)->where('count', '>', 0)->count(),
            'records_affected' => collect($alerts)->sum(fn(array $alert) => $alert['count'] ?? 0),
            'read_only' => true,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Grounded operational counts. A missing optional module table is exposed
     * as unavailable rather than represented by a made-up zero.
     */
    public function getOperationalAlerts(): array
    {
        return [
            $this->alert(
                'unassigned_orders',
                'Unassigned active orders',
                $this->countWhen(
                    'orders',
                    fn() => DB::table('orders')
                        ->whereNull('delivery_man_id')
                        ->whereNotIn('order_status', $this->terminalOrderStatuses())
                        ->count(),
                    ['delivery_man_id', 'order_status']
                ),
                'high',
                '/admin/order/list/all'
            ),
            $this->alert(
                'delayed_orders',
                'Orders open longer than two hours',
                $this->countWhen(
                    'orders',
                    fn() => DB::table('orders')
                        ->whereNotIn('order_status', $this->terminalOrderStatuses())
                        ->where('created_at', '<', now()->subHours(2))
                        ->count(),
                    ['order_status', 'created_at']
                ),
                'high',
                '/admin/order/list/all'
            ),
            $this->alert(
                'failed_payments',
                'Failed payment ledger events',
                $this->countWhen(
                    'urban_goodz_payment_ledgers',
                    fn() => DB::table('urban_goodz_payment_ledgers')
                        ->whereIn('payment_status', ['failed', 'declined', 'error'])
                        ->count(),
                    ['payment_status']
                ),
                'high',
                '/admin/urban-goodz/payments'
            ),
            $this->alert(
                'pending_refunds',
                'Refunds awaiting review',
                $this->countWhen(
                    'orders',
                    fn() => DB::table('orders')->where('order_status', 'refund_requested')->count(),
                    ['order_status']
                ),
                'high',
                '/admin/refund/pending'
            ),
            $this->alert(
                'pending_withdrawals',
                'Withdrawal requests awaiting approval',
                $this->countWhen(
                    'withdraw_requests',
                    fn() => DB::table('withdraw_requests')->where('approved', 0)->count(),
                    ['approved']
                ),
                'medium',
                '/admin/vendor/withdraw_list'
            ),
            $this->alert(
                'failed_queue_jobs',
                'Failed queue jobs',
                $this->countWhen('failed_jobs', fn() => DB::table('failed_jobs')->count()),
                'high',
                '/admin/urban-goodz/ai-operations/logs'
            ),
            $this->alert(
                'load_sourcing_errors',
                'Unresolved load-sourcing errors',
                $this->countWhen(
                    'load_source_errors',
                    fn() => DB::table('load_source_errors')->where('resolved', false)->count(),
                    ['resolved']
                ),
                'high',
                '/admin/urban-goodz/load-sourcing/errors'
            ),
            $this->alert(
                'out_of_stock_items',
                'Active items currently out of stock',
                $this->countWhen(
                    'items',
                    fn() => DB::table('items')->where('status', 1)->where('stock', '<=', 0)->count(),
                    ['stock', 'status']
                ),
                'medium',
                '/admin/item/list/all'
            ),
        ];
    }

    public function escalateOverdueActions(): void
    {
        $overdue = HumanActionItem::where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdue as $item) {
            if ($item->assigned_role !== 'Owner') {
                $item->update([
                    'assigned_role' => 'Owner',
                    'priority' => 'urgent',
                    'escalation_path' => 'Escalated to Owner due to SLA breach',
                    'status' => 'escalated',
                ]);
            }
        }
    }

    private function countWhen(string $table, callable $query, array $requiredColumns = []): ?int
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return null;
            }
        }

        return (int) $query();
    }

    private function alert(string $key, string $label, ?int $count, string $severity, string $url): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'available' => $count !== null,
            'severity' => $severity,
            'url' => $url,
        ];
    }

    private function terminalOrderStatuses(): array
    {
        return ['delivered', 'failed', 'canceled', 'cancelled', 'refunded', 'refund_request_canceled'];
    }
}
