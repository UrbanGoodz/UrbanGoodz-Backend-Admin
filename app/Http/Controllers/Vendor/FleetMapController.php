<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\DriverFleetService;

class FleetMapController extends Controller
{
    public function index()
    {
        $mapKey = BusinessSetting::where('key', 'map_api_key')->value('value');

        return view('vendor-views.fleet-map', ['mapKey' => $mapKey]);
    }

    public function data(DriverFleetService $fleet)
    {
        // A vendor sees ONLY their own store's drivers. The store comes from the logged-in vendor, never from the request.
        $storeId = (int) Helpers::get_store_id();
        if ($storeId <= 0) {
            return response()->json(['drivers' => [], 'generated_at' => now()->toIso8601String()]);
        }

        return response()->json($fleet->fleet($storeId));
    }
}
