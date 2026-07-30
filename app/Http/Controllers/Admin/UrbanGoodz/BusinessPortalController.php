<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzBusinessClientLocation;
use App\Models\UrbanGoodzBusinessClientDocument;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzClientInvoice;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\DedicatedRouteOptimizationService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BusinessPortalController extends Controller
{
    protected function getClientId()
    {
        return auth('business')->user()->business_client_id;
    }

    protected function checkPermission(string $permission): bool
    {
        $user = auth('business')->user();
        if (!$user) return false;
        if ($user->role === 'owner_admin') return true;
        $userPermissions = $user->permissions ?? [];
        return in_array($permission, $userPermissions);
    }

    protected function requirePermission(string $permission)
    {
        if (!$this->checkPermission($permission)) {
            abort(403, translate('messages.access_denied'));
        }
    }

    public function dashboard()
    {
        $clientId = $this->getClientId();

        $data = [
            'client' => UrbanGoodzBusinessClient::find($clientId),
            'routes_count' => UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->count(),
            'active_routes_count' => UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->whereIn('status', ['active', 'in_progress'])->count(),
            'locations_count' => UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)->count(),
            'users_count' => UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)->count(),
            'documents_count' => UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)->count(),
            'invoices_count' => UrbanGoodzClientInvoice::where('business_client_id', $clientId)->count(),
            'jobs_count' => UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)->count(),
            'recent_routes' => UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->latest()->take(5)->get(),
            'packages_count' => UrbanGoodzRoutePackage::where('business_client_id', $clientId)->count(),
            'pool_count' => UrbanGoodzRoutePackage::where('business_client_id', $clientId)->whereNull('dedicated_route_id')->count(),
            'manifests_count' => UrbanGoodzManifest::where('business_client_id', $clientId)->count(),
            'active_manifests_count' => UrbanGoodzManifest::where('business_client_id', $clientId)->whereIn('status', ['draft', 'importing'])->count(),
        ];

        return view('business.dashboard', $data);
    }

    public function routes()
    {
        $clientId = $this->getClientId();
        $routes = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->latest()
            ->paginate(15);

        return view('business.routes.index', compact('routes'));
    }

    public function routeCreate()
    {
        $clientId = $this->getClientId();
        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return view('business.routes.create', compact('locations'));
    }

    public function routeStore(Request $request)
    {
        $this->requirePermission('scan_packages');
        $clientId = $this->getClientId();

        $request->validate([
            'route_name' => 'required|string|max:255',
            'route_type' => ['required', Rule::in(UrbanGoodzDedicatedRoute::ROUTE_TYPES)],
            'pickup_location' => 'required|string|max:255',
            'pickup_lat' => 'nullable|numeric|between:-90,90',
            'pickup_lng' => 'nullable|numeric|between:-180,180',
            'end_location' => 'nullable|string|max:255',
            'end_lat' => 'nullable|numeric|between:-90,90|required_with:end_lng',
            'end_lng' => 'nullable|numeric|between:-180,180|required_with:end_lat',
            'return_to_origin' => 'nullable|boolean',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'recurring_rule' => 'nullable|string|max:100',
            'capacity_packages' => 'nullable|integer|min:1|max:10000',
            'capacity_weight_lbs' => 'nullable|numeric|min:0|max:1000000',
            'stops' => 'required|array|min:1',
            'stops.*.dropoff_address' => 'required|string|max:255',
            'stops.*.recipient_name' => 'nullable|string|max:255',
            'stops.*.contact_phone' => 'nullable|string|max:50',
            'stops.*.package_type' => 'nullable|string|in:parcel,document,specimen,supply,pallet,envelope',
            'stops.*.delivery_notes' => 'nullable|string',
            'stops.*.delivery_window_start' => 'nullable|date',
            'stops.*.delivery_window_end' => 'nullable|date|after_or_equal:stops.*.delivery_window_start',
            'stops.*.priority' => ['nullable', Rule::in(UrbanGoodzRoutePackage::PRIORITIES)],
            'stops.*.weight' => 'nullable|numeric|min:0',
            'stops.*.stop_locked' => 'nullable|boolean',
            'stops.*.dropoff_lat' => 'nullable|numeric|between:-90,90|required_with:stops.*.dropoff_lng',
            'stops.*.dropoff_lng' => 'nullable|numeric|between:-180,180|required_with:stops.*.dropoff_lat',
        ]);

        $route = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $clientId,
            'route_name' => $request->route_name,
            'route_type' => $request->route_type,
            'source_module' => $request->route_type,
            'pickup_location' => $request->pickup_location,
            'pickup_lat' => $request->pickup_lat,
            'pickup_lng' => $request->pickup_lng,
            'end_location' => $request->boolean('return_to_origin') ? $request->pickup_location : $request->end_location,
            'end_lat' => $request->boolean('return_to_origin') ? $request->pickup_lat : $request->end_lat,
            'end_lng' => $request->boolean('return_to_origin') ? $request->pickup_lng : $request->end_lng,
            'return_to_origin' => $request->boolean('return_to_origin'),
            'scheduled_date' => $request->scheduled_date,
            'recurring_rule' => $request->recurring_rule,
            'capacity_packages' => $request->capacity_packages,
            'capacity_weight_lbs' => $request->capacity_weight_lbs,
            'status' => 'pending',
            'created_by' => auth('business')->id(),
            'total_packages' => count($request->stops),
        ]);

        $stopStartIndex = UrbanGoodzRoutePackage::max('id') ?? 0;
        foreach ($request->stops as $i => $stop) {
            UrbanGoodzRoutePackage::create([
                'dedicated_route_id' => $route->id,
                'business_client_id' => $clientId,
                'tracking_id' => 'UGP-' . now()->format('Ymd') . '-' . str_pad($stopStartIndex + $i + 1, 6, '0', STR_PAD_LEFT),
                'dropoff_name' => $stop['recipient_name'] ?? null,
                'dropoff_address' => $stop['dropoff_address'],
                'dropoff_phone' => $stop['contact_phone'] ?? null,
                'dropoff_lat' => $stop['dropoff_lat'] ?? null,
                'dropoff_lng' => $stop['dropoff_lng'] ?? null,
                'package_type' => $stop['package_type'] ?? 'parcel',
                'priority' => $stop['priority'] ?? 'normal',
                'weight' => $stop['weight'] ?? null,
                'delivery_window_start' => $stop['delivery_window_start'] ?? null,
                'delivery_window_end' => $stop['delivery_window_end'] ?? null,
                'stop_locked' => (bool) ($stop['stop_locked'] ?? false),
                'locked_stop_order' => !empty($stop['stop_locked']) ? $i + 1 : null,
                'source_module' => $request->route_type,
                'notes' => $stop['delivery_notes'] ?? null,
                'stop_order' => $i + 1,
                'status' => 'pending',
            ]);
        }

        Toastr::success(translate(':count stop(s) added to route', ['count' => count($request->stops)]));
        return redirect()->route('business.routes.show', $route->id);
    }

    public function routeShow($id)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->with([
                'packages.scans', 'batches', 'driver',
                'optimizationStops.package', 'optimizationHistory',
            ])
            ->findOrFail($id);

        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return view('business.routes.show', compact('route', 'locations'));
    }

    public function routeEdit($id)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->findOrFail($id);

        if (in_array($route->status, ['completed', 'in_progress', 'canceled'])) {
            Toastr::error(translate('Cannot edit a route that is :status', ['status' => $route->status]));
            return redirect()->route('business.routes.show', $id);
        }

        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return view('business.routes.edit', compact('route', 'locations'));
    }

    public function routeUpdate(Request $request, $id)
    {
        $this->requirePermission('scan_packages');
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->findOrFail($id);

        if (in_array($route->status, ['completed', 'in_progress', 'canceled'])) {
            Toastr::error(translate('Cannot update a route that is :status', ['status' => $route->status]));
            return redirect()->route('business.routes.show', $id);
        }

        $request->validate([
            'route_name' => 'required|string|max:255',
            'route_type' => ['required', Rule::in(UrbanGoodzDedicatedRoute::ROUTE_TYPES)],
            'pickup_location' => 'required|string|max:255',
            'pickup_lat' => 'nullable|numeric|between:-90,90',
            'pickup_lng' => 'nullable|numeric|between:-180,180',
            'end_location' => 'nullable|string|max:255',
            'end_lat' => 'nullable|numeric|between:-90,90|required_with:end_lng',
            'end_lng' => 'nullable|numeric|between:-180,180|required_with:end_lat',
            'return_to_origin' => 'nullable|boolean',
            'scheduled_date' => 'required|date',
            'recurring_rule' => 'nullable|string|max:100',
            'capacity_packages' => 'nullable|integer|min:1|max:10000',
            'capacity_weight_lbs' => 'nullable|numeric|min:0|max:1000000',
        ]);

        $route->update($request->only([
            'route_name', 'route_type', 'pickup_location',
            'pickup_lat', 'pickup_lng', 'end_location', 'end_lat', 'end_lng',
            'scheduled_date', 'recurring_rule', 'capacity_packages', 'capacity_weight_lbs',
        ]));
        $route->update([
            'source_module' => $request->route_type,
            'return_to_origin' => $request->boolean('return_to_origin'),
            'end_location' => $request->boolean('return_to_origin') ? $request->pickup_location : $request->end_location,
            'end_lat' => $request->boolean('return_to_origin') ? $request->pickup_lat : $request->end_lat,
            'end_lng' => $request->boolean('return_to_origin') ? $request->pickup_lng : $request->end_lng,
        ]);

        Toastr::success(translate('Route updated successfully'));
        return redirect()->route('business.routes.show', $id);
    }

    public function routeDestroy($id)
    {
        $this->requirePermission('scan_packages');
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->findOrFail($id);

        if (in_array($route->status, ['in_progress', 'completed'])) {
            Toastr::error(translate('Cannot delete a route that is in progress or completed'));
            return redirect()->route('business.routes.show', $id);
        }

        $route->packages()->delete();
        $route->optimizationStops()->delete();
        $route->delete();

        Toastr::success(translate('Route deleted successfully'));
        return redirect()->route('business.routes.index');
    }

    public function locations()
    {
        $clientId = $this->getClientId();
        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->latest()
            ->paginate(15);

        return view('business.locations.index', compact('locations'));
    }

    public function locationCreate()
    {
        $clientId = $this->getClientId();
        $types = UrbanGoodzBusinessClientLocation::TYPES;

        return view('business.locations.create', compact('types'));
    }

    public function locationStore(Request $request)
    {
        $this->requirePermission('business_locations_manage');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['business_client_id'] = $clientId;
        $data['is_active'] = true;

        UrbanGoodzBusinessClientLocation::create($data);

        Toastr::success(translate('Location created successfully'));
        return redirect()->route('business.locations.index');
    }

    public function users()
    {
        $clientId = $this->getClientId();
        $users = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)
            ->latest()
            ->paginate(15);

        return view('business.users.index', compact('users'));
    }

    public function documents()
    {
        $clientId = $this->getClientId();
        $documents = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)
            ->latest()
            ->paginate(15);

        return view('business.documents.index', compact('documents'));
    }

    public function documentCreate()
    {
        $clientId = $this->getClientId();
        $types = UrbanGoodzBusinessClientDocument::TYPES;

        return view('business.documents.create', compact('types'));
    }

    public function documentStore(Request $request)
    {
        $this->requirePermission('business_documents_manage');
        $clientId = $this->getClientId();

        $request->validate([
            'document_type' => 'required|string|in:' . implode(',', UrbanGoodzBusinessClientDocument::TYPES),
            'document_name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt,csv|max:25600',
            'notes' => 'nullable|string|max:1000',
            'expires_at' => 'nullable|date',
        ]);

        $file = $request->file('file');
        $path = $file->store('business-documents/' . $clientId, 'public');

        UrbanGoodzBusinessClientDocument::create([
            'business_client_id' => $clientId,
            'uploaded_by' => auth('business')->id(),
            'document_type' => $request->document_type,
            'document_name' => $request->document_name,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $request->notes,
            'expires_at' => $request->expires_at,
            'status' => 'pending',
        ]);

        Toastr::success(translate('Document uploaded successfully'));
        return redirect()->route('business.documents.index');
    }

    public function invoices()
    {
        $clientId = $this->getClientId();
        $invoices = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->with('route')
            ->latest()
            ->paginate(15);

        return view('business.invoices.index', compact('invoices'));
    }

    public function invoiceShow($id)
    {
        $clientId = $this->getClientId();
        $invoice = UrbanGoodzClientInvoice::where('business_client_id', $clientId)
            ->with(['client', 'route'])
            ->findOrFail($id);

        return view('business.invoices.show', compact('invoice'));
    }

    public function routePackages($id)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->with(['packages.scans', 'batches'])
            ->findOrFail($id);

        return view('business.routes.packages.index', compact('route'));
    }

    public function routePackageCreate($id)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->findOrFail($id);

        return view('business.routes.packages.create', compact('route'));
    }

    public function routePackageStore($id, Request $request)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->findOrFail($id);

        $data = $request->validate([
            'dropoff_name' => 'nullable|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'dropoff_city' => 'nullable|string|max:255',
            'dropoff_state' => 'nullable|string|max:255',
            'dropoff_zip' => 'nullable|string|max:20',
            'dropoff_phone' => 'nullable|string|max:50',
            'delivery_notes' => 'nullable|string',
            'package_type' => 'nullable|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'priority' => 'nullable|string|max:50',
            'delivery_window_start' => 'nullable|date_format:H:i',
            'delivery_window_end' => 'nullable|date_format:H:i|after:delivery_window_start',
        ]);

        UrbanGoodzRoutePackage::create([
            'dedicated_route_id' => $route->id,
            'business_client_id' => $clientId,
            'tracking_id' => UrbanGoodzRoutePackage::nextTrackingId(),
            'dropoff_name' => $data['dropoff_name'] ?? null,
            'dropoff_address' => $data['dropoff_address'],
            'dropoff_city' => $data['dropoff_city'] ?? null,
            'dropoff_state' => $data['dropoff_state'] ?? null,
            'dropoff_zip' => $data['dropoff_zip'] ?? null,
            'dropoff_phone' => $data['dropoff_phone'] ?? null,
            'notes' => $data['delivery_notes'] ?? null,
            'package_type' => $data['package_type'] ?? 'parcel',
            'weight' => $data['weight'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'delivery_window_start' => $data['delivery_window_start'] ?? null,
            'delivery_window_end' => $data['delivery_window_end'] ?? null,
            'stop_order' => $route->packages()->count() + 1,
            'status' => 'pending',
        ]);

        $route->increment('total_packages');

        Toastr::success(translate('Package added to route'));
        return redirect()->route('business.routes.packages', $route->id);
    }

    public function routePackageUpload($id)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->findOrFail($id);

        return view('business.routes.packages.upload', compact('route'));
    }

    public function routePackageBulkStore($id, Request $request)
    {
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)->findOrFail($id);

        $request->validate([
            'packages_csv' => 'required|string',
        ]);

        $lines = explode("\n", $request->packages_csv);
        $header = str_getcsv(array_shift($lines));
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $row = str_getcsv($line);
                if (count($row) !== count($header)) continue;

                $pkgData = array_combine($header, $row);

                $nextId = UrbanGoodzRoutePackage::max('id') ?? 0;
                UrbanGoodzRoutePackage::create([
                    'dedicated_route_id' => $route->id,
                    'business_client_id' => $clientId,
                    'tracking_id' => 'UGP-' . now()->format('Ymd') . '-' . str_pad($nextId + $count + 1, 6, '0', STR_PAD_LEFT),
                    'dropoff_name' => $pkgData['dropoff_name'] ?? null,
                    'dropoff_address' => $pkgData['dropoff_address'] ?? '',
                    'dropoff_city' => $pkgData['dropoff_city'] ?? null,
                    'dropoff_state' => $pkgData['dropoff_state'] ?? null,
                    'dropoff_zip' => $pkgData['dropoff_zip'] ?? null,
                    'dropoff_phone' => $pkgData['dropoff_phone'] ?? null,
                    'package_type' => $pkgData['package_type'] ?? 'parcel',
                    'weight' => $pkgData['weight'] ?? null,
                    'priority' => $pkgData['priority'] ?? 'normal',
                    'notes' => $pkgData['notes'] ?? null,
                    'status' => 'pending',
                ]);
                $count++;
            }

            $route->increment('total_packages', $count);
            DB::commit();

            Toastr::success(translate(':count package(s) imported from CSV', ['count' => $count]));
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(translate('CSV import failed: ') . $e->getMessage());
        }

        return redirect()->route('business.routes.packages', $route->id);
    }

    public function manifests()
    {
        $clientId = $this->getClientId();

        $manifests = UrbanGoodzManifest::where('business_client_id', $clientId)
            ->latest()
            ->paginate(25);

        return view('business.manifests.index', compact('manifests'));
    }

    public function manifestCreate()
    {
        $clientId = $this->getClientId();

        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return view('business.manifests.create', compact('locations'));
    }

    public function manifestStore(Request $request)
    {
        $clientId = $this->getClientId();

        $data = $request->validate([
            'manifest_name' => 'nullable|string|max:255',
            'service_date' => 'required|date',
            'service_type' => 'nullable|string|in:' . implode(',', UrbanGoodzManifest::SERVICE_TYPES),
            'pickup_location_id' => 'nullable|exists:urban_goodz_business_client_locations,id',
            'pickup_location_text' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $manifest = UrbanGoodzManifest::create([
            'business_client_id' => $clientId,
            'manifest_name' => $data['manifest_name'] ?? null,
            'manifest_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'service_date' => $data['service_date'],
            'service_type' => $data['service_type'] ?? null,
            'pickup_location_id' => $data['pickup_location_id'] ?? null,
            'pickup_location_text' => $data['pickup_location_text'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'created_by' => auth('business')->id(),
        ]);

        Toastr::success(translate('Manifest created successfully'));
        return redirect()->route('business.manifests.show', $manifest->id);
    }

    public function manifestShow($id)
    {
        $clientId = $this->getClientId();

        $manifest = UrbanGoodzManifest::where('business_client_id', $clientId)
            ->with([
                'pickupLocation',
                'packages' => function ($q) {
                    $q->with('scannedByUser')->latest();
                },
            ])
            ->findOrFail($id);

        $packagesWithAddress = $manifest->packages->filter(function ($pkg) {
            return !empty($pkg->dropoff_address);
        })->count();

        $packagesMissingAddress = $manifest->packages->filter(function ($pkg) {
            return empty($pkg->dropoff_address);
        })->count();

        return view('business.manifests.show', compact('manifest', 'packagesWithAddress', 'packagesMissingAddress'));
    }

    public function scanPackages()
    {
        $clientId = $this->getClientId();
        $user = auth('business')->user();
        $client = $user->client;

        $recent = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->latest()
            ->take(10)
            ->get();

        $manifests = UrbanGoodzManifest::where('business_client_id', $clientId)
            ->whereIn('status', ['draft', 'importing'])
            ->latest()
            ->get();

        $activeManifest = null;
        if (request('manifest_id')) {
            $activeManifest = UrbanGoodzManifest::where('business_client_id', $clientId)
                ->whereIn('status', ['draft', 'importing'])
                ->where('id', request('manifest_id'))
                ->first();
        }

        return view('business.routes.packages.scan', compact('recent', 'user', 'client', 'manifests', 'activeManifest'));
    }

    public function manifestScan($id)
    {
        $clientId = $this->getClientId();
        $user = auth('business')->user();
        $client = $user->client;

        $manifest = UrbanGoodzManifest::where('business_client_id', $clientId)
            ->with(['packages' => function ($q) {
                $q->latest()->take(10);
            }])
            ->findOrFail($id);

        $manifests = UrbanGoodzManifest::where('business_client_id', $clientId)
            ->whereIn('status', ['draft', 'importing'])
            ->latest()
            ->get();

        return view('business.manifests.scan', compact('manifest', 'manifests', 'user', 'client'));
    }

    public function manifestPackages($id)
    {
        $clientId = $this->getClientId();

        $manifest = UrbanGoodzManifest::where('business_client_id', $clientId)
            ->with(['pickupLocation'])
            ->findOrFail($id);

        $packages = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->where('manifest_id', $id)
            ->with('scannedByUser')
            ->latest()
            ->paginate(25);

        return view('business.manifests.packages', compact('manifest', 'packages'));
    }

    public function scanStore(Request $request)
    {
        $clientId = $this->getClientId();
        $user = auth('business')->user();

        if (!$user->is_active || $user->status !== 'active') {
            return response()->json(['success' => false, 'message' => translate('Account is inactive')], 403);
        }

        if (!in_array('scan_packages', $user->permissions ?? []) && $user->role !== 'owner_admin') {
            return response()->json(['success' => false, 'message' => translate('You do not have scan permission')], 403);
        }

        $request->validate([
            'barcode' => 'required|string|max:255',
            'manifest_session_id' => 'nullable|string|max:36',
            'manifest_id' => 'nullable|integer|exists:urban_goodz_manifests,id',
        ]);

        $barcode = trim($request->barcode);

        $existing = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->where('barcode', $barcode)
            ->first();

        $manifest = null;
        if ($request->manifest_id) {
            $manifest = UrbanGoodzManifest::where('business_client_id', $clientId)
                ->whereIn('status', ['draft', 'importing'])
                ->where('id', $request->manifest_id)
                ->first();
        }

        if ($existing) {
            $existing->update([
                'scanned_at' => now(),
                'scanned_by' => $user->id,
                'manifest_id' => $existing->manifest_id ?? ($manifest->id ?? null),
                'manifest_session_id' => $existing->manifest_session_id ?? ($manifest->manifest_session_id ?? null),
            ]);

            if ($manifest && !$existing->manifest_id) {
                $existing->update(['manifest_id' => $manifest->id]);
                $manifest->increment('total_packages');
                $manifest->increment('scanned_packages');
            }

            return response()->json([
                'success' => true,
                'duplicate' => true,
                'package' => $existing,
                'message' => translate('Already scanned') . ': ' . $barcode,
                'session_id' => $existing->manifest_session_id,
            ]);
        }

        $sessionId = $manifest->manifest_session_id ?? $request->manifest_session_id ?? (string) \Illuminate\Support\Str::uuid();
        $manifestId = $manifest->id ?? null;

        $pkg = UrbanGoodzRoutePackage::create([
            'business_client_id' => $clientId,
            'manifest_id' => $manifestId,
            'tracking_id' => UrbanGoodzRoutePackage::nextTrackingId(),
            'barcode' => $barcode,
            'manifest_session_id' => $sessionId,
            'status' => 'pending_review',
            'scanned_by' => $user->id,
            'scanned_at' => now(),
        ]);

        if ($manifestId) {
            $manifest->increment('total_packages');
            $manifest->increment('scanned_packages');
        }

        return response()->json([
            'success' => true,
            'duplicate' => false,
            'package' => $pkg,
            'message' => translate('Package scanned') . ': ' . $barcode,
            'session_id' => $sessionId,
            'manifest_id' => $manifestId,
        ]);
    }

    public function packagePool()
    {
        $clientId = $this->getClientId();
        $routes = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->whereIn('status', ['pending', 'active'])
            ->latest()
            ->get();

        $packages = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->with('scannedByUser')
            ->latest()
            ->paginate(25);

        return view('business.routes.packages.pool', compact('packages', 'routes'));
    }

    public function assignPackageToRoute(Request $request, $id)
    {
        $clientId = $this->getClientId();

        $request->validate([
            'route_id' => 'required|exists:urban_goodz_dedicated_routes,id',
        ]);

        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->findOrFail($request->route_id);

        $package = UrbanGoodzRoutePackage::where('business_client_id', $clientId)
            ->whereNull('dedicated_route_id')
            ->findOrFail($id);

        $capacityPackages = (int) ($route->capacity_packages ?: $route->max_packages_per_batch);
        if ($capacityPackages > 0 && $route->packages()->count() >= $capacityPackages) {
            Toastr::error(translate('Route package capacity has been reached'));
            return redirect()->route('business.packages.pool');
        }
        $projectedWeight = (float) $route->packages()->sum('weight') + (float) ($package->weight ?? 0);
        if ($route->capacity_weight_lbs !== null
            && $projectedWeight > (float) $route->capacity_weight_lbs) {
            Toastr::error(translate('Route weight capacity would be exceeded'));
            return redirect()->route('business.packages.pool');
        }

        $nextOrder = $route->packages()->count() + 1;

        DB::transaction(function () use ($package, $route, $nextOrder, $clientId): void {
            $package->update([
                'dedicated_route_id' => $route->id,
                'source_module' => $package->source_module ?: ($route->source_module ?: $route->route_type),
                'stop_order' => $nextOrder,
                'status' => 'pending',
            ]);

            UrbanGoodzPackageScan::create([
                'package_id' => $package->id,
                'scan_type' => 'route_assignment',
                'scanned_by' => auth('business')->id(),
                'scanner_type' => 'business',
                'business_client_id' => $clientId,
                'dedicated_route_id' => $route->id,
                'input_method' => 'manual',
                'occurred_at' => now(),
                'received_at' => now(),
                'metadata' => [
                    'route_id' => $route->id,
                    'stop_order' => $nextOrder,
                    'source_module' => $route->source_module ?: $route->route_type,
                ],
            ]);

            $route->increment('total_packages');
        });

        Toastr::success(translate('Package assigned to route'));
        return redirect()->route('business.packages.pool');
    }

    public function routeOptimize(
        $id,
        Request $request,
        DedicatedRouteOptimizationService $optimizer
    )
    {
        $this->requirePermission('scan_packages');
        $clientId = $this->getClientId();
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
            ->findOrFail($id);

        $data = $request->validate([
            'end_location' => ['nullable', 'string', 'max:255'],
            'end_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:end_lng'],
            'end_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:end_lat'],
            'return_to_origin' => ['nullable', 'boolean'],
        ]);

        $returnToOrigin = $request->boolean('return_to_origin');
        $route->update([
            'end_location' => $returnToOrigin ? $route->pickup_location : ($data['end_location'] ?? $route->end_location),
            'end_lat' => $returnToOrigin ? $route->pickup_lat : ($data['end_lat'] ?? $route->end_lat),
            'end_lng' => $returnToOrigin ? $route->pickup_lng : ($data['end_lng'] ?? $route->end_lng),
            'return_to_origin' => $returnToOrigin,
        ]);

        try {
            $result = $optimizer->optimize(
                $route->fresh(),
                $returnToOrigin,
                'business',
                auth('business')->id()
            );
            $message = $result['changed']
                ? translate('Route optimized and saved')
                : translate('Route measured successfully; no shorter valid order was found');
            Toastr::success($message);
        } catch (\Throwable $exception) {
            Toastr::error(translate('Route optimization failed: ') . $exception->getMessage());
        }
        return redirect()->route('business.routes.show', $route->id);
    }

    public function routeManualOrder(
        $id,
        Request $request,
        DedicatedRouteOptimizationService $optimizer
    )
    {
        $this->requirePermission('scan_packages');
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $this->getClientId())->findOrFail($id);
        $data = $request->validate([
            'package_order' => ['required', 'array', 'min:1'],
            'package_order.*' => ['required', 'integer'],
        ]);
        try {
            $optimizer->applyManualOrder($route, $data['package_order'], 'business', auth('business')->id());
            Toastr::success(translate('Manual stop order saved'));
        } catch (\Throwable $exception) {
            Toastr::error(translate('Manual reorder failed: ') . $exception->getMessage());
        }
        return redirect()->route('business.routes.show', $route->id);
    }

    public function routeRestoreOriginal(
        $id,
        DedicatedRouteOptimizationService $optimizer
    ) {
        $this->requirePermission('scan_packages');
        $route = UrbanGoodzDedicatedRoute::where('business_client_id', $this->getClientId())->findOrFail($id);
        try {
            $optimizer->restoreOriginalOrder($route, 'business', auth('business')->id());
            Toastr::success(translate('Original stop order restored'));
        } catch (\Throwable $exception) {
            Toastr::error(translate('Restore failed: ') . $exception->getMessage());
        }
        return redirect()->route('business.routes.show', $route->id);
    }

    // =========================================================
    // PHASE B5-P1: LOCATION EDIT / UPDATE / DEACTIVATE
    // =========================================================

    public function locationEdit($id)
    {
        $clientId = $this->getClientId();
        $location = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->findOrFail($id);
        $types = UrbanGoodzBusinessClientLocation::TYPES;

        return view('business.locations.edit', compact('location', 'types'));
    }

    public function locationUpdate(Request $request, $id)
    {
        $this->requirePermission('business_locations_manage');
        $clientId = $this->getClientId();
        $location = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $location->update($data);

        Toastr::success(translate('Location updated successfully'));
        return redirect()->route('business.locations.index');
    }

    public function locationDeactivate($id)
    {
        $clientId = $this->getClientId();
        $location = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->findOrFail($id);

        $location->update(['is_active' => !$location->is_active]);

        $status = $location->is_active ? translate('activated') : translate('deactivated');
        Toastr::success(translate('Location :status', ['status' => $status]));
        return redirect()->route('business.locations.index');
    }

    // =========================================================
    // PHASE B5-P1: TEAM USER CREATE / EDIT / UPDATE / DEACTIVATE
    // =========================================================

    public function userCreate()
    {
        $clientId = $this->getClientId();
        $roles = UrbanGoodzBusinessClientUser::ROLES;

        return view('business.users.create', compact('roles'));
    }

    public function userStore(Request $request)
    {
        $this->requirePermission('business_users_manage');
        $clientId = $this->getClientId();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:urban_goodz_business_client_users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'string', 'in:' . implode(',', UrbanGoodzBusinessClientUser::ROLES)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        UrbanGoodzBusinessClientUser::create([
            'business_client_id' => $clientId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'password' => bcrypt($data['password']),
            'is_active' => true,
            'status' => 'active',
        ]);

        Toastr::success(translate('Team user created successfully'));
        return redirect()->route('business.users.index');
    }

    public function userEdit($id)
    {
        $clientId = $this->getClientId();
        $user = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)
            ->findOrFail($id);
        $roles = UrbanGoodzBusinessClientUser::ROLES;

        return view('business.users.edit', compact('user', 'roles'));
    }

    public function userUpdate(Request $request, $id)
    {
        $this->requirePermission('business_users_manage');
        $clientId = $this->getClientId();
        $user = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)
            ->findOrFail($id);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:urban_goodz_business_client_users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'string', 'in:' . implode(',', UrbanGoodzBusinessClientUser::ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        if ($data['is_active'] && $user->status !== 'active') {
            $user->update(['status' => 'active']);
        } elseif (!$data['is_active']) {
            $user->update(['status' => 'inactive']);
        }

        Toastr::success(translate('Team user updated successfully'));
        return redirect()->route('business.users.index');
    }

    public function userDeactivate($id)
    {
        $this->requirePermission('business_users_manage');
        $clientId = $this->getClientId();
        $user = UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)
            ->findOrFail($id);

        if ($user->id === auth('business')->id()) {
            Toastr::error(translate('You cannot deactivate your own account'));
            return redirect()->route('business.users.index');
        }

        $user->update([
            'is_active' => !$user->is_active,
            'status' => !$user->is_active ? 'active' : 'inactive',
        ]);

        $status = $user->is_active ? translate('activated') : translate('deactivated');
        Toastr::success(translate('User :status', ['status' => $status]));
        return redirect()->route('business.users.index');
    }

    // =========================================================
    // PHASE B5-P1: PROFILE / PASSWORD
    // =========================================================

    public function profile()
    {
        $user = auth('business')->user();

        return view('business.profile', compact('user'));
    }

    public function profileUpdate(Request $request)
    {
        $user = auth('business')->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
        ]);

        Toastr::success(translate('Profile updated successfully'));
        return redirect()->route('business.profile');
    }

    public function passwordChange(Request $request)
    {
        $user = auth('business')->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!\Hash::check($request->current_password, $user->password)) {
            Toastr::error(translate('Current password is incorrect'));
            return redirect()->route('business.profile');
        }

        $user->update([
            'password' => bcrypt($request->password),
        ]);

        Toastr::success(translate('Password changed successfully'));
        return redirect()->route('business.profile');
    }

    // =========================================================
    // PHASE B5-P1: DOCUMENT DOWNLOAD / DELETE
    // =========================================================

    public function documentDownload($id)
    {
        $clientId = $this->getClientId();
        $document = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)
            ->findOrFail($id);

        $path = storage_path('app/public/' . $document->file_path);

        if (!file_exists($path)) {
            Toastr::error(translate('File not found in storage'));
            return redirect()->route('business.documents.index');
        }

        $downloadName = $document->document_name . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION);

        return response()->download($path, $downloadName);
    }

    public function documentDelete($id)
    {
        $this->requirePermission('business_documents_manage');
        $clientId = $this->getClientId();
        $document = UrbanGoodzBusinessClientDocument::where('business_client_id', $clientId)
            ->findOrFail($id);

        $path = storage_path('app/public/' . $document->file_path);
        if (file_exists($path)) {
            @unlink($path);
        }

        $document->delete();

        Toastr::success(translate('Document deleted successfully'));
        return redirect()->route('business.documents.index');
    }

    // =========================================================
    // BUSINESS PORTAL LOAD BOARD
    // =========================================================

    public function loadBoardIndex(Request $request)
    {
        $clientId = $this->getClientId();
        
        $query = UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('origin_state')) {
            $query->where('origin_state', $request->origin_state);
        }
        if ($request->filled('destination_state')) {
            $query->where('destination_state', $request->destination_state);
        }
        if ($request->filled('load_type')) {
            $query->where('load_type', $request->load_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('load_number', 'like', "%{$search}%")
                  ->orWhere('origin_city', 'like', "%{$search}%")
                  ->orWhere('destination_city', 'like', "%{$search}%")
                  ->orWhere('commodity_description', 'like', "%{$search}%");
            });
        }

        $loads = $query->with('assignedDriver')
            ->latest()
            ->paginate(25);

        $stats = [
            'total' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)->count(),
            'available' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)->where('status', 'available')->count(),
            'active' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)->whereIn('status', ['assigned', 'in_transit', 'picked_up'])->count(),
            'completed' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)->where('status', 'delivered')->count(),
            'cancelled' => UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)->where('status', 'cancelled')->count(),
        ];

        return view('business.load-board.index', compact('loads', 'stats'));
    }

    public function loadBoardCreate()
    {
        $clientId = $this->getClientId();
        $locations = UrbanGoodzBusinessClientLocation::where('business_client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return view('business.load-board.create', compact('locations'));
    }

    public function loadBoardStore(Request $request)
    {
        $clientId = $this->getClientId();

        $validated = $request->validate([
            'load_number' => 'nullable|string|max:100',
            'origin_name' => 'nullable|string|max:255',
            'origin_city' => 'nullable|string|max:100',
            'origin_state' => 'nullable|string|max:2',
            'origin_zip' => 'nullable|string|max:10',
            'destination_name' => 'nullable|string|max:255',
            'destination_city' => 'nullable|string|max:100',
            'destination_state' => 'nullable|string|max:2',
            'destination_zip' => 'nullable|string|max:10',
            'distance_miles' => 'nullable|numeric|min:0',
            'payout_amount' => 'required|numeric|min:0',
            'payout_type' => 'nullable|string|in:flat,per_mile,per_hour',
            'rate_per_mile' => 'nullable|numeric|min:0',
            'load_type' => 'nullable|string|max:50',
            'equipment_type' => 'nullable|string|max:50',
            'weight_lbs' => 'nullable|numeric|min:0',
            'length_ft' => 'nullable|numeric|min:0',
            'pieces' => 'nullable|integer|min:0',
            'commodity_description' => 'nullable|string|max:500',
            'special_requirements' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'is_hazmat' => 'boolean',
            'is_temperature_controlled' => 'boolean',
            'temperature_min_f' => 'nullable|numeric',
            'temperature_max_f' => 'nullable|numeric',
            'requires_liftgate' => 'boolean',
            'requires_pallet_jack' => 'boolean',
            'is_team_load' => 'boolean',
            'is_expedited' => 'boolean',
            'shipper_name' => 'nullable|string|max:255',
            'shipper_phone' => 'nullable|string|max:20',
            'consignee_name' => 'nullable|string|max:255',
            'consignee_phone' => 'nullable|string|max:20',
            'origin_ready_at' => 'nullable|date',
            'destination_due_at' => 'nullable|date',
        ]);

        $validated['business_client_id'] = $clientId;
        $validated['provider'] = 'internal';
        $validated['status'] = 'available';

        // Enforce pricing calculation by using the LoadBoardService
        $loadBoardService = resolve(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class);
        $loadBoardService->createLoad($validated);

        Toastr::success(translate('Load Board request created successfully'));
        return redirect()->route('business.load-board.index');
    }

    public function loadBoardShow($id)
    {
        $clientId = $this->getClientId();
        $load = UrbanGoodzLoadBoardLoad::where('id', $id)
            ->where('business_client_id', $clientId)
            ->with(['assignedDriver'])
            ->firstOrFail();

        return view('business.load-board.show', compact('load'));
    }

    public function loadBoardCancel($id, \App\Services\UrbanGoodz\UrbanGoodzLoadBoardService $service)
    {
        $clientId = $this->getClientId();
        $load = UrbanGoodzLoadBoardLoad::where('id', $id)
            ->where('business_client_id', $clientId)
            ->firstOrFail();

        if ($load->status === 'delivered' || $load->status === 'cancelled') {
            Toastr::error(translate('This load cannot be cancelled'));
            return redirect()->back();
        }

        if ($load->status === 'available') {
            $load->update(['status' => 'cancelled']);
            Toastr::success(translate('Load cancelled successfully'));
            return redirect()->route('business.load-board.show', $id);
        }

        $result = $service->updateStatus($id, 'cancelled', $load->assigned_driver_id);
        if (!$result) {
            Toastr::error(translate('Failed to cancel load'));
            return redirect()->back();
        }

        Toastr::success(translate('Load cancelled successfully'));
        return redirect()->route('business.load-board.show', $id);
    }

    public function aiAssistant(Request $request)
    {
        $clientId = $this->getClientId();

        $data = [
            'client' => UrbanGoodzBusinessClient::find($clientId),
            'pool_packages' => UrbanGoodzRoutePackage::where('business_client_id', $clientId)
                ->whereNull('dedicated_route_id')
                ->whereIn('status', ['pending', 'queued', 'awaiting_assignment'])
                ->get(),
            'active_routes' => UrbanGoodzDedicatedRoute::where('business_client_id', $clientId)
                ->whereIn('status', ['active', 'in_progress'])
                ->get(),
            'employees_count' => UrbanGoodzBusinessClientUser::where('business_client_id', $clientId)->count(),
            'unpaid_invoices' => UrbanGoodzClientInvoice::where('business_client_id', $clientId)
                ->where('status', 'unpaid')
                ->get(),
            'recent_manifests' => UrbanGoodzManifest::where('business_client_id', $clientId)
                ->latest()
                ->take(5)
                ->get(),
        ];

        return view('business.ai.assistant', $data);
    }
}
