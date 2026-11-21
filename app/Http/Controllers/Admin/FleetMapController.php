<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\DriverFleetService;
use Illuminate\Http\Request;

class FleetMapController extends Controller
{
    public function index()
    {
        $mapKey = BusinessSetting::where('key', 'map_api_key')->value('value');

        return view('admin-views.urban-goodz.fleet-map', ['mapKey' => $mapKey]);
    }

    public function data(Request $request, DriverFleetService $fleet)
    {
        // Admins see every driver; ?store_id= narrows to one store's own drivers.
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;

        return response()->json($fleet->fleet($storeId));
    }
}
