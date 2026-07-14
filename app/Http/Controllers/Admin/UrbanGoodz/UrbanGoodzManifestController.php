<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzRoutePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrbanGoodzManifestController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzManifest::with('client');

        if ($request->filled('client_id')) {
            $query->where('business_client_id', $request->client_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $manifests = $query->latest()->paginate(25);
        $clients = UrbanGoodzBusinessClient::where('status', 'approved')->orderBy('company_name')->get();

        return view('admin-views.urban-goodz.manifests.index', compact('manifests', 'clients'));
    }

    public function show($id)
    {
        $manifest = UrbanGoodzManifest::with([
            'client',
            'pickupLocation',
            'creator',
            'approver',
            'packages' => function ($q) {
                $q->with('scannedByUser')->latest();
            },
        ])->findOrFail($id);

        $packagesWithAddress = $manifest->packages->filter(function ($pkg) {
            return !empty($pkg->dropoff_address);
        })->count();

        $packagesMissingAddress = $manifest->packages->filter(function ($pkg) {
            return empty($pkg->dropoff_address);
        })->count();

        $readyForOptimization = $manifest->isReadyForOptimization();

        return view('admin-views.urban-goodz.manifests.show', compact(
            'manifest', 'packagesWithAddress', 'packagesMissingAddress', 'readyForOptimization'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_client_id' => ['required', 'integer', 'exists:urban_goodz_business_clients,id'],
            'manifest_name' => ['required', 'string', 'max:255'],
            'pickup_location_id' => ['nullable', 'integer'],
            'pickup_location_text' => ['nullable', 'string', 'max:500'],
            'service_date' => ['required', 'date'],
            'service_type' => ['nullable', 'string', 'in:standard,express,same_day,next_day,medical,bulk,scheduled'],
            'packages_csv' => ['required', 'string'],
        ]);

        $manifestSessionId = 'MS-' . strtoupper(Str::random(8)) . '-' . time();

        DB::beginTransaction();
        try {
            $manifest = UrbanGoodzManifest::create([
                'business_client_id' => $data['business_client_id'],
                'manifest_name' => $data['manifest_name'],
                'manifest_session_id' => $manifestSessionId,
                'pickup_location_id' => $data['pickup_location_id'] ?? null,
                'pickup_location_text' => $data['pickup_location_text'] ?? null,
                'service_date' => $data['service_date'],
                'service_type' => $data['service_type'] ?? 'standard',
                'status' => 'importing',
                'total_packages' => 0,
                'valid_packages' => 0,
                'invalid_packages' => 0,
                'created_by' => auth('admin')->id(),
            ]);

            $lines = explode("\n", $data['packages_csv']);
            $header = str_getcsv(array_shift($lines));
            $header = array_map('trim', $header);
            $count = 0;
            $valid = 0;
            $invalid = 0;
            $trackingIdsSeen = [];

            foreach ($lines as $lineNum => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $row = str_getcsv($line);
                if (count($row) < count($header)) {
                    $invalid++;
                    continue;
                }

                $packageData = array_combine($header, $row);

                $trackingId = $packageData['tracking_id'] ?? null;
                if (!$trackingId) {
                    $trackingId = UrbanGoodzRoutePackage::nextTrackingId();
                }

                if (in_array($trackingId, $trackingIdsSeen)) {
                    $invalid++;
                    continue;
                }
                $trackingIdsSeen[] = $trackingId;

                $existingTracking = UrbanGoodzRoutePackage::where('tracking_id', $trackingId)->exists();
                if ($existingTracking) {
                    $invalid++;
                    continue;
                }

                $dropoffAddress = $packageData['dropoff_address'] ?? '';
                if (empty($dropoffAddress)) {
                    $invalid++;
                    continue;
                }

                UrbanGoodzRoutePackage::create([
                    'business_client_id' => $data['business_client_id'],
                    'manifest_id' => $manifest->id,
                    'manifest_session_id' => $manifestSessionId,
                    'tracking_id' => $trackingId,
                    'external_reference' => $packageData['external_reference'] ?? null,
                    'dropoff_name' => $packageData['dropoff_name'] ?? null,
                    'dropoff_address' => $dropoffAddress,
                    'dropoff_phone' => $packageData['dropoff_phone'] ?? null,
                    'package_type' => $packageData['package_type'] ?? 'parcel',
                    'weight' => $packageData['weight'] ?? null,
                    'priority' => $packageData['priority'] ?? 'normal',
                    'notes' => $packageData['notes'] ?? null,
                    'status' => 'pending',
                ]);
                $count++;
                $valid++;
            }

            $manifest->update([
                'status' => 'import_complete',
                'total_packages' => $count,
                'valid_packages' => $valid,
                'invalid_packages' => $invalid,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.urban-goodz.manifests.show', $manifest->id)
                ->with('success', "Manifest imported: {$valid} valid, {$invalid} invalid out of " . ($count + $invalid) . " rows");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['import' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
