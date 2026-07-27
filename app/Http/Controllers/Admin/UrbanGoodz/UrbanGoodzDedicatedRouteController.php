<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteBatch;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzRouteAssignment;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Models\UrbanGoodzClientInvoice;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\DeliveryMan;
use App\Models\Admin;
use App\Services\UrbanGoodzDriverDispatchNotificationService;
use App\Services\UrbanGoodz\DedicatedRouteOptimizationService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UrbanGoodzDedicatedRouteController extends Controller
{
    // ===== DEDICATED ROUTES =====

    public function index()
    {
        $routes = UrbanGoodzDedicatedRoute::with(['client', 'driver'])->latest()->paginate(25);
        return view('admin-views.urban-goodz.dedicated-routes.index', compact('routes'));
    }

    public function create()
    {
        $clients = UrbanGoodzBusinessClient::where('status', 'approved')->get();
        return view('admin-views.urban-goodz.dedicated-routes.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_client_id' => ['required', 'integer', 'exists:urban_goodz_business_clients,id'],
            'route_name' => ['required', 'string', 'max:255'],
            'route_type' => ['required', Rule::in(UrbanGoodzDedicatedRoute::ROUTE_TYPES)],
            'pickup_location' => ['nullable', 'string'],
            'pickup_lat' => ['nullable', 'numeric'],
            'pickup_lng' => ['nullable', 'numeric'],
            'scheduled_date' => ['nullable', 'date'],
            'recurring_rule' => ['nullable', 'string', 'max:50'],
            'max_packages_per_batch' => ['nullable', 'integer', 'min:1', 'max:200'],
            'vehicle_type_required' => ['nullable', 'string', 'max:100'],
            'driver_pay_per_package' => ['nullable', 'numeric', 'min:0'],
            'business_charge_per_package' => ['nullable', 'numeric', 'min:0'],
            'pickup_bonus' => ['nullable', 'numeric', 'min:0'],
            'route_completion_bonus' => ['nullable', 'numeric', 'min:0'],
            'priority_package_bonus' => ['nullable', 'numeric', 'min:0'],
            'failed_delivery_partial_pay' => ['nullable', 'numeric', 'min:0'],
            'return_to_sender_pay' => ['nullable', 'numeric', 'min:0'],
            'instant_payout_allowed' => ['nullable', 'boolean'],
            'weekly_payout_allowed' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth('admin')->id();
        $data['instant_payout_allowed'] = $request->boolean('instant_payout_allowed', true);
        $data['weekly_payout_allowed'] = $request->boolean('weekly_payout_allowed', true);

        UrbanGoodzDedicatedRoute::create($data);

        Toastr::success(translate('Dedicated route created successfully'));
        return redirect()->route('admin.urban-goodz.dedicated-routes.index');
    }

    public function show($id)
    {
        $route = UrbanGoodzDedicatedRoute::with([
            'client', 'driver', 'batches', 'packages', 'assignments.driver', 'optimizationStops.package',
        ])->findOrFail($id);

        $drivers = DeliveryMan::where('application_status', 'approved')->where('active', 1)->get();

        return view('admin-views.urban-goodz.dedicated-routes.show', compact('route', 'drivers'));
    }

    public function update($id, Request $request)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($id);

        $data = $request->validate([
            'route_name' => ['sometimes', 'string', 'max:255'],
            'pickup_location' => ['nullable', 'string'],
            'vehicle_type_required' => ['nullable', 'string', 'max:100'],
            'driver_pay_per_package' => ['nullable', 'numeric', 'min:0'],
            'business_charge_per_package' => ['nullable', 'numeric', 'min:0'],
            'pickup_bonus' => ['nullable', 'numeric', 'min:0'],
            'route_completion_bonus' => ['nullable', 'numeric', 'min:0'],
            'priority_package_bonus' => ['nullable', 'numeric', 'min:0'],
            'failed_delivery_partial_pay' => ['nullable', 'numeric', 'min:0'],
            'return_to_sender_pay' => ['nullable', 'numeric', 'min:0'],
            'instant_payout_allowed' => ['nullable', 'boolean'],
            'weekly_payout_allowed' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string'],
            'max_packages_per_batch' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['nullable', Rule::in(UrbanGoodzDedicatedRoute::STATUSES)],
        ]);

        if ($request->has('status') && $data['status'] === 'completed') {
            $data['route_completed_at'] = now();
        }

        if ($request->has('status') && $data['status'] === 'in_progress' && !$route->route_started_at) {
            $data['route_started_at'] = now();
        }

        $route->update($data);

        Toastr::success(translate('Dedicated route updated'));
        return back();
    }

    public function assignDriver($id, Request $request)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($id);

        $data = $request->validate([
            'assigned_driver_id' => ['required', 'integer', 'exists:delivery_men,id'],
        ]);

        $route->assigned_driver_id = $data['assigned_driver_id'];

        if ($route->status === 'pending') {
            $route->status = 'active';
        }

        $route->save();

        UrbanGoodzRouteAssignment::create([
            'dedicated_route_id' => $route->id,
            'delivery_man_id' => $data['assigned_driver_id'],
            'status' => 'assigned',
            'assigned_by' => auth('admin')->id(),
        ]);

        app(UrbanGoodzDriverDispatchNotificationService::class)
            ->notifyDedicatedRouteAssigned($route);

        Toastr::success(translate('Driver assigned to route'));
        return back();
    }

    public function destroy($id)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($id);
        $route->delete();

        Toastr::success(translate('Dedicated route removed'));
        return redirect()->route('admin.urban-goodz.dedicated-routes.index');
    }

    // ===== PACKAGES =====

    public function packages($id)
    {
        $route = UrbanGoodzDedicatedRoute::with(['client', 'packages.scans', 'batches'])->findOrFail($id);

        return view('admin-views.urban-goodz.dedicated-routes.packages', compact('route'));
    }

    public function packageShow($routeId, $packageId)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($routeId);
        $package = UrbanGoodzRoutePackage::with(['scans.scanner', 'batch', 'optimizationStop', 'custodyLogs', 'earnings'])->findOrFail($packageId);

        return view('admin-views.urban-goodz.dedicated-routes.package-show', compact('route', 'package'));
    }

    public function packageStore(Request $request)
    {
        $data = $request->validate([
            'dedicated_route_id' => ['required', 'integer', 'exists:urban_goodz_dedicated_routes,id'],
            'business_client_id' => ['required', 'integer', 'exists:urban_goodz_business_clients,id'],
            'tracking_id' => ['nullable', 'string', 'max:255', 'unique:urban_goodz_route_packages,tracking_id'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:urban_goodz_route_packages,barcode'],
            'qr_code' => ['nullable', 'string', 'max:255', 'unique:urban_goodz_route_packages,qr_code'],
            'pickup_location_id' => ['nullable', 'integer'],
            'pickup_contact_name' => ['nullable', 'string', 'max:255'],
            'pickup_contact_phone' => ['nullable', 'string', 'max:50'],
            'pickup_address' => ['nullable', 'string'],
            'dropoff_name' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['required', 'string'],
            'dropoff_phone' => ['nullable', 'string', 'max:50'],
            'dropoff_lat' => ['nullable', 'numeric'],
            'dropoff_lng' => ['nullable', 'numeric'],
            'delivery_window_start' => ['nullable', 'date'],
            'delivery_window_end' => ['nullable', 'date', 'after_or_equal:delivery_window_start'],
            'package_type' => ['nullable', Rule::in(UrbanGoodzRoutePackage::PACKAGE_TYPES)],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(UrbanGoodzRoutePackage::PRIORITIES)],
            'requires_signature' => ['nullable', 'boolean'],
            'requires_photo' => ['nullable', 'boolean'],
            'requires_custody' => ['nullable', 'boolean'],
            'temperature_requirement' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['tracking_id'] = $data['tracking_id'] ?? UrbanGoodzRoutePackage::nextTrackingId();
        $data['requires_signature'] = $request->boolean('requires_signature', false);
        $data['requires_photo'] = $request->boolean('requires_photo', false);
        $data['requires_custody'] = $request->boolean('requires_custody', false);

        $package = UrbanGoodzRoutePackage::create($data);

        $route = UrbanGoodzDedicatedRoute::find($data['dedicated_route_id']);
        if ($route) {
            $route->increment('total_packages');
        }

        Toastr::success(translate('Package added to route'));
        return back();
    }

    public function packageBulkStore(Request $request)
    {
        $data = $request->validate([
            'dedicated_route_id' => ['required', 'integer', 'exists:urban_goodz_dedicated_routes,id'],
            'business_client_id' => ['required', 'integer', 'exists:urban_goodz_business_clients,id'],
            'packages_csv' => ['required', 'string'],
        ]);

        $route = UrbanGoodzDedicatedRoute::findOrFail($data['dedicated_route_id']);
        $routeId = $route->id;
        $clientId = $data['business_client_id'];

        $lines = explode("\n", $data['packages_csv']);
        $header = str_getcsv(array_shift($lines));
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $row = str_getcsv($line);
                $packageData = array_combine($header, $row);

                UrbanGoodzRoutePackage::create([
                    'dedicated_route_id' => $routeId,
                    'business_client_id' => $clientId,
                    'tracking_id' => $packageData['tracking_id'] ?? UrbanGoodzRoutePackage::nextTrackingId(),
                    'external_reference' => $packageData['external_reference'] ?? null,
                    'dropoff_name' => $packageData['dropoff_name'] ?? null,
                    'dropoff_address' => $packageData['dropoff_address'] ?? '',
                    'dropoff_phone' => $packageData['dropoff_phone'] ?? null,
                    'package_type' => $packageData['package_type'] ?? 'parcel',
                    'weight' => $packageData['weight'] ?? null,
                    'priority' => $packageData['priority'] ?? 'normal',
                    'notes' => $packageData['notes'] ?? null,
                ]);
                $count++;
            }

            $route->increment('total_packages', $count);
            DB::commit();

            Toastr::success(translate("$count packages imported from CSV"));
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(translate('CSV import failed: ') . $e->getMessage());
        }

        return back();
    }

    public function packageUpdateStatus($routeId, $packageId, Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(UrbanGoodzRoutePackage::STATUSES)],
            'exception_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)->findOrFail($packageId);

        $validTransitions = [
            'pending' => ['picked_up', 'failed', 'return_required', 'admin_review'],
            'picked_up' => ['in_transit', 'delivered', 'failed', 'return_required'],
            'in_transit' => ['delivered', 'failed', 'return_required'],
            'delivered' => ['completed'],
            'failed' => ['pending', 'picked_up', 'return_required'],
            'return_required' => ['returning_to_pickup', 'returning_to_hub'],
            'returning_to_pickup' => ['returned_to_pickup'],
            'returning_to_hub' => ['returned_to_hub'],
            'returning_to_business' => ['returned_to_business'],
        ];

        $currentStatus = $package->status;
        $newStatus = $data['status'];

        if (isset($validTransitions[$currentStatus]) && !in_array($newStatus, $validTransitions[$currentStatus])) {
            Toastr::error(translate('Invalid status transition from') . " {$currentStatus} " . translate('to') . " {$newStatus}");
            return back();
        }

        $package->status = $newStatus;
        $package->exception_reason = $data['exception_reason'] ?? $package->exception_reason;
        $package->notes = $data['notes'] ?? $package->notes;

        if ($newStatus === 'delivered') {
            $package->dropoff_scanned_at = now();
            $package->dropoff_scanned_by = auth('admin')->id();
        }

        $package->save();

        // Check if dedicated route is completed
        $route = $package->route;
        if ($route && $route->assigned_driver_id) {
            $totalCount = $route->packages()->count();
            $completedCount = $route->completed_packages + $route->failed_packages;
            if ($completedCount >= $totalCount && $route->status !== 'completed') {
                $route->status = 'completed';
                $route->save();

                // Trigger Payout Calculation & Log Earnings
                try {
                    $driverPricingService = resolve(\App\Services\UrbanGoodz\UrbanGoodzDriverPricingService::class);
                    $packageCount = $route->packages()->count();
                    $routeRevenue = (float) ($route->route_offer_amount ?? ($route->business_charge_per_package * $packageCount));
                    $baseAmount = (float) ($route->driver_pay_per_package * $packageCount);
                    $payoutResult = $driverPricingService->calculatePayout('dedicated_routes', [
                        'zone_id' => $route->client?->zone_id,
                        'mileage' => $route->estimated_miles ?? 0.00,
                        'duration' => $route->estimated_duration ?? 0.00,
                        'stops' => $packageCount,
                        'packages' => $packageCount,
                        'revenue' => $routeRevenue,
                        'base_amount' => $baseAmount,
                        'vehicle_id' => $route->driver?->vehicle_id,
                    ]);

                    $driverPricingService->recordEarning([
                        'delivery_man_id' => $route->assigned_driver_id,
                        'dedicated_route_id' => $route->id,
                        'earning_type' => 'dedicated_routes',
                        'amount' => $payoutResult['payout'],
                        'status' => 'approved', // Credits wallet immediately
                        'description' => "Payout for completing dedicated route: {$route->route_name}",
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to calculate/record dedicated route payout: " . $e->getMessage());
                }
            }
        }

        UrbanGoodzPackageScan::create([
            'package_id' => $package->id,
            'scan_type' => match ($newStatus) {
                'delivered' => 'dropoff',
                'failed' => 'exception',
                'return_required', 'returning_to_pickup', 'returning_to_hub' => 'return_scan',
                default => 'admin_override',
            },
            'scanned_by' => auth('admin')->id(),
            'scanner_type' => 'admin',
            'notes' => $data['notes'] ?? null,
            'exception_reason' => $data['exception_reason'] ?? null,
        ]);

        $route = $package->route;
        if ($route) {
            if ($newStatus === 'delivered' && $currentStatus !== 'delivered') {
                $route->increment('completed_packages');
            }
            if ($newStatus === 'failed' && $currentStatus !== 'failed') {
                $route->increment('failed_packages');
            }
            if ($newStatus === 'return_required' || str_starts_with($newStatus, 'returning_')) {
                $route->increment('returned_packages');
            }
        }

        Toastr::success(translate('Package status updated'));
        return back();
    }

    public function markMissing($routeId, $packageId, Request $request)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)->findOrFail($packageId);

        if (in_array($package->status, ['delivered', 'return_required', 'returning_to_pickup', 'returning_to_hub'])) {
            Toastr::error(translate('Cannot mark package as missing in current status'));
            return back();
        }

        $package->status = 'failed';
        $package->exception_reason = 'Missing package: ' . $data['reason'];
        $package->save();

        UrbanGoodzPackageScan::create([
            'package_id' => $package->id,
            'scan_type' => 'exception',
            'scanned_by' => auth('admin')->id(),
            'scanner_type' => 'admin',
            'exception_reason' => 'Missing package: ' . $data['reason'],
            'notes' => $data['reason'],
        ]);

        $route = $package->route;
        if ($route) {
            $route->increment('failed_packages');
        }

        Toastr::success(translate('Package marked as missing'));
        return back();
    }

    public function initiateReturn($routeId, $packageId, Request $request)
    {
        $data = $request->validate([
            'return_location' => ['required', 'string', 'in:pickup,hub,business'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $package = UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)->findOrFail($packageId);

        if (in_array($package->status, ['pending', 'return_required', 'returning_to_pickup', 'returning_to_hub'])) {
            Toastr::error(translate('Package cannot be returned in current status'));
            return back();
        }

        $returnStatus = match ($data['return_location']) {
            'pickup' => 'returning_to_pickup',
            'hub' => 'returning_to_hub',
            'business' => 'returning_to_business',
        };

        $package->status = $returnStatus;
        $package->return_required = true;
        $package->return_location = $data['return_location'];
        $package->exception_reason = 'Return initiated: ' . $data['reason'];
        $package->save();

        UrbanGoodzPackageScan::create([
            'package_id' => $package->id,
            'scan_type' => 'return_scan',
            'scanned_by' => auth('admin')->id(),
            'scanner_type' => 'admin',
            'exception_reason' => 'Return initiated: ' . $data['reason'],
        ]);

        $route = $package->route;
        if ($route) {
            $route->increment('returned_packages');
            if ($route->return_to_sender_pay > 0) {
                if ($route->assigned_driver_id) {
                    UrbanGoodzDriverEarning::create([
                        'delivery_man_id' => $route->assigned_driver_id,
                        'package_id' => $package->id,
                        'dedicated_route_id' => $routeId,
                        'earning_type' => 'return_pay',
                        'amount' => $route->return_to_sender_pay,
                        'status' => 'pending',
                        'description' => 'Return pay for package ' . $package->tracking_id,
                    ]);
                }
            }
        }

        Toastr::success(translate('Return initiated for package'));
        return back();
    }

    public function completeRoute($id, Request $request)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($id);

        if (!in_array($route->status, ['active', 'in_progress'])) {
            Toastr::error(translate('Route is not in an active state'));
            return back();
        }

        $pendingPackages = UrbanGoodzRoutePackage::where('dedicated_route_id', $id)
            ->whereIn('status', ['pending', 'picked_up', 'in_transit', 'ready_for_route'])
            ->count();

        if ($pendingPackages > 0 && !$request->boolean('force_complete')) {
            Toastr::error(translate('Route has') . " {$pendingPackages} " . translate('pending packages. Use force_complete to override.'));
            return back();
        }

        $route->update([
            'status' => 'completed',
            'route_completed_at' => now(),
        ]);

        UrbanGoodzRouteAssignment::where('dedicated_route_id', $id)
            ->where('delivery_man_id', $route->assigned_driver_id)
            ->update([
                'status' => 'completed',
                'route_completed_at' => now(),
            ]);

        UrbanGoodzRouteBatch::where('dedicated_route_id', $id)
            ->where('status', 'in_transit')
            ->update(['status' => 'completed', 'completed_at' => now()]);

        if ($route->assigned_driver_id && $route->route_completion_bonus > 0) {
            UrbanGoodzDriverEarning::create([
                'delivery_man_id' => $route->assigned_driver_id,
                'dedicated_route_id' => $id,
                'earning_type' => 'completion_bonus',
                'amount' => $route->route_completion_bonus,
                'status' => 'pending',
                'description' => 'Route completion bonus: ' . $route->route_name,
            ]);
        }

        $completedPackages = UrbanGoodzRoutePackage::where('dedicated_route_id', $id)->delivered()->count();
        $failedPackages = UrbanGoodzRoutePackage::where('dedicated_route_id', $id)->failed()->count();
        $totalPackages = $route->total_packages;

        Toastr::success(translate('Route completed') . ": {$completedPackages}/{$totalPackages} " . translate('packages delivered') . ", {$failedPackages} " . translate('failed'));
        return back();
    }

    // ===== SCANS =====

    public function packageScans($routeId, $packageId)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($routeId);
        $package = UrbanGoodzRoutePackage::with(['scans.scanner', 'batch'])->findOrFail($packageId);

        return view('admin-views.urban-goodz.dedicated-routes.scans', compact('route', 'package'));
    }

    // ===== OPTIMIZATION =====

    public function optimize($id, DedicatedRouteOptimizationService $optimizer)
    {
        $route = UrbanGoodzDedicatedRoute::findOrFail($id);
        try {
            $result = $optimizer->optimize(
                $route,
                (bool) $route->return_to_origin,
                'admin',
                auth('admin')->id()
            );
            Toastr::success($result['changed']
                ? translate('Route optimized and persisted')
                : translate('Route measured; no shorter valid order was found'));
        } catch (\Throwable $exception) {
            Toastr::error(translate('Optimization failed: ') . $exception->getMessage());
        }

        return back();
    }

    // ===== REPORTS =====

    public function report($id)
    {
        $route = UrbanGoodzDedicatedRoute::with([
            'client', 'driver', 'packages', 'batches',
            'optimizationStops.package', 'earnings',
        ])->findOrFail($id);

        return view('admin-views.urban-goodz.dedicated-routes.report', compact('route'));
    }

    public function exportReport($id, Request $request)
    {
        $route = UrbanGoodzDedicatedRoute::with(['client', 'packages', 'batches'])->findOrFail($id);

        $format = $request->format ?? 'csv';

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="route-' . $route->id . '-report.csv"',
            ];

            $callback = function () use ($route) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Tracking ID', 'Dropoff Name', 'Dropoff Address', 'Status', 'Package Type', 'Weight', 'Priority', 'Pickup Scanned At', 'Dropoff Scanned At', 'Exception']);

                foreach ($route->packages as $pkg) {
                    fputcsv($handle, [
                        $pkg->tracking_id, $pkg->dropoff_name, $pkg->dropoff_address,
                        $pkg->status, $pkg->package_type, $pkg->weight, $pkg->priority,
                        $pkg->pickup_scanned_at, $pkg->dropoff_scanned_at, $pkg->exception_reason,
                    ]);
                }

                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        Toastr::error(translate('Unsupported export format'));
        return back();
    }

    // ===== DRIVER PAYOUTS =====

    public function driverPayouts()
    {
        $payouts = UrbanGoodzDriverPayoutRequest::with(['driver', 'approver'])->latest()->paginate(25);

        $stats = [
            'total_pending' => UrbanGoodzDriverPayoutRequest::where('status', 'pending')->sum('requested_amount'),
            'total_paid' => UrbanGoodzDriverPayoutRequest::where('status', 'paid')->sum('net_amount'),
            'total_fees' => UrbanGoodzDriverPayoutRequest::where('status', 'paid')->sum('instant_fee'),
            'pending_count' => UrbanGoodzDriverPayoutRequest::where('status', 'pending')->count(),
        ];

        return view('admin-views.urban-goodz.driver-payouts.index', compact('payouts', 'stats'));
    }

    public function driverPayoutShow($id)
    {
        $payout = UrbanGoodzDriverPayoutRequest::with(['driver', 'approver'])->findOrFail($id);

        return view('admin-views.urban-goodz.driver-payouts.show', compact('payout'));
    }

    public function driverPayoutApprove($id, Request $request)
    {
        $payout = UrbanGoodzDriverPayoutRequest::findOrFail($id);

        if ($payout->status !== 'pending') {
            Toastr::error(translate('Payout is not in pending status'));
            return back();
        }

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $payout->status = 'approved';
        $payout->approved_by = auth('admin')->id();
        $payout->approved_at = now();
        $payout->admin_notes = $data['admin_notes'] ?? $payout->admin_notes;
        $payout->save();

        UrbanGoodzDriverEarning::where('delivery_man_id', $payout->delivery_man_id)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
            ]);

        Toastr::success(translate('Payout approved'));
        return back();
    }

    public function driverPayoutPay($id)
    {
        $payout = UrbanGoodzDriverPayoutRequest::findOrFail($id);

        if (!in_array($payout->status, ['approved', 'processing'])) {
            Toastr::error(translate('Payout must be approved first'));
            return back();
        }

        $payout->status = 'paid';
        $payout->paid_at = now();
        $payout->save();

        UrbanGoodzDriverEarning::where('delivery_man_id', $payout->delivery_man_id)
            ->whereIn('status', ['approved'])
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

        $idempotencyKey = "driver_payout_{$payout->id}";
        $existingLedger = UrbanGoodzPaymentLedger::where('idempotency_key', $idempotencyKey)->first();
        if (!$existingLedger) {
            UrbanGoodzPaymentLedger::create([
                'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
                'feature' => 'driver_payout',
                'payable_type' => DeliveryMan::class,
                'payable_id' => $payout->delivery_man_id,
                'event_type' => 'payout_completed',
                'direction' => 'outbound',
                'amount' => $payout->net_amount,
                'currency' => 'USD',
                'payment_method' => $payout->payout_type,
                'payment_status' => 'completed',
                'idempotency_key' => $idempotencyKey,
                'delivery_man_id' => $payout->delivery_man_id,
                'created_by_admin_id' => auth('admin')->id(),
                'metadata' => [
                    'payout_id' => $payout->id,
                    'requested_amount' => $payout->requested_amount,
                    'instant_fee' => $payout->instant_fee,
                    'net_amount' => $payout->net_amount,
                    'payout_type' => $payout->payout_type,
                ],
            ]);
        }

        Toastr::success(translate('Payout marked as paid'));
        return back();
    }

    public function driverPayoutReject($id, Request $request)
    {
        $payout = UrbanGoodzDriverPayoutRequest::findOrFail($id);

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $payout->status = 'rejected';
        $payout->admin_notes = $data['admin_notes'] ?? $payout->admin_notes;
        $payout->save();

        Toastr::success(translate('Payout rejected'));
        return back();
    }

    // ===== EARNINGS (Admin view) =====

    public function driverEarnings()
    {
        $earnings = UrbanGoodzDriverEarning::with(['driver', 'package', 'route'])->latest()->paginate(25);

        $totals = [
            'pending' => UrbanGoodzDriverEarning::where('status', 'pending')->sum('amount'),
            'approved' => UrbanGoodzDriverEarning::where('status', 'approved')->sum('amount'),
            'paid' => UrbanGoodzDriverEarning::where('status', 'paid')->sum('amount'),
            'total' => UrbanGoodzDriverEarning::sum('amount'),
        ];

        return view('admin-views.urban-goodz.driver-payouts.earnings', compact('earnings', 'totals'));
    }
}
