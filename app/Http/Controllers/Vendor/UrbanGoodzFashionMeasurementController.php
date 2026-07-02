<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;

class UrbanGoodzFashionMeasurementController extends Controller
{
    public function index()
    {
        $vendorId = Helpers::get_vendor_id();

        return view('vendor-views.urban-goodz.fashion-measurements.index', [
            'requests' => MeasurementRequest::forVendor($vendorId)->latest()->paginate(25),
        ]);
    }
}
