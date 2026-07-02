<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Support\UrbanGoodzMeasurementSettings;

class UrbanGoodzFashionMeasurementController extends Controller
{
    public function index()
    {
        $requests = MeasurementRequest::latest()->paginate(25);

        return view('admin-views.urban-goodz.fashion-measurements.index', [
            'requests' => $requests,
            'settings' => UrbanGoodzMeasurementSettings::all(),
            'totalRequests' => MeasurementRequest::count(),
            'pendingReview' => MeasurementRequest::where('review_status', 'pending')->count(),
            'readyForTailorReview' => MeasurementRequest::where('measurement_status', 'ready_for_tailor_review')->count(),
        ]);
    }
}
