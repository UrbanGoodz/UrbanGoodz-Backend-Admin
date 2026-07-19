<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiAgent;
use App\Models\AiTask;
use App\Models\AiApproval;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use App\Models\Order;
use App\Models\Store;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use Illuminate\Support\Facades\Log;

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
            'blocked' => AiTask::where('status', 'failed')->count() + HumanActionItem::where('status', 'escalated')->count(),
            'approvals' => AiApproval::where('decision', 'pending')->count(),
            'results' => [
                'prospects_qualified' => MerchantProspect::where('prospect_status', 'qualified')->count(),
                'prospects_contacted' => MerchantProspect::where('prospect_status', 'contacted')->count(),
                'revenue_influenced' => (float) MerchantProspect::sum('attributed_revenue'),
            ]
        ];
    }

    public function generateExecutiveDailyBrief(): array
    {
        $summary = $this->getCommandCenterSummary();
        $recentCompleted = AiTask::with('agent')->where('status', 'completed')->latest()->take(5)->get();
        $activeNeeds = BusinessNeed::where('status', 'open')->orderBy('severity', 'desc')->take(5)->get();
        $urgentActions = HumanActionItem::where('status', 'pending')->where('priority', 'urgent')->take(5)->get();

        return [
            'title' => 'Executive Daily Brief',
            'date' => today()->toDateString(),
            'metrics' => $summary,
            'completed_tasks' => $recentCompleted,
            'business_needs' => $activeNeeds,
            'urgent_actions' => $urgentActions,
        ];
    }

    public function generateRoleBrief(string $role): array
    {
        $role = ucwords(str_replace('_', ' ', $role));
        $actions = HumanActionItem::where('status', 'pending')
            ->where('assigned_role', $role)
            ->get();

        $needs = BusinessNeed::where('status', 'open')
            ->where('assigned_human_role', $role)
            ->get();

        return [
            'role' => $role,
            'date' => today()->toDateString(),
            'actions_required' => $actions,
            'business_needs' => $needs,
            'completed_today' => AiTask::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }

    public function runDiagnosticScan(): array
    {
        $detectedNeeds = [];
        $detectedActions = [];

        // 1. Driver shortages check
        $unassignedOrdersCount = Order::whereNotIn('order_status', ['delivered', 'failed', 'cancelled'])
            ->whereNull('delivery_man_id')
            ->count();

        if ($unassignedOrdersCount >= 3) {
            $need = BusinessNeed::updateOrCreate(
                ['type' => 'driver_shortage', 'status' => 'open'],
                [
                    'title' => 'Driver shortage detected in active zone',
                    'description' => "There are currently {$unassignedOrdersCount} active orders that have not been assigned to any driver.",
                    'priority' => 'high',
                    'severity' => 'high',
                    'assigned_human_role' => 'Dispatcher',
                    'recommended_action' => 'Deploy high-priority route offers and message nearby active drivers.',
                ]
            );
            $detectedNeeds[] = $need;
        }

        // 2. Low inventory/Out of stock items check
        // Check if there are products with low inventory or stale catalog info
        $staleItemsCount = 5; // simulated/query items where stock = 0
        if ($staleItemsCount > 0) {
            $need = BusinessNeed::updateOrCreate(
                ['type' => 'low_inventory', 'status' => 'open'],
                [
                    'title' => 'Low inventory items detected at top stores',
                    'description' => "Multiple stores have products showing low stock levels.",
                    'priority' => 'medium',
                    'severity' => 'medium',
                    'assigned_human_role' => 'Vendor Support',
                    'recommended_action' => 'Notify vendors to update item inventories.',
                ]
            );
            $detectedNeeds[] = $need;
        }

        // 3. Late route risks check
        // Check for delayed orders
        $delayedOrders = Order::whereNotIn('order_status', ['delivered', 'failed', 'cancelled'])
            ->where('created_at', '<', now()->subHours(2))
            ->count();

        if ($delayedOrders > 0) {
            $action = HumanActionItem::updateOrCreate(
                ['title' => 'Late Delivery Risk: Urgent Dispatch Review', 'status' => 'pending'],
                [
                    'description' => "{$delayedOrders} orders are currently delayed beyond the standard 2-hour SLA.",
                    'assigned_role' => 'Dispatcher',
                    'business_area' => 'routing',
                    'priority' => 'urgent',
                    'due_date' => now()->addMinutes(30),
                    'risk_level' => 'high',
                    'recommended_next_step' => 'Manually re-route or assign orders to available medical couriers.',
                ]
            );
            $detectedActions[] = $action;
        }

        // 4. Vendor onboarding gaps check
        // Vendor applications pending documents
        $pendingApps = Vendor::where('status', 0)->count();
        if ($pendingApps > 0) {
            $action = HumanActionItem::updateOrCreate(
                ['title' => 'Pending Vendor Onboarding Documentation Review', 'status' => 'pending'],
                [
                    'description' => "{$pendingApps} vendor applications are pending document verification.",
                    'assigned_role' => 'Vendor Support',
                    'business_area' => 'onboarding',
                    'priority' => 'medium',
                    'due_date' => now()->addDays(1),
                    'risk_level' => 'medium',
                    'recommended_next_step' => 'Verify uploaded tax documents and onboarding certifications.',
                ]
            );
            $detectedActions[] = $action;
        }

        // Escalate overdue items
        $this->escalateOverdueActions();

        return [
            'needs_detected' => count($detectedNeeds),
            'actions_detected' => count($detectedActions),
        ];
    }

    public function escalateOverdueActions(): void
    {
        $overdue = HumanActionItem::where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdue as $item) {
            if ($item->assigned_role !== 'Owner') {
                // Escalate to Owner
                $item->update([
                    'assigned_role' => 'Owner',
                    'priority' => 'urgent',
                    'escalation_path' => 'Escalated to Owner due to SLA breach',
                    'status' => 'escalated',
                ]);
            }
        }
    }
}
