<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Models\Vendor;
use App\Models\UrbanGoodzFile;
use App\Support\UrbanGoodzMeasurementSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        $request = MeasurementRequest::with([
            'frontPhotoFile',
            'sidePhotoFile',
            'backPhotoFile',
        ])->findOrFail($id);

        $frontPhotoUrl = null;
        if ($request->frontPhotoFile) {
            $frontPhotoUrl = $this->fileUrl($request->frontPhotoFile);
        } elseif ($request->front_photo_path) {
            $frontPhotoUrl = asset('storage/' . $request->front_photo_path);
        }

        $sidePhotoUrl = null;
        if ($request->sidePhotoFile) {
            $sidePhotoUrl = $this->fileUrl($request->sidePhotoFile);
        } elseif ($request->side_photo_path) {
            $sidePhotoUrl = asset('storage/' . $request->side_photo_path);
        }

        $backPhotoUrl = null;
        if ($request->backPhotoFile) {
            $backPhotoUrl = $this->fileUrl($request->backPhotoFile);
        } elseif ($request->back_photo_path) {
            $backPhotoUrl = asset('storage/' . $request->back_photo_path);
        }

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
            'front_photo_url' => $frontPhotoUrl,
            'side_photo_url' => $sidePhotoUrl,
            'back_photo_url' => $backPhotoUrl,
            'front_photo_file_id' => $request->front_photo_file_id,
            'side_photo_file_id' => $request->side_photo_file_id,
            'back_photo_file_id' => $request->back_photo_file_id,
            'status' => $request->review_status,
            'quote_amount' => $request->quote_amount,
            'mockup_reference' => $request->mockup_reference,
            'stylist_notes' => $request->tailor_notes,
            'corrected_measurements' => $request->corrected_measurements,
            'tailor_id' => $request->tailor_id,
        ];

        $tailors = [];
        if (Schema::hasTable('vendors')) {
            $tailors = Vendor::where('status', 1)->select('id', 'name', 'phone')->get();
        }

        return view('admin-views.urban-goodz.fashion-measurements.view', [
            'request' => $formattedRequest,
            'tailors' => $tailors,
        ]);
    }

    public function update($id, Request $request)
    {
        $record = MeasurementRequest::findOrFail($id);

        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'quote_amount' => ['nullable', 'numeric'],
            'mockup_reference' => ['nullable', 'string'],
            'stylist_notes' => ['nullable', 'string'],
            'corrected_measurements' => ['nullable', 'string'],
            'tailor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        if ($request->has('tailor_id')) {
            $record->tailor_id = $data['tailor_id'];
        }

        $record->review_status = $data['status'] ?? $record->review_status;
        $record->quote_amount = $data['quote_amount'] ?? $record->quote_amount;
        $record->mockup_reference = $data['mockup_reference'] ?? $record->mockup_reference;
        $record->tailor_notes = $data['stylist_notes'] ?? $record->tailor_notes;
        $record->corrected_measurements = $data['corrected_measurements'] ?? $record->corrected_measurements;
        $record->save();

        return redirect()->route('admin.urban-goodz.fashion-fit.index')->with('success', 'Fashion Fit request updated by admin successfully.');
    }

    private function fileUrl(UrbanGoodzFile $file): string
    {
        return asset('storage/' . $file->stored_path);
    }
}
