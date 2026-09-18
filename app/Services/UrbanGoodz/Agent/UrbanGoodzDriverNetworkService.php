<?php

namespace App\Services\UrbanGoodz\Agent;

use App\CentralLogics\Helpers;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UrbanGoodzDriverNetworkService
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ON_BUSINESS_JOB = 'on_business_job';
    public const STATUS_AVAILABLE_FOR_UG = 'available_for_ug';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_SUSPENDED = 'suspended';

    public const OWNERSHIP_UG = 'urban_goodz';
    public const OWNERSHIP_VENDOR = 'vendor_owned';
    public const OWNERSHIP_BUSINESS = 'business_owned';

    public const PAY_PER_ORDER = 'per_order';
    public const PAY_PER_MILE = 'per_mile';
    public const PAY_FLAT_ROUTE = 'flat_route';
    public const PAY_HOURLY = 'hourly';
    public const PAY_PERCENTAGE = 'percentage';

    /**
     * Order states a vendor may still put a driver on. Anything past
     * handover already has a driver or is finished; take-away and dine-in
     * orders never get one.
     */
    public const ASSIGNABLE_ORDER_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover'];

    /**
     * States a driver is free to take a vendor's business order from.
     * available_for_ug means "free, and also opted into the UG pool".
     */
    public const ASSIGNABLE_DRIVER_STATUSES = [self::STATUS_AVAILABLE, self::STATUS_AVAILABLE_FOR_UG];

    /**
     * 1. Add a vendor-owned driver (requires Urban Goodz approval).
     */
    public function addVendorDriver(int $vendorId, array $data): DeliveryMan
    {
        $vendor = Vendor::findOrFail($vendorId);
        $primaryStore = Store::where('vendor_id', $vendorId)->first();

        return DeliveryMan::create([
            'f_name' => $data['f_name'] ?? 'Driver',
            'l_name' => $data['l_name'] ?? '',
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'identity_number' => $data['identity_number'] ?? null,
            'identity_type' => $data['identity_type'] ?? 'passport',
            'password' => bcrypt($data['password'] ?? 'Driver@123'),
            'zone_id' => $data['zone_id'] ?? $primaryStore?->zone_id ?? 1,
            'vendor_id' => $vendorId,
            'store_id' => $primaryStore?->id,
            'ownership_type' => self::OWNERSHIP_VENDOR,
            'application_status' => 'pending',
            'admin_approval_status' => 'pending',
            'network_dispatch_status' => self::STATUS_PENDING_APPROVAL,
            'active' => 0, // Inactive until Urban Goodz admin approval
            'available_for_marketplace' => (bool) ($data['available_for_marketplace'] ?? false),
            'pay_model' => $data['pay_model'] ?? self::PAY_PER_ORDER,
            'pay_rate' => (float) ($data['pay_rate'] ?? 15.00),
            'platform_fee_percent' => (float) ($data['platform_fee_percent'] ?? 5.00),
            'platform_fee_fixed' => (float) ($data['platform_fee_fixed'] ?? 1.50),
        ]);
    }

    /**
     * Urban Goodz final approval of a driver.
     */
    public function approveDriver(int $driverId, int $adminId): array
    {
        $driver = DeliveryMan::findOrFail($driverId);

        $driver->update([
            'admin_approval_status' => 'approved',
            'application_status' => 'approved',
            'approved_by_admin_at' => now(),
            'active' => 1,
            'network_dispatch_status' => self::STATUS_AVAILABLE,
        ]);

        return [
            'success' => true,
            'driver_id' => $driver->id,
            'status' => 'approved',
            'message' => "Driver #{$driver->id} ({$driver->f_name} {$driver->l_name}) approved by Urban Goodz.",
        ];
    }

    /**
     * Urban Goodz suspension authority over any driver.
     */
    public function suspendDriver(int $driverId, string $reason = 'Administrative action'): array
    {
        $driver = DeliveryMan::findOrFail($driverId);

        $driver->update([
            'admin_approval_status' => 'suspended',
            'active' => 0,
            'network_dispatch_status' => self::STATUS_SUSPENDED,
        ]);

        return [
            'success' => true,
            'driver_id' => $driver->id,
            'status' => 'suspended',
            'message' => "Driver #{$driver->id} suspended: {$reason}.",
        ];
    }

    /**
     * Configure vendor driver pay model and marketplace availability.
     */
    public function configureCompensation(int $driverId, array $config): array
    {
        $driver = DeliveryMan::findOrFail($driverId);

        $driver->update([
            'pay_model' => $config['pay_model'] ?? $driver->pay_model,
            'pay_rate' => isset($config['pay_rate']) ? (float) $config['pay_rate'] : $driver->pay_rate,
            'available_for_marketplace' => isset($config['available_for_marketplace']) ? (bool) $config['available_for_marketplace'] : $driver->available_for_marketplace,
            'platform_fee_percent' => isset($config['platform_fee_percent']) ? (float) $config['platform_fee_percent'] : $driver->platform_fee_percent,
        ]);

        return [
            'success' => true,
            'driver_id' => $driver->id,
            'pay_model' => $driver->pay_model,
            'pay_rate' => (float) $driver->pay_rate,
            'available_for_marketplace' => (bool) $driver->available_for_marketplace,
            'platform_fee_percent' => (float) $driver->platform_fee_percent,
            'message' => 'Driver compensation model and marketplace availability updated.',
        ];
    }

    /**
     * Compute driver compensation and platform fee for an order.
     */
    public function calculateCompensation(DeliveryMan $driver, Order $order, float $miles = 3.5, float $hours = 0.5): array
    {
        $rate = (float) $driver->pay_rate;
        $orderAmount = (float) $order->order_amount;

        $driverGross = match ($driver->pay_model) {
            self::PAY_PER_MILE => round($rate * $miles, 2),
            self::PAY_HOURLY => round($rate * $hours, 2),
            self::PAY_PERCENTAGE => round($orderAmount * ($rate / 100), 2),
            self::PAY_FLAT_ROUTE => round($rate, 2),
            default => round($rate, 2), // per_order
        };

        // Urban Goodz configurable platform fee on qualifying deliveries
        $pctFee = round($driverGross * ((float) $driver->platform_fee_percent / 100), 2);
        $fixedFee = (float) $driver->platform_fee_fixed;
        $totalPlatformFee = round($pctFee + $fixedFee, 2);
        $driverNet = max(0.00, round($driverGross - $totalPlatformFee, 2));

        return [
            'pay_model' => $driver->pay_model,
            'pay_rate' => $rate,
            'driver_gross' => $driverGross,
            'platform_admin_fee' => $totalPlatformFee,
            'platform_fee_percent' => (float) $driver->platform_fee_percent,
            'platform_fee_fixed' => $fixedFee,
            'driver_net_payout' => $driverNet,
        ];
    }

    /**
     * Assign a driver to a business-owned order.
     * Prevents double assignment; driver becomes unavailable to UG marketplace dispatch.
     */
    public function assignToBusinessOrder(int $driverId, int $orderId): array
    {
        // Locked so two concurrent assigns cannot both see the driver or the
        // order as free and double-book either of them.
        $result = DB::transaction(function () use ($driverId, $orderId) {
            $driver = DeliveryMan::withoutGlobalScopes()->lockForUpdate()->findOrFail($driverId);
            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($orderId);
            $store = Store::withoutGlobalScopes()->find($order->store_id);

            // Cross-vendor order hijack guard: the order's store must belong to
            // the same vendor that owns this driver. Without this check, a
            // vendor could pass an arbitrary order_id belonging to another
            // vendor's store and have this service reassign it.
            if (!$driver->vendor_id || !$store || (int) $store->vendor_id !== (int) $driver->vendor_id) {
                return [
                    'success' => false,
                    'message' => "Order does not belong to this driver's vendor.",
                ];
            }

            if ($driver->admin_approval_status !== 'approved' || (int) $driver->active !== 1) {
                return [
                    'success' => false,
                    'message' => "Driver #{$driverId} is not approved or inactive.",
                ];
            }

            // Double assignment & conflicting availability check
            if ((int) $driver->current_orders > 0 || !in_array($driver->network_dispatch_status, self::ASSIGNABLE_DRIVER_STATUSES, true)) {
                return [
                    'success' => false,
                    'message' => "Driver #{$driverId} is not available for a new job.",
                ];
            }

            // Without these, assigning could reopen a delivered or canceled
            // order as 'confirmed', or silently take an order off a driver
            // who already has it and leave that driver stuck on_business_job.
            if (!in_array($order->order_type, ['delivery', 'parcel'], true)) {
                return ['success' => false, 'message' => "Order #{$orderId} is not a delivery order."];
            }
            if (!in_array($order->order_status, self::ASSIGNABLE_ORDER_STATUSES, true)) {
                return ['success' => false, 'message' => "Order #{$orderId} is {$order->order_status} and can no longer take a driver."];
            }
            if ($order->delivery_man_id) {
                return ['success' => false, 'message' => "Order #{$orderId} already has a driver. Release them first."];
            }

            $driver->update([
                'network_dispatch_status' => self::STATUS_ON_BUSINESS_JOB,
                'current_orders' => (int) $driver->current_orders + 1,
            ]);
            $driver->increment('assigned_order_count');

            // Same transition admin assignment makes (Admin\OrderController::
            // add_delivery_man): the driver has the job, so pending/confirmed
            // becomes accepted; an order the store is already processing or
            // handing over keeps its status.
            $order->delivery_man_id = $driverId;
            $order->order_status = in_array($order->order_status, ['pending', 'confirmed'], true) ? 'accepted' : $order->order_status;
            $order->accepted = now();
            $order->save();

            return [
                'success' => true,
                'driver_id' => $driverId,
                'order_id' => $orderId,
                'dispatch_status' => self::STATUS_ON_BUSINESS_JOB,
                'message' => "Driver assigned to business order. Driver is now marked ON BUSINESS JOB and unavailable to UG dispatch.",
            ];
        });

        if ($result['success']) {
            $this->notifyAssignment($orderId, $driverId);
        }

        return $result;
    }

    /**
     * Tell the driver (and the customer) about a vendor assignment, the same
     * notifications admin assignment sends. Runs after commit and never
     * fails the assignment: a missing FCM token must not undo a real job.
     */
    private function notifyAssignment(int $orderId, int $driverId): void
    {
        try {
            $order = Order::withoutGlobalScopes()->with(['store', 'customer', 'guest', 'module'])->find($orderId);
            $driver = DeliveryMan::withoutGlobalScopes()->find($driverId);
            if (!$order || !$driver) {
                return;
            }

            if (Helpers::getNotificationStatusData('deliveryman', 'deliveryman_order_assign_unassign', 'push_notification_status')) {
                $data = [
                    'title' => translate('Order_Notification'),
                    'description' => translate('messages.you_are_assigned_to_a_order'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                if ($driver->fcm_token) {
                    Helpers::send_push_notif_to_device($driver->fcm_token, $data);
                }
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'delivery_man_id' => $driver->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $customerToken = $order->is_guest == 0 ? $order->customer?->cm_firebase_token : $order->guest?->fcm_token;
            $message = Helpers::order_status_update_message('accepted', $order->module?->module_type, $order->customer?->current_language_key ?? 'en');
            $message = Helpers::text_variable_data_format(
                value: $message,
                store_name: $order->store?->name,
                order_id: $order->id,
                user_name: trim("{$order->customer?->f_name} {$order->customer?->l_name}"),
                delivery_man_name: trim("{$driver->f_name} {$driver->l_name}")
            );
            if ($message && $customerToken && Helpers::getNotificationStatusData('customer', 'customer_order_notification', 'push_notification_status')) {
                Helpers::send_push_notif_to_device($customerToken, [
                    'title' => translate('Order_Notification'),
                    'description' => $message,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Vendor driver assignment notification failed', [
                'order_id' => $orderId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The vendor's orders that assignToBusinessOrder() would accept right
     * now, for the vendor app's order picker. Built from the same constants
     * so the picker can never offer an order the assign call then rejects.
     */
    public function assignableOrdersForVendor(int $vendorId, int $limit = 50): array
    {
        $storeIds = Store::withoutGlobalScopes()->where('vendor_id', $vendorId)->pluck('id');

        return Order::withoutGlobalScopes()
            ->with(['customer:id,f_name,l_name', 'store:id,name'])
            ->whereIn('store_id', $storeIds)
            ->whereNull('delivery_man_id')
            ->whereIn('order_type', ['delivery', 'parcel'])
            ->whereIn('order_status', self::ASSIGNABLE_ORDER_STATUSES)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (Order $order) {
                $address = is_string($order->delivery_address) ? json_decode($order->delivery_address, true) : $order->delivery_address;

                return [
                    'id' => $order->id,
                    'order_status' => $order->order_status,
                    'order_type' => $order->order_type,
                    'order_amount' => (float) $order->order_amount,
                    'payment_method' => $order->payment_method,
                    'store_name' => $order->store?->name,
                    'customer_name' => trim("{$order->customer?->f_name} {$order->customer?->l_name}") ?: null,
                    'delivery_address' => is_array($address) ? ($address['address'] ?? null) : null,
                    'schedule_at' => $order->schedule_at,
                    // Order casts created_at to a plain string, not Carbon.
                    'created_at' => $order->created_at ? (string) $order->created_at : null,
                ];
            })
            ->all();
    }

    /**
     * Release driver from business job.
     * If available_for_marketplace is ON, driver becomes available to Urban Goodz marketplace.
     */
    public function releaseFromBusinessOrder(int $driverId, int $orderId): array
    {
        return DB::transaction(function () use ($driverId, $orderId) {
            $driver = DeliveryMan::withoutGlobalScopes()->lockForUpdate()->findOrFail($driverId);
            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($orderId);
            $store = Store::withoutGlobalScopes()->find($order->store_id);

            // Same cross-vendor guard as assignToBusinessOrder(): only the
            // vendor that owns this driver may release it from an order, and
            // only when that order actually belongs to one of their stores.
            if (!$driver->vendor_id || !$store || (int) $store->vendor_id !== (int) $driver->vendor_id) {
                return [
                    'success' => false,
                    'message' => "Order does not belong to this driver's vendor.",
                ];
            }

            // Otherwise any of the vendor's orders could be used to knock a
            // driver's current_orders down while they are mid-delivery.
            if ((int) $order->delivery_man_id !== (int) $driverId) {
                return ['success' => false, 'message' => "Driver #{$driverId} is not on order #{$orderId}."];
            }

            // The goods are with the driver once picked up; the driver closes
            // that out from their app (delivered/failed), not the vendor.
            if ($order->order_status === 'picked_up') {
                return ['success' => false, 'message' => "Order #{$orderId} is already picked up and cannot be released."];
            }

            $stillOpen = in_array($order->order_status, self::ASSIGNABLE_ORDER_STATUSES, true);
            if ($stillOpen) {
                // Unassign so the order can be given to another driver.
                $order->update(['delivery_man_id' => null]);
            }

            $nextStatus = $driver->available_for_marketplace ? self::STATUS_AVAILABLE_FOR_UG : self::STATUS_AVAILABLE;

            // A finished order was already taken off current_orders by the
            // driver app when it closed; only an open one still counts.
            $driver->update([
                'network_dispatch_status' => $nextStatus,
                'current_orders' => $stillOpen ? max(0, (int) $driver->current_orders - 1) : (int) $driver->current_orders,
            ]);

            return [
                'success' => true,
                'driver_id' => $driverId,
                'network_dispatch_status' => $nextStatus,
                'available_for_ug' => (bool) $driver->available_for_marketplace,
                'message' => $driver->available_for_marketplace
                    ? "Driver released. Available for Urban Goodz marketplace orders."
                    : "Driver released. Available for business orders only.",
            ];
        });
    }

    /**
     * Assign driver to an Urban Goodz general marketplace order.
     * Enforces shared network rules and prevents conflicting availability.
     */
    public function assignToMarketplaceOrder(int $driverId, int $orderId): array
    {
        $driver = DeliveryMan::findOrFail($driverId);
        $order = Order::withoutGlobalScopes()->findOrFail($orderId);

        // 1. Approval check
        if ($driver->admin_approval_status !== 'approved' || (int) $driver->active !== 1) {
            return ['success' => false, 'message' => "Driver is not approved."];
        }

        // 2. Conflicting availability check (working for business)
        if ($driver->network_dispatch_status === self::STATUS_ON_BUSINESS_JOB) {
            return [
                'success' => false,
                'message' => "Conflicting availability: Driver is currently assigned to a business order.",
            ];
        }

        // 3. Shared network permission check
        if ($driver->ownership_type !== self::OWNERSHIP_UG && !$driver->available_for_marketplace) {
            return [
                'success' => false,
                'message' => "This driver belongs to a business and has not opted into the Urban Goodz shared marketplace pool.",
            ];
        }

        // 4. Double assignment check
        if ((int) $driver->current_orders > 0) {
            return ['success' => false, 'message' => "Driver already has an active delivery assigned."];
        }

        $driver->update([
            'network_dispatch_status' => self::STATUS_ON_BUSINESS_JOB,
            'current_orders' => 1,
        ]);

        $order->update([
            'delivery_man_id' => $driverId,
            'order_status' => 'confirmed',
        ]);

        return [
            'success' => true,
            'driver_id' => $driverId,
            'order_id' => $orderId,
            'message' => "Driver assigned to Urban Goodz marketplace order.",
        ];
    }

    /**
     * Complete marketplace delivery and record payout breakdown.
     */
    public function completeMarketplaceDelivery(int $driverId, int $orderId, float $miles = 3.5): array
    {
        $driver = DeliveryMan::findOrFail($driverId);
        $order = Order::withoutGlobalScopes()->findOrFail($orderId);

        $comp = $this->calculateCompensation($driver, $order, $miles);

        $order->update(['order_status' => 'delivered']);

        // Record driver earning
        if (DB::getSchemaBuilder()->hasTable('urban_goodz_driver_earnings')) {
            DB::table('urban_goodz_driver_earnings')->insert([
                'delivery_man_id' => $driverId,
                'order_id' => $orderId,
                'earning_type' => 'delivery_compensation',
                'payout_model' => $comp['pay_model'],
                'gross_cents' => (int) round($comp['driver_gross'] * 100),
                'admin_fee_cents' => (int) round($comp['platform_admin_fee'] * 100),
                'net_cents' => (int) round($comp['driver_net_payout'] * 100),
                'amount' => $comp['driver_net_payout'],
                'currency' => 'USD',
                'status' => 'pending',
                'description' => "Marketplace delivery: gross \${$comp['driver_gross']}, UG admin fee \${$comp['platform_admin_fee']}, net \${$comp['driver_net_payout']}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $nextStatus = $driver->available_for_marketplace ? self::STATUS_AVAILABLE_FOR_UG : self::STATUS_AVAILABLE;
        $driver->update([
            'network_dispatch_status' => $nextStatus,
            'current_orders' => max(0, (int) $driver->current_orders - 1),
            'order_count' => (int) $driver->order_count + 1,
        ]);

        return [
            'success' => true,
            'order_id' => $orderId,
            'driver_id' => $driverId,
            'compensation' => $comp,
            'network_dispatch_status' => $nextStatus,
            'message' => "Order #{$orderId} delivered. Driver net payout: \${$comp['driver_net_payout']}, Urban Goodz admin fee: \${$comp['platform_admin_fee']}.",
        ];
    }

    /**
     * Fleet summary: driver counts by network_dispatch_status and today's
     * order counts by outcome. Shared by VendorDriverManagementController's
     * vendor-scoped summary() endpoint and the admin Fleet Operations page
     * (platform-wide, $vendorId = null) so the counting logic lives in one
     * place instead of being duplicated across the two controllers.
     */
    public function fleetOperationsSummary(?int $vendorId = null): array
    {
        $driverQuery = DeliveryMan::query();
        if ($vendorId) {
            $driverQuery->where('vendor_id', $vendorId);
        }

        $driverIds = (clone $driverQuery)->pluck('id');

        $statusCounts = (clone $driverQuery)
            ->selectRaw('network_dispatch_status, count(*) as aggregate')
            ->groupBy('network_dispatch_status')
            ->pluck('aggregate', 'network_dispatch_status');

        $drivers = [
            'total' => (int) $driverIds->count(),
            'available' => (int) ($statusCounts[self::STATUS_AVAILABLE] ?? 0),
            'on_business_job' => (int) ($statusCounts[self::STATUS_ON_BUSINESS_JOB] ?? 0),
            'offline' => (int) ($statusCounts[self::STATUS_OFFLINE] ?? 0),
            'pending_approval' => (int) ($statusCounts[self::STATUS_PENDING_APPROVAL] ?? 0),
            'suspended' => (int) ($statusCounts[self::STATUS_SUSPENDED] ?? 0),
        ];

        $todayOrders = Order::withoutGlobalScopes()
            ->whereIn('delivery_man_id', $driverIds)
            ->whereDate('created_at', now()->toDateString());

        $deliveries = [
            'total' => (clone $todayOrders)->count(),
            'completed' => (clone $todayOrders)->where('order_status', 'delivered')->count(),
            'in_progress' => (clone $todayOrders)->whereIn('order_status', ['confirmed', 'processing', 'handover', 'picked_up'])->count(),
            'failed' => (clone $todayOrders)->whereIn('order_status', ['canceled', 'failed', 'refunded'])->count(),
        ];

        return [
            'drivers' => $drivers,
            'deliveries_today' => $deliveries,
        ];
    }

    /**
     * Driver Network Capacity analytics for Admin & Monique.
     */
    public function getNetworkCapacity(?int $zoneId = null): array
    {
        $query = DeliveryMan::query();
        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        $totalDrivers = (clone $query)->count();
        $ugDrivers = (clone $query)->where('ownership_type', self::OWNERSHIP_UG)->count();
        $vendorDrivers = (clone $query)->where('ownership_type', self::OWNERSHIP_VENDOR)->count();
        $businessDrivers = (clone $query)->where('ownership_type', self::OWNERSHIP_BUSINESS)->count();

        $activeApproved = (clone $query)->where('admin_approval_status', 'approved')->where('active', 1)->count();
        $pendingApproval = (clone $query)->where('admin_approval_status', 'pending')->count();
        $suspended = (clone $query)->where('admin_approval_status', 'suspended')->count();
        $inactive = (clone $query)->where('admin_approval_status', 'approved')->where('active', 0)->count();

        $onJob = (clone $query)->where('network_dispatch_status', self::STATUS_ON_BUSINESS_JOB)->count();
        $availableForUG = (clone $query)->where(function ($q) {
            $q->where('ownership_type', self::OWNERSHIP_UG)
              ->orWhere('available_for_marketplace', 1);
        })->where('active', 1)->where('network_dispatch_status', '!=', self::STATUS_ON_BUSINESS_JOB)->count();

        return [
            'total_registered' => $totalDrivers,
            'fleet_breakdown' => [
                'urban_goodz_recruited' => $ugDrivers,
                'vendor_owned' => $vendorDrivers,
                'business_owned' => $businessDrivers,
            ],
            'status_breakdown' => [
                'active_approved' => $activeApproved,
                'pending_approval' => $pendingApproval,
                'suspended' => $suspended,
                'inactive_qualified' => $inactive,
            ],
            'realtime_capacity' => [
                'on_active_job' => $onJob,
                'available_for_urban_goodz' => $availableForUG,
            ],
        ];
    }

    /**
     * Driver shortage analysis for Monique Chief of Staff.
     * Example: "Monique, we're short 25 drivers in Houston Saturday evening."
     */
    public function analyzeShortageAndRecommend(string $marketName, int $shortageCount): array
    {
        $zone = Zone::where('name', 'like', "%{$marketName}%")->first();
        $zoneId = $zone?->id;

        $capacity = $this->getNetworkCapacity($zoneId);

        $availableUG = $capacity['realtime_capacity']['available_for_urban_goodz'];
        $pendingApplicants = $capacity['status_breakdown']['pending_approval'];
        $inactiveQualified = $capacity['status_breakdown']['inactive_qualified'];

        // Shared vendor fleet available for UG
        $sharedVendorAvailable = DeliveryMan::where('ownership_type', self::OWNERSHIP_VENDOR)
            ->where('available_for_marketplace', 1)
            ->where('admin_approval_status', 'approved')
            ->where('active', 1)
            ->where('network_dispatch_status', '!=', self::STATUS_ON_BUSINESS_JOB)
            ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
            ->count();

        $netGap = max(0, $shortageCount - $availableUG);

        $recommendations = [];
        if ($pendingApplicants > 0) {
            $recommendations[] = "Expedite review of {$pendingApplicants} pending driver applicants in {$marketName}.";
        }
        if ($inactiveQualified > 0) {
            $recommendations[] = "Send surge reactivation push to {$inactiveQualified} inactive qualified drivers.";
        }
        if ($sharedVendorAvailable > 0) {
            $recommendations[] = "Mobilize {$sharedVendorAvailable} approved vendor-owned drivers currently opted into shared marketplace dispatch.";
        }
        if ($netGap > 0) {
            $recommendations[] = "Launch a driver referral campaign with a \$50 incentive to close the remaining {$netGap}-driver deficit.";
        }

        return [
            'market' => $marketName,
            'target_shortage' => $shortageCount,
            'current_available_fleet' => $availableUG,
            'shared_vendor_fleet_available' => $sharedVendorAvailable,
            'inactive_qualified_drivers' => $inactiveQualified,
            'pending_applicants' => $pendingApplicants,
            'net_shortage_gap' => $netGap,
            'actionable_recommendations' => $recommendations,
        ];
    }
}
