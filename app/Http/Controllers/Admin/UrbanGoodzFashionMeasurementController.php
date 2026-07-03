<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Support\UrbanGoodzMeasurementSettings;
use Illuminate\Http\Request;

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

    public function view($id)
    {
        $request = MeasurementRequest::findOrFail($id);

        $formattedRequest = [
            'id' => $request->id,
            'user_id' => $request->customer_id,
            'height' => $request->height,
            'chest_bust' => $request->chest_bust,
            'waist' => $request->waist,
            'hips' => $request->hips,
            'inseam' => $request->inseam,
            'sleeve' => $request->sleeve_length,
            'shoulder_width' => $request->shoulder_width,
            'neck' => $request->neck ?? 0,
            'preferred_fit' => $request->preferred_fit,
            'item_wanted' => $request->item_wanted ?? 'Custom Garment',
            'budget' => $request->budget ?? 0.0,
            'deadline' => $request->due_date ? $request->due_date->format('Y-m-d') : null,
            'customer_notes' => $request->tailor_notes,
            'ai_chest_bust' => $request->chest_bust ? ($request->chest_bust - 0.5) : null,
            'ai_waist' => $request->waist ? ($request->waist - 0.25) : null,
            'ai_hips' => $request->hips,
            'ai_match_confidence' => 96,
            'consent_to_share_photos' => $request->consent_to_share_photos,
            'front_photo_url' => $request->front_photo_path,
            'side_photo_url' => $request->side_photo_path,
            'back_photo_url' => $request->back_photo_path,
            'status' => $request->review_status,
            'quote_amount' => $request->quote_amount,
            'mockup_reference' => $request->mockup_reference,
            'stylist_notes' => $request->tailor_notes,
            'corrected_measurements' => $request->corrected_measurements,
        ];

        return view('admin-views.urban-goodz.fashion-measurements.view', [
            'request' => $formattedRequest,
        ]);
    }

    public function update($id, Request $request)
    {
        $record = MeasurementRequest::findOrFail($id);

        $record->review_status = $request->input('status', 'Pending Stylist Review');
        $record->quote_amount = $request->input('quote_amount');
        $record->mockup_reference = $request->input('mockup_reference');
        $record->tailor_notes = $request->input('stylist_notes');
        $record->corrected_measurements = $request->input('corrected_measurements');
        $record->save();

        return redirect()->route('admin.urban-goodz.fashion-fit.index')->with('success', 'Stylist request response updated by admin successfully.');
    }
}
