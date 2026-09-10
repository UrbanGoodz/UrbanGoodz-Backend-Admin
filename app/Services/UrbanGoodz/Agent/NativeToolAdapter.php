<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\AiAuditEvent;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\UrbanGoodz\AiChiefOfStaffService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NativeToolAdapter implements ToolAdapterInterface
{
    public function __construct(
        private readonly ?AiChiefOfStaffService $chiefOfStaff = null
    ) {}

    public function name(): string
    {
        return 'native';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function execute(string $toolName, array $parameters, array $context = []): array
    {
        return match ($toolName) {
            'get_vendor_details' => $this->getVendorDetails($parameters),
            'list_vendors' => $this->listVendors($parameters),
            'audit_vendor_onboarding' => $this->auditVendorOnboarding($parameters),
            'update_vendor_status' => $this->updateVendorStatus($parameters, $context),
            'get_order_details' => $this->getOrderDetails($parameters),
            'assign_order_courier' => $this->assignOrderCourier($parameters, $context),
            'cancel_order' => $this->cancelOrder($parameters, $context),
            'get_out_of_stock_inventory' => $this->getOutOfStockInventory($parameters),
            'retry_failed_queue_job' => $this->retryFailedQueueJob($parameters, $context),
            'get_command_center_metrics' => $this->getCommandCenterMetrics(),
            'vendor_review_orders' => $this->vendorReviewOrders($parameters, $context),
            'vendor_performance_summary' => $this->vendorPerformanceSummary($parameters, $context),
            'vendor_operational_alerts' => $this->vendorOperationalAlerts($parameters, $context),
            'vendor_promotions_summary' => $this->vendorPromotionsSummary($parameters, $context),
            'vendor_update_item' => $this->vendorUpdateItem($parameters, $context),
            'analyze_driver_shortage' => $this->analyzeDriverShortage($parameters),
            'add_vendor_driver' => $this->addVendorDriver($parameters, $context),
            'configure_driver_pay' => $this->configureDriverPay($parameters, $context),
            'approve_driver' => $this->approveDriver($parameters, $context),
            'suspend_driver' => $this->suspendDriver($parameters, $context),
            default => [
                'success' => false,
                'verified' => false,
                'tool' => $toolName,
                'adapter' => $this->name(),
                'message' => "Unimplemented native tool action '{$toolName}'.",
                'error_code' => 'unknown_tool',
            ],
        };
    }

    private function getVendorDetails(array $params): array
    {
        $vendorId = (int) ($params['vendor_id'] ?? 0);
        $name = trim((string) ($params['name'] ?? ''));

        $query = Vendor::query()->with('stores');
        if ($vendorId > 0) {
            $query->where('id', $vendorId);
        } elseif ($name !== '') {
            $query->where(function ($q) use ($name) {
                $q->where('f_name', 'like', "%{$name}%")
                  ->orWhere('l_name', 'like', "%{$name}%")
                  ->orWhereHas('stores', fn ($sq) => $sq->where('name', 'like', "%{$name}%"));
            });
        } else {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'get_vendor_details',
                'adapter' => $this->name(),
                'message' => 'Please provide a vendor ID or name.',
            ];
        }

        $vendor = $query->first();
        if (!$vendor) {
            return [
                'success' => false,
                'verified' => true,
                'tool' => 'get_vendor_details',
                'adapter' => $this->name(),
                'message' => 'Vendor not found in database records.',
            ];
        }

        $stores = $vendor->stores->map(fn ($s) => [
            'store_id' => $s->id,
            'name' => $s->name,
            'status' => (int) $s->status === 1 ? 'active' : 'inactive',
            'phone' => $s->phone,
            'address' => $s->address,
            'zone_id' => $s->zone_id,
        ])->toArray();

        $missingItems = [];
        if (empty($vendor->phone)) $missingItems[] = 'phone_number';
        if (empty($vendor->email)) $missingItems[] = 'email_address';
        if ($vendor->stores->isEmpty()) $missingItems[] = 'store_profile';

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'get_vendor_details',
            'adapter' => $this->name(),
            'message' => "Found vendor #{$vendor->id} ({$vendor->f_name} {$vendor->l_name}).",
            'data' => [
                'vendor_id' => $vendor->id,
                'name' => trim("{$vendor->f_name} {$vendor->l_name}"),
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'status' => (int) $vendor->status === 1 ? 'active' : 'inactive',
                'stores' => $stores,
                'onboarding_complete' => empty($missingItems),
                'missing_items' => $missingItems,
            ],
        ];
    }

    private function listVendors(array $params): array
    {
        $status = $params['status'] ?? 'all';
        $limit = min(50, max(1, (int) ($params['limit'] ?? 20)));

        $query = Vendor::query()->with('stores');
        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }

        $vendors = $query->latest('id')->limit($limit)->get()->map(fn ($v) => [
            'vendor_id' => $v->id,
            'name' => trim("{$v->f_name} {$v->l_name}"),
            'status' => (int) $v->status === 1 ? 'active' : 'inactive',
            'store_count' => $v->stores->count(),
            'primary_store' => $v->stores->first()?->name,
        ])->toArray();

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'list_vendors',
            'adapter' => $this->name(),
            'message' => 'Retrieved ' . count($vendors) . ' vendor records.',
            'data' => [
                'total_returned' => count($vendors),
                'vendors' => $vendors,
            ],
        ];
    }

    private function auditVendorOnboarding(array $params): array
    {
        $allVendors = Vendor::with('stores')->get();
        $incomplete = [];
        $completeCount = 0;

        foreach ($allVendors as $vendor) {
            $missing = [];
            if (empty($vendor->phone)) $missing[] = 'phone';
            if (empty($vendor->email)) $missing[] = 'email';
            if ($vendor->stores->isEmpty()) {
                $missing[] = 'store_profile';
            } else {
                $store = $vendor->stores->first();
                if (empty($store->address)) $missing[] = 'store_address';
                if (empty($store->phone)) $missing[] = 'store_phone';
                if ((int) $store->status === 0) $missing[] = 'store_activation';
            }
            if ((int) $vendor->status === 0) $missing[] = 'vendor_approval';

            if (!empty($missing)) {
                $incomplete[] = [
                    'vendor_id' => $vendor->id,
                    'name' => trim("{$vendor->f_name} {$vendor->l_name}"),
                    'email' => $vendor->email,
                    'store_name' => $vendor->stores->first()?->name ?? 'None',
                    'status' => (int) $vendor->status === 1 ? 'active' : 'inactive',
                    'missing_requirements' => $missing,
                ];
            } else {
                $completeCount++;
            }
        }

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'audit_vendor_onboarding',
            'adapter' => $this->name(),
            'message' => "Audited {$allVendors->count()} vendors: {$completeCount} fully completed, " . count($incomplete) . ' with missing onboarding items.',
            'data' => [
                'total_audited' => $allVendors->count(),
                'fully_completed_count' => $completeCount,
                'incomplete_count' => count($incomplete),
                'incomplete_vendors' => array_slice($incomplete, 0, 15),
            ],
        ];
    }

    private function updateVendorStatus(array $params, array $context): array
    {
        $vendorId = (int) ($params['vendor_id'] ?? 0);
        $statusStr = strtolower(trim((string) ($params['status'] ?? '')));
        $reason = trim((string) ($params['reason'] ?? 'Updated via Chief of Staff Monique'));

        if ($vendorId <= 0 || !in_array($statusStr, ['active', 'inactive', 'suspended'], true)) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'update_vendor_status',
                'adapter' => $this->name(),
                'message' => 'Valid vendor_id and status (active|inactive|suspended) are required.',
            ];
        }

        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'update_vendor_status',
                'adapter' => $this->name(),
                'message' => "Vendor #{$vendorId} not found.",
            ];
        }

        $prevStatusNumeric = (int) $vendor->status;
        $prevStatusLabel = $prevStatusNumeric === 1 ? 'active' : 'inactive';

        $targetStatusNumeric = ($statusStr === 'active') ? 1 : 0;

        // Perform mutation
        $vendor->status = $targetStatusNumeric;
        $vendor->save();

        // Update linked stores as well to maintain business consistency
        Store::where('vendor_id', $vendorId)->update(['status' => $targetStatusNumeric]);

        // Post-execution verification against DB
        $reloaded = Vendor::find($vendorId);
        $verified = ($reloaded && (int) $reloaded->status === $targetStatusNumeric);

        // Audit logging
        $this->logAuditEvent('vendor_status_updated', [
            'vendor_id' => $vendorId,
            'previous_status' => $prevStatusLabel,
            'new_status' => $statusStr,
            'reason' => $reason,
            'actor_id' => $context['admin_id'] ?? null,
            'actor_role' => $context['actor_role'] ?? 'admin',
        ]);

        return [
            'success' => true,
            'verified' => $verified,
            'tool' => 'update_vendor_status',
            'adapter' => $this->name(),
            'message' => "Vendor #{$vendorId} status changed from {$prevStatusLabel} to {$statusStr}.",
            'previous_state' => $prevStatusLabel,
            'new_state' => $statusStr,
            'data' => [
                'vendor_id' => $vendorId,
                'status' => $statusStr,
                'reason' => $reason,
            ],
        ];
    }

    private function getOrderDetails(array $params): array
    {
        $orderId = (int) ($params['order_id'] ?? 0);
        $order = Order::withoutGlobalScopes()->with(['details.item', 'customer', 'delivery_man', 'store'])->find($orderId);

        if (!$order) {
            return [
                'success' => false,
                'verified' => true,
                'tool' => 'get_order_details',
                'adapter' => $this->name(),
                'message' => "Order #{$orderId} not found.",
            ];
        }

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'get_order_details',
            'adapter' => $this->name(),
            'message' => "Retrieved details for Order #{$orderId}.",
            'data' => [
                'order_id' => $order->id,
                'status' => $order->order_status,
                'order_amount' => (float) $order->order_amount,
                'customer_name' => $order->customer ? trim("{$order->customer->f_name} {$order->customer->l_name}") : 'Guest',
                'store_name' => $order->store?->name,
                'assigned_driver' => $order->delivery_man ? trim("{$order->delivery_man->f_name} {$order->delivery_man->l_name}") : 'Unassigned',
                'created_at' => (string) $order->created_at,
            ],
        ];
    }

    private function assignOrderCourier(array $params, array $context): array
    {
        $orderId = (int) ($params['order_id'] ?? 0);
        $driverId = (int) ($params['driver_id'] ?? 0);

        $order = Order::withoutGlobalScopes()->find($orderId);
        $driver = DeliveryMan::find($driverId);

        if (!$order || !$driver) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'assign_order_courier',
                'adapter' => $this->name(),
                'message' => 'Invalid order_id or driver_id.',
            ];
        }

        $prevDriver = $order->delivery_man_id;
        $order->delivery_man_id = $driverId;
        if ($order->order_status === 'pending') {
            $order->order_status = 'confirmed';
        }
        $order->save();

        // Verification
        $reloaded = Order::withoutGlobalScopes()->find($orderId);
        $verified = ($reloaded && (int) $reloaded->delivery_man_id === $driverId);

        $this->logAuditEvent('order_courier_assigned', [
            'order_id' => $orderId,
            'driver_id' => $driverId,
            'actor_id' => $context['admin_id'] ?? null,
        ]);

        return [
            'success' => true,
            'verified' => $verified,
            'tool' => 'assign_order_courier',
            'adapter' => $this->name(),
            'message' => "Order #{$orderId} assigned to courier #{$driverId} ({$driver->f_name} {$driver->l_name}).",
            'previous_state' => $prevDriver ? "Driver #{$prevDriver}" : 'Unassigned',
            'new_state' => "Driver #{$driverId} ({$driver->f_name})",
            'data' => [
                'order_id' => $orderId,
                'driver_id' => $driverId,
            ],
        ];
    }

    private function cancelOrder(array $params, array $context): array
    {
        $orderId = (int) ($params['order_id'] ?? 0);
        $reason = trim((string) ($params['reason'] ?? 'Cancelled via Chief of Staff'));

        $order = Order::withoutGlobalScopes()->find($orderId);
        if (!$order) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'cancel_order',
                'adapter' => $this->name(),
                'message' => "Order #{$orderId} not found.",
            ];
        }

        $prevStatus = $order->order_status;
        $order->order_status = 'canceled';
        $order->cancellation_reason = $reason;
        $order->canceled_by = 'admin';
        $order->save();

        // Verification
        $reloaded = Order::withoutGlobalScopes()->find($orderId);
        $verified = ($reloaded && $reloaded->order_status === 'canceled');

        $this->logAuditEvent('order_cancelled', [
            'order_id' => $orderId,
            'reason' => $reason,
            'actor_id' => $context['admin_id'] ?? null,
        ]);

        return [
            'success' => true,
            'verified' => $verified,
            'tool' => 'cancel_order',
            'adapter' => $this->name(),
            'message' => "Order #{$orderId} status changed from {$prevStatus} to canceled.",
            'previous_state' => $prevStatus,
            'new_state' => 'canceled',
            'data' => [
                'order_id' => $orderId,
                'reason' => $reason,
            ],
        ];
    }

    private function getOutOfStockInventory(array $params): array
    {
        $limit = min(50, max(1, (int) ($params['limit'] ?? 20)));
        $total = DB::table('items')->where('status', 1)->where('stock', '<=', 0)->count();

        $rows = DB::table('items')
            ->leftJoin('stores', 'stores.id', '=', 'items.store_id')
            ->where('items.status', 1)
            ->where('items.stock', '<=', 0)
            ->groupBy('items.store_id', 'stores.name')
            ->orderByDesc(DB::raw('COUNT(items.id)'))
            ->limit($limit)
            ->get([
                'items.store_id',
                'stores.name as store_name',
                DB::raw('COUNT(items.id) as out_of_stock_count'),
            ]);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'get_out_of_stock_inventory',
            'adapter' => $this->name(),
            'message' => "{$total} out-of-stock items across " . $rows->count() . ' stores.',
            'data' => [
                'total_out_of_stock' => $total,
                'breakdown' => $rows->toArray(),
            ],
        ];
    }

    private function retryFailedQueueJob(array $params, array $context): array
    {
        $uuid = $params['job_uuid'] ?? null;
        if (!$uuid) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'retry_failed_queue_job',
                'adapter' => $this->name(),
                'message' => 'Job UUID is required.',
            ];
        }

        $existed = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        if (!$existed) {
            return [
                'success' => false,
                'verified' => true,
                'tool' => 'retry_failed_queue_job',
                'adapter' => $this->name(),
                'message' => "Job {$uuid} was not found in failed jobs.",
            ];
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $stillFailed = DB::table('failed_jobs')->where('uuid', $uuid)->exists();

        $this->logAuditEvent('queue_job_retried', [
            'uuid' => $uuid,
            'actor_id' => $context['admin_id'] ?? null,
        ]);

        return [
            'success' => !$stillFailed,
            'verified' => true,
            'tool' => 'retry_failed_queue_job',
            'adapter' => $this->name(),
            'message' => $stillFailed ? "Job {$uuid} retry failed." : "Job {$uuid} successfully re-queued.",
            'previous_state' => 'failed',
            'new_state' => $stillFailed ? 'failed' : 'queued',
        ];
    }

    private function getCommandCenterMetrics(): array
    {
        $service = $this->chiefOfStaff ?? app(AiChiefOfStaffService::class);
        $summary = $service->getCommandCenterSummary();

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'get_command_center_metrics',
            'adapter' => $this->name(),
            'message' => 'Retrieved live command center metrics.',
            'data' => $summary,
        ];
    }

    private function vendorReviewOrders(array $params, array $context): array
    {
        $vendorId = (int) ($context['vendor_id'] ?? $context['admin_id'] ?? 0);
        $storeIds = Store::where('vendor_id', $vendorId)->pluck('id');

        $statusFilter = $params['status'] ?? 'all_active';
        $orderQuery = Order::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->with(['details.item', 'customer']);

        if ($statusFilter === 'pending') {
            $orderQuery->where('order_status', 'pending');
        } elseif ($statusFilter === 'processing') {
            $orderQuery->whereIn('order_status', ['confirmed', 'processing', 'handover']);
        } else {
            $orderQuery->whereIn('order_status', ['pending', 'confirmed', 'processing', 'handover']);
        }

        $orders = $orderQuery->latest('id')->limit(25)->get();
        $urgentCount = $orders->where('order_status', 'pending')->count();

        $orderSummaries = $orders->map(fn ($o) => [
            'order_id' => $o->id,
            'status' => $o->order_status,
            'amount' => (float) $o->order_amount,
            'customer' => $o->customer ? trim("{$o->customer->f_name} {$o->customer->l_name}") : 'Customer',
            'item_count' => $o->details->count(),
            'placed_at' => (string) $o->created_at,
        ])->toArray();

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'vendor_review_orders',
            'adapter' => $this->name(),
            'message' => "Found {$orders->count()} active orders for your stores ({$urgentCount} pending acceptance).",
            'data' => [
                'total_orders' => $orders->count(),
                'urgent_attention_count' => $urgentCount,
                'orders' => $orderSummaries,
            ],
        ];
    }

    private function vendorPerformanceSummary(array $params, array $context): array
    {
        $vendorId = (int) ($context['vendor_id'] ?? $context['admin_id'] ?? 0);
        $vendorAI = app(\App\Services\UrbanGoodz\VendorAIService::class);
        $performance = $vendorAI->analyzeVendorPerformance($vendorId);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'vendor_performance_summary',
            'adapter' => $this->name(),
            'message' => 'Generated performance summary for your store.',
            'data' => $performance,
        ];
    }

    private function vendorOperationalAlerts(array $params, array $context): array
    {
        $vendorId = (int) ($context['vendor_id'] ?? $context['admin_id'] ?? 0);
        $vendorAI = app(\App\Services\UrbanGoodz\VendorAIService::class);
        $alerts = $vendorAI->generateVendorAlerts($vendorId);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'vendor_operational_alerts',
            'adapter' => $this->name(),
            'message' => 'Retrieved operational alerts for your store.',
            'data' => $alerts,
        ];
    }

    private function vendorPromotionsSummary(array $params, array $context): array
    {
        $vendorId = (int) ($context['vendor_id'] ?? $context['admin_id'] ?? 0);
        $vendorAI = app(\App\Services\UrbanGoodz\VendorAIService::class);
        $promotions = $vendorAI->suggestVendorPromotions($vendorId);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'vendor_promotions_summary',
            'adapter' => $this->name(),
            'message' => 'Retrieved promotion campaigns and suggestions.',
            'data' => $promotions,
        ];
    }

    private function vendorUpdateItem(array $params, array $context): array
    {
        $vendorId = (int) ($context['vendor_id'] ?? $context['admin_id'] ?? 0);
        $itemId = (int) ($params['item_id'] ?? 0);
        $storeIds = Store::where('vendor_id', $vendorId)->pluck('id');

        $item = \App\Models\Item::withoutGlobalScopes()
            ->where('id', $itemId)
            ->whereIn('store_id', $storeIds)
            ->first();

        if (!$item) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => 'vendor_update_item',
                'adapter' => $this->name(),
                'message' => "Item #{$itemId} not found or does not belong to your store.",
            ];
        }

        $prevState = [
            'price' => (float) $item->price,
            'status' => (int) $item->status,
        ];

        if (isset($params['price'])) {
            $item->price = (float) $params['price'];
        }
        if (isset($params['status'])) {
            $item->status = (int) $params['status'];
        }
        $item->save();

        $fresh = $item->fresh();
        $verified = true;
        if (isset($params['price']) && (float) $fresh->price !== (float) $params['price']) {
            $verified = false;
        }

        $this->logAuditEvent('vendor_item_updated', [
            'item_id' => $itemId,
            'vendor_id' => $vendorId,
            'previous_state' => $prevState,
            'new_state' => ['price' => (float) $fresh->price, 'status' => (int) $fresh->status],
        ]);

        return [
            'success' => true,
            'verified' => $verified,
            'tool' => 'vendor_update_item',
            'adapter' => $this->name(),
            'message' => "Item '{$item->name}' updated successfully.",
            'previous_state' => $prevState,
            'new_state' => ['price' => (float) $fresh->price, 'status' => (int) $fresh->status],
        ];
    }

    private function analyzeDriverShortage(array $params): array
    {
        $market = $params['market'] ?? 'Houston';
        $shortageCount = (int) ($params['shortage_count'] ?? 10);

        $networkService = app(\App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService::class);
        $analysis = $networkService->analyzeShortageAndRecommend($market, $shortageCount);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'analyze_driver_shortage',
            'adapter' => $this->name(),
            'message' => "Driver shortage analysis complete for {$market}. Current available fleet: {$analysis['current_available_fleet']}, Net shortage gap: {$analysis['net_shortage_gap']}.",
            'data' => $analysis,
        ];
    }

    private function addVendorDriver(array $params, array $context): array
    {
        $vendorId = (int) ($context['vendor_id'] ?? $context['admin_id'] ?? 1);
        $networkService = app(\App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService::class);
        $driver = $networkService->addVendorDriver($vendorId, $params);

        $this->logAuditEvent('vendor_driver_registered', [
            'driver_id' => $driver->id,
            'vendor_id' => $vendorId,
            'phone' => $driver->phone,
        ]);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'add_vendor_driver',
            'adapter' => $this->name(),
            'message' => "Driver #{$driver->id} ({$driver->f_name} {$driver->l_name}) added for review. Awaiting Urban Goodz admin approval.",
            'data' => [
                'driver_id' => $driver->id,
                'status' => $driver->admin_approval_status,
                'network_dispatch_status' => $driver->network_dispatch_status,
            ],
        ];
    }

    private function configureDriverPay(array $params, array $context): array
    {
        $driverId = (int) ($params['driver_id'] ?? 0);
        $networkService = app(\App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService::class);
        $res = $networkService->configureCompensation($driverId, $params);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'configure_driver_pay',
            'adapter' => $this->name(),
            'message' => $res['message'],
            'data' => $res,
        ];
    }

    private function approveDriver(array $params, array $context): array
    {
        $driverId = (int) ($params['driver_id'] ?? 0);
        $adminId = (int) ($context['admin_id'] ?? 1);

        $networkService = app(\App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService::class);
        $res = $networkService->approveDriver($driverId, $adminId);

        $this->logAuditEvent('driver_approved_by_admin', [
            'driver_id' => $driverId,
            'admin_id' => $adminId,
        ]);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'approve_driver',
            'adapter' => $this->name(),
            'message' => $res['message'],
            'previous_state' => 'pending_approval',
            'new_state' => 'approved',
            'data' => $res,
        ];
    }

    private function suspendDriver(array $params, array $context): array
    {
        $driverId = (int) ($params['driver_id'] ?? 0);
        $reason = $params['reason'] ?? 'Administrative suspension';

        $networkService = app(\App\Services\UrbanGoodz\Agent\UrbanGoodzDriverNetworkService::class);
        $res = $networkService->suspendDriver($driverId, $reason);

        $this->logAuditEvent('driver_suspended', [
            'driver_id' => $driverId,
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'verified' => true,
            'tool' => 'suspend_driver',
            'adapter' => $this->name(),
            'message' => $res['message'],
            'previous_state' => 'active',
            'new_state' => 'suspended',
            'data' => $res,
        ];
    }

    private function logAuditEvent(string $event, array $metadata): void
    {
        try {
            AiAuditEvent::create([
                'event_type' => $event,
                'policy_decision' => 'allowed',
                'request_metadata' => $metadata,
                'actor_type' => 'admin',
                'actor_id' => $metadata['actor_id'] ?? null,
                'status' => 'completed',
                'severity' => 'info',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not write AiAuditEvent: ' . $e->getMessage());
        }
    }
}
