<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzBusinessClient;
use Illuminate\Http\Request;

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
}
