<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\FashionFitAccessGrant;
use App\Models\FashionFitEstimate;
use App\Models\FashionFitPhoto;
use App\Models\FashionFitProviderProfile;
use App\Models\FashionFitRequest;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UserNotification;
use App\Services\FashionFit\FashionFitAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FashionFitWorkflowController extends Controller
{
    public function index(Request $request)
    {
        $vendorId = (int) $request['vendor']->id;
        $this->assertEligible($vendorId);
        return response()->json(FashionFitRequest::where('vendor_id', $vendorId)
            ->whereNull('access_revoked_at')->with('estimates')->latest()->paginate(20));
    }

    public function profile(Request $request)
    {
        return response()->json(['data' => FashionFitProviderProfile::firstOrCreate(
            ['vendor_id' => $request['vendor']->id], ['status' => 'pending', 'service_categories' => []]
        )]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'service_categories' => ['required', 'array', 'min:1'],
            'service_categories.*' => ['string', 'max:100'],
            'credentials' => ['nullable', 'array'],
        ]);
        $profile = FashionFitProviderProfile::updateOrCreate(
            ['vendor_id' => $request['vendor']->id], array_merge($data, ['status' => 'pending', 'approved_at' => null, 'approved_by' => null])
        );
        return response()->json(['data' => $profile]);
    }

    public function show(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $record = $this->ownedRequest($request, $uuid);
        $grant = $this->activeGrant($record);
        abort_unless($grant->measurements_allowed, 403, 'Measurement access is not authorized.');
        $profile = $record->profile()->with('measurements')->firstOrFail();
        abort_unless($profile->status === 'approved' && $profile->approved_at, 403, 'Customer-approved measurements are required.');
        $service->audit('vendor', (int) $request['vendor']->id, 'measurements_viewed', FashionFitRequest::class, $record->id);

        return response()->json(['data' => [
            'request' => $record->load('estimates'),
            'profile' => [
                'uuid' => $profile->uuid, 'name' => $profile->name, 'units' => $profile->units,
                'fit_preferences' => $profile->fit_preferences, 'overall_confidence' => $profile->overall_confidence,
                'model_version' => $profile->model_version, 'approved_at' => $profile->approved_at,
                'measurements' => $profile->measurements,
            ],
            'photos_allowed' => $grant->photos_allowed && $record->share_photos,
            'photos' => $grant->photos_allowed && $record->share_photos
                ? $profile->photos()->get(['uuid', 'view', 'status']) : [],
        ]]);
    }

    public function downloadPhoto(Request $request, string $requestUuid, string $photoUuid, FashionFitAnalysisService $service)
    {
        $record = $this->ownedRequest($request, $requestUuid);
        $grant = $this->activeGrant($record);
        abort_unless($grant->photos_allowed && $record->share_photos, 403, 'Raw photo access is not authorized.');
        $consent = $record->profile->consents()->whereNull('revoked_at')->latest('accepted_at')->first();
        abort_unless($consent?->photo_sharing_allowed, 403, 'Customer photo-sharing consent is not active.');
        $photo = FashionFitPhoto::where('profile_id', $record->profile_id)->where('uuid', $photoUuid)->with('file')->firstOrFail();
        $service->audit('vendor', (int) $request['vendor']->id, 'photo_viewed', FashionFitPhoto::class, $photo->id, ['request_id' => $record->id, 'view' => $photo->view]);
        return Storage::disk($photo->file->disk)->download($photo->file->stored_path, 'fashion-fit-'.$photo->view.'.jpg', [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function requestClarification(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $record = $this->ownedRequest($request, $uuid);
        $data = $request->validate([
            'type' => ['required', Rule::in(['clarification', 'manual_measurement', 'photo_retake'])],
            'message' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless(in_array($record->status, ['submitted', 'provider_review', 'estimate_submitted'], true), 409, 'Clarification is not allowed.');
        $record->update(['status' => 'provider_review']);
        $service->audit('vendor', (int) $request['vendor']->id, 'provider_'.$data['type'], FashionFitRequest::class, $record->id, ['message_length' => strlen($data['message'])]);
        $service->notifyCustomer($record->customer_id, 'fashion_fit_'.$data['type'], 'Your Fashion Fit provider requested more information.');
        return response()->json(['message' => 'Customer clarification request recorded.']);
    }

    public function estimate(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $record = $this->ownedRequest($request, $uuid);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timeline_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'requirements' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless(in_array($record->status, ['submitted', 'provider_review', 'estimate_submitted'], true), 409, 'Estimate is not allowed.');
        $revision = ((int) FashionFitEstimate::where('request_id', $record->id)->max('revision')) + 1;
        $estimate = FashionFitEstimate::create(array_merge($data, [
            'request_id' => $record->id, 'vendor_id' => $record->vendor_id,
            'currency' => strtoupper($data['currency'] ?? 'USD'), 'revision' => $revision, 'status' => 'submitted',
        ]));
        $record->update(['status' => 'estimate_submitted']);
        $service->audit('vendor', $record->vendor_id, 'estimate_submitted', FashionFitEstimate::class, $estimate->id, ['revision' => $revision]);
        $service->notifyCustomer($record->customer_id, 'fashion_fit_estimate', 'A Fashion Fit provider estimate is ready.');
        return response()->json(['data' => $estimate], 201);
    }

    public function updateStatus(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $record = $this->ownedRequest($request, $uuid);
        $status = $request->validate(['status' => ['required', Rule::in(['in_progress', 'completed', 'canceled'])]])['status'];
        $transitions = [
            'accepted' => ['in_progress', 'canceled'],
            'in_progress' => ['completed', 'canceled'],
        ];
        abort_unless(in_array($status, $transitions[$record->status] ?? [], true), 409, 'Illegal Fashion Fit status transition.');
        if ($status === 'in_progress') {
            abort_unless(in_array($record->payment_status, ['test_paid', 'paid', 'not_required'], true), 409, 'Required payment has not been recorded.');
        }
        $updates = ['status' => $status];
        if ($status === 'completed') {
            $updates['completed_at'] = now();
            $updates['access_revoked_at'] = now();
            $record->accessGrant?->update(['revoked_at' => now()]);
        }
        if ($status === 'canceled') {
            $updates['access_revoked_at'] = now();
            $record->accessGrant?->update(['revoked_at' => now()]);
        }
        $record->update($updates);
        $service->audit('vendor', $record->vendor_id, 'request_'.$status, FashionFitRequest::class, $record->id);
        $service->notifyCustomer($record->customer_id, 'fashion_fit_status', 'Fashion Fit request status: '.$status.'.');
        return response()->json(['data' => $record->fresh()]);
    }

    public function earnings(Request $request)
    {
        $vendorId = (int) $request['vendor']->id;
        $requestIds = FashionFitRequest::where('vendor_id', $vendorId)->pluck('id');
        $transactions = UrbanGoodzPaymentTransaction::where('payable_type', FashionFitRequest::class)
            ->whereIn('payable_id', $requestIds)->whereIn('internal_status', ['captured', 'settled'])->latest()->paginate(20);
        return response()->json([
            'total_minor' => (int) UrbanGoodzPaymentTransaction::where('payable_type', FashionFitRequest::class)
                ->whereIn('payable_id', $requestIds)->whereIn('internal_status', ['captured', 'settled'])->sum('amount_minor'),
            'transactions' => $transactions,
        ]);
    }

    private function ownedRequest(Request $request, string $uuid): FashionFitRequest
    {
        $this->assertEligible((int) $request['vendor']->id);
        return FashionFitRequest::where('vendor_id', $request['vendor']->id)->where('uuid', $uuid)->with(['profile', 'accessGrant'])->firstOrFail();
    }

    private function assertEligible(int $vendorId): void
    {
        abort_unless(FashionFitProviderProfile::where('vendor_id', $vendorId)->where('status', 'approved')->exists(), 403, 'Fashion Fit provider approval is required.');
    }

    private function activeGrant(FashionFitRequest $record): FashionFitAccessGrant
    {
        $grant = $record->accessGrant;
        abort_unless($grant && $grant->isActive() && $record->access_revoked_at === null, 403, 'Provider access has been revoked.');
        return $grant;
    }
}
