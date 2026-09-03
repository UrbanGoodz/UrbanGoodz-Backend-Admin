<?php

namespace App\Services\UrbanGoodz\Agent;

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
        $driver = DeliveryMan::findOrFail($driverId);
        $order = Order::withoutGlobalScopes()->findOrFail($orderId);

        if ($driver->admin_approval_status !== 'approved' || (int) $driver->active !== 1) {
            return [
                'success' => false,
                'message' => "Driver #{$driverId} is not approved or inactive.",
            ];
        }

        // Double assignment & conflicting availability check
        if ((int) $driver->current_orders > 0 || $driver->network_dispatch_status === self::STATUS_ON_BUSINESS_JOB) {
            return [
                'success' => false,
                'message' => "Driver #{$driverId} is already actively delivering another job.",
            ];
        }

        $driver->update([
            'network_dispatch_status' => self::STATUS_ON_BUSINESS_JOB,
            'current_orders' => (int) $driver->current_orders + 1,
        ]);

        $order->update([
            'delivery_man_id' => $driverId,
            'order_status' => 'confirmed',
        ]);

        return [
            'success' => true,
            'driver_id' => $driverId,
            'order_id' => $orderId,
            'dispatch_status' => self::STATUS_ON_BUSINESS_JOB,
            'message' => "Driver assigned to business order. Driver is now marked ON BUSINESS JOB and unavailable to UG dispatch.",
        ];
    }

    /**
     * Release driver from business job.
     * If available_for_marketplace is ON, driver becomes available to Urban Goodz marketplace.
     */
    public function releaseFromBusinessOrder(int $driverId, int $orderId): array
    {
        $driver = DeliveryMan::findOrFail($driverId);
        $order = Order::withoutGlobalScopes()->findOrFail($orderId);

        $nextStatus = $driver->available_for_marketplace ? self::STATUS_AVAILABLE_FOR_UG : self::STATUS_AVAILABLE;

        $driver->update([
            'network_dispatch_status' => $nextStatus,
            'current_orders' => max(0, (int) $driver->current_orders - 1),
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
