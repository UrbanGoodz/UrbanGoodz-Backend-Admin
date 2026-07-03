<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use Illuminate\Http\Request;

class UrbanGoodzFashionMeasurementController extends Controller
{
    public function index()
    {
        $vendorId = Helpers::get_vendor_id();

        // Retrieve requests, filtering by vendor if applicable
        $requests = MeasurementRequest::query()
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->latest()
            ->paginate(25);

        return view('vendor-views.urban-goodz.fashion-measurements.index', [
            'requests' => $requests,
        ]);
    }

    public function view($id)
    {
        $vendorId = Helpers::get_vendor_id();
        $request = MeasurementRequest::query()
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->findOrFail($id);

        // Convert the Eloquent model attributes to an array format expected by the view
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
            'neck' => $request->neck ?? 0, // Fallback if neck is not in migration
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

        return view('vendor-views.urban-goodz.fashion-measurements.view', [
            'request' => $formattedRequest,
        ]);
    }

    public function update($id, Request $request)
    {
        $vendorId = Helpers::get_vendor_id();
        $record = MeasurementRequest::query()
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->findOrFail($id);

        $record->review_status = $request->input('status', 'Pending Stylist Review');
        $record->quote_amount = $request->input('quote_amount');
        $record->mockup_reference = $request->input('mockup_reference');
        $record->tailor_notes = $request->input('stylist_notes');
        $record->corrected_measurements = $request->input('corrected_measurements');
        $record->save();

        return redirect()->route('vendor.stylist-request.list')->with('success', 'Stylist request response updated successfully.');
    }
}
