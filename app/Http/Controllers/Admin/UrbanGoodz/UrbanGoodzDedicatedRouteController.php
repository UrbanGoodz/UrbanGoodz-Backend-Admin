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
use App\Models\DeliveryMan;
use App\Models\Admin;
use App\Services\UrbanGoodzDriverDispatchNotificationService;
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
        $package->status = $data['status'];
        $package->exception_reason = $data['exception_reason'] ?? $package->exception_reason;
        $package->notes = $data['notes'] ?? $package->notes;

        if ($data['status'] === 'delivered') {
            $package->dropoff_scanned_at = now();
            $package->dropoff_scanned_by = auth('admin')->id();

            $route = $package->route;
            if ($route) {
                $route->increment('completed_packages');
            }
        }

        if ($data['status'] === 'failed') {
            $package->exception_reason = $data['exception_reason'] ?? 'Manual exception by admin';

            $route = $package->route;
            if ($route) {
                $route->increment('failed_packages');
            }
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
            'scan_type' => $data['status'] === 'delivered' ? 'dropoff' : ($data['status'] === 'failed' ? 'exception' : $data['status']),
            'scanned_by' => auth('admin')->id(),
            'scanner_type' => 'admin',
            'notes' => $data['notes'] ?? null,
            'exception_reason' => $data['exception_reason'] ?? null,
        ]);

        Toastr::success(translate('Package status updated'));
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

    public function optimize($id)
    {
        $route = UrbanGoodzDedicatedRoute::with('packages')->findOrFail($id);
        $packages = $route->packages()->whereIn('status', ['pending', 'picked_up', 'in_transit'])->get();

        if ($packages->isEmpty()) {
            Toastr::info(translate('No packages to optimize'));
            return back();
        }

        UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $route->id)->delete();

        $sorted = $this->optimizeStopOrder($packages, $route);
        $batchSize = $route->max_packages_per_batch ?: 50;
        $batches = $sorted->chunk($batchSize);

        DB::beginTransaction();
        try {
            $batchNumber = 1;
            foreach ($batches as $batchPackages) {
                $batch = UrbanGoodzRouteBatch::create([
                    'dedicated_route_id' => $route->id,
                    'batch_number' => 'BATCH-' . str_pad($batchNumber, 3, '0', STR_PAD_LEFT),
                    'package_count' => $batchPackages->count(),
                    'status' => 'pending',
                ]);

                $stopOrder = 1;
                foreach ($batchPackages as $pkg) {
                    $pkg->route_batch_id = $batch->id;
                    $pkg->save();

                    UrbanGoodzRouteOptimizationStop::create([
                        'dedicated_route_id' => $route->id,
                        'package_id' => $pkg->id,
                        'stop_order' => $stopOrder,
                        'status' => 'pending',
                    ]);
                    $stopOrder++;
                }
                $batchNumber++;
            }
            DB::commit();

            Toastr::success(translate('Route optimized into ') . ($batchNumber - 1) . translate(' batches'));
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(translate('Optimization failed: ') . $e->getMessage());
        }

        return back();
    }

    private function optimizeStopOrder($packages, $route)
    {
        $originLat = $route->pickup_lat;
        $originLng = $route->pickup_lng;

        $sorted = $packages->sort(function ($a, $b) use ($originLat, $originLng) {
            if ($b->priority === 'urgent' || $b->priority === 'medical') return 1;
            if ($a->priority === 'urgent' || $a->priority === 'medical') return -1;

            if ($a->priority === 'high' && $b->priority !== 'high') return -1;
            if ($b->priority === 'high' && $a->priority !== 'high') return 1;

            if ($a->delivery_window_start && $b->delivery_window_start) {
                return $a->delivery_window_start->timestamp <=> $b->delivery_window_start->timestamp;
            }

            if ($originLat && $originLng && $a->dropoff_lat && $b->dropoff_lat) {
                $distA = $this->haversine($originLat, $originLng, $a->dropoff_lat, $a->dropoff_lng);
                $distB = $this->haversine($originLat, $originLng, $b->dropoff_lat, $b->dropoff_lng);
                return $distA <=> $distB;
            }

            return $a->id <=> $b->id;
        });

        return $sorted->values();
    }

    private function haversine($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 3959;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * asin(sqrt($a));
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
