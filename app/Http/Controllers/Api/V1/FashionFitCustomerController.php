<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFashionFitAnalysis;
use App\Models\FashionFitAccessGrant;
use App\Models\FashionFitAnalysis;
use App\Models\FashionFitConsent;
use App\Models\FashionFitEstimate;
use App\Models\FashionFitMeasurement;
use App\Models\FashionFitPhoto;
use App\Models\FashionFitProfile;
use App\Models\FashionFitProviderProfile;
use App\Models\FashionFitRequest;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Services\FashionFit\FashionFitAnalysisService;
use App\Services\UrbanGoodz\UrbanGoodzFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FashionFitCustomerController extends Controller
{
    public function requirements()
    {
        return response()->json([
            'consent_version' => config('fashion_fit_ai.consent_version'),
            'required_views' => config('fashion_fit_ai.required_views'),
            'optional_views' => config('fashion_fit_ai.optional_views'),
            'units' => ['in', 'cm'],
            'guidance' => [
                'form_fitting_clothing', 'full_body_visible', 'neutral_pose', 'arms_slightly_away',
                'feet_visible', 'camera_level', 'even_lighting', 'single_person', 'no_screenshot',
            ],
            'disclaimer' => 'AI measurements are estimates and require customer review and approval.',
        ]);
    }

    public function providers(Request $request)
    {
        $providers = FashionFitProviderProfile::query()
            ->where('status', 'approved')
            ->whereHas('vendor', fn ($query) => $query->where('status', 1)->whereHas('stores', fn ($store) => $store->where('status', 1)))
            ->with(['vendor.stores' => fn ($query) => $query->where('status', 1)->select('id', 'vendor_id', 'name', 'logo', 'address', 'module_id')])
            ->paginate(min(50, max(1, $request->integer('limit', 20))));

        return response()->json($providers->through(fn (FashionFitProviderProfile $provider) => [
            'vendor_id' => $provider->vendor_id,
            'name' => trim($provider->vendor->f_name.' '.$provider->vendor->l_name),
            'bio' => $provider->bio,
            'service_categories' => $provider->service_categories,
            'store' => $provider->vendor->stores->first(),
        ]));
    }

    public function index(Request $request)
    {
        return response()->json(FashionFitProfile::where('customer_id', $request->user()->id)
            ->withCount(['photos', 'measurements'])->latest()->paginate(20));
    }

    public function store(Request $request, FashionFitAnalysisService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'units' => ['required', Rule::in(['in', 'cm'])],
            'calibration_height' => ['required', 'numeric', 'gt:0', 'max:300'],
            'fit_preferences' => ['nullable', 'array'],
            'ai_processing_consent' => ['required', 'accepted'],
            'measurement_sharing_consent' => ['nullable', 'boolean'],
            'photo_sharing_consent' => ['nullable', 'boolean'],
        ]);
        $customerId = (int) $request->user()->id;

        $profile = DB::transaction(function () use ($data, $customerId, $request, $service) {
            $profile = FashionFitProfile::create([
                'uuid' => Str::uuid(),
                'customer_id' => $customerId,
                'name' => $data['name'],
                'units' => $data['units'],
                'calibration_height' => $data['calibration_height'],
                'fit_preferences' => $data['fit_preferences'] ?? [],
                'status' => 'photos_pending',
            ]);
            FashionFitConsent::create([
                'profile_id' => $profile->id,
                'customer_id' => $customerId,
                'consent_version' => config('fashion_fit_ai.consent_version'),
                'ai_processing_allowed' => true,
                'measurement_sharing_allowed' => $data['measurement_sharing_consent'] ?? false,
                'photo_sharing_allowed' => $data['photo_sharing_consent'] ?? false,
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'accepted_at' => now(),
            ]);
            $service->audit('customer', $customerId, 'profile_created', FashionFitProfile::class, $profile->id);
            return $profile;
        });

        return response()->json(['data' => $profile], 201);
    }

    public function show(Request $request, string $uuid)
    {
        return response()->json(['data' => $this->ownedProfile($request, $uuid)
            ->load(['measurements', 'analyses' => fn ($query) => $query->latest()])]);
    }

    public function update(Request $request, string $uuid)
    {
        $profile = $this->ownedProfile($request, $uuid);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'fit_preferences' => ['sometimes', 'array'],
            'calibration_height' => ['sometimes', 'numeric', 'gt:0', 'max:300'],
            'units' => ['sometimes', Rule::in(['in', 'cm'])],
        ]);
        $profile->update($data);
        return response()->json(['data' => $profile->fresh()]);
    }

    public function updateConsent(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $uuid);
        $data = $request->validate([
            'ai_processing_allowed' => ['required', 'boolean'],
            'measurement_sharing_allowed' => ['required', 'boolean'],
            'photo_sharing_allowed' => ['required', 'boolean'],
        ]);
        $consent = FashionFitConsent::create(array_merge($data, [
            'profile_id' => $profile->id,
            'customer_id' => $profile->customer_id,
            'consent_version' => config('fashion_fit_ai.consent_version'),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'accepted_at' => now(),
        ]));
        if (! $data['ai_processing_allowed']) {
            $profile->update(['sharing_revoked_at' => now()]);
            FashionFitAccessGrant::where('profile_id', $profile->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }
        $service->audit('customer', $profile->customer_id, 'consent_updated', FashionFitProfile::class, $profile->id, [
            'ai' => $data['ai_processing_allowed'], 'measurements' => $data['measurement_sharing_allowed'], 'photos' => $data['photo_sharing_allowed'],
        ]);
        return response()->json(['data' => $consent]);
    }

    public function uploadPhoto(Request $request, string $uuid, UrbanGoodzFileStorageService $storage, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $uuid);
        abort_unless($this->activeConsent($profile)?->ai_processing_allowed, 403, 'Fashion Fit AI consent is required.');
        $data = $request->validate([
            'view' => ['required', Rule::in(['front', 'side', 'back'])],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('fashion_fit_ai.max_photo_kb')],
            'confirmed_for_upload' => ['required', 'accepted'],
        ]);
        $dimensions = @getimagesize($request->file('photo')->getRealPath());
        abort_unless($dimensions, 422, 'Image dimensions could not be validated.');
        $quality = ['width' => $dimensions[0], 'height' => $dimensions[1]];
        if ($dimensions[0] < config('fashion_fit_ai.min_width') || $dimensions[1] < config('fashion_fit_ai.min_height')) {
            return response()->json([
                'error' => 'photo_quality',
                'retake_required' => true,
                'instructions' => ['Use a higher-resolution full-body portrait photo.'],
            ], 422);
        }

        foreach (FashionFitPhoto::where('profile_id', $profile->id)->where('view', $data['view'])->with('file')->get() as $oldPhoto) {
            Storage::disk($oldPhoto->file->disk)->delete($oldPhoto->file->stored_path);
            $oldPhoto->file->delete();
            $oldPhoto->delete();
        }
        $file = $storage->storeFashionFitPhoto(
            $request->file('photo'), $data['view'], $profile->id, FashionFitProfile::class,
            $profile->customer_id, ['profile_uuid' => $profile->uuid],
        );
        $photo = FashionFitPhoto::create([
            'uuid' => Str::uuid(), 'profile_id' => $profile->id, 'customer_id' => $profile->customer_id,
            'file_id' => $file->id, 'view' => $data['view'], 'status' => 'accepted', 'quality' => $quality,
        ]);
        $profile->update(['status' => 'photos_pending']);
        $service->audit('customer', $profile->customer_id, 'photo_uploaded', FashionFitPhoto::class, $photo->id, ['view' => $photo->view]);
        return response()->json(['data' => ['uuid' => $photo->uuid, 'view' => $photo->view, 'status' => $photo->status, 'quality' => $quality]], 201);
    }

    public function downloadPhoto(Request $request, string $profileUuid, string $photoUuid)
    {
        $profile = $this->ownedProfile($request, $profileUuid);
        $photo = FashionFitPhoto::where('profile_id', $profile->id)->where('uuid', $photoUuid)->with('file')->firstOrFail();
        return Storage::disk($photo->file->disk)->download($photo->file->stored_path, 'fashion-fit-'.$photo->view.'.jpg', [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deletePhoto(Request $request, string $profileUuid, string $photoUuid, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $profileUuid);
        $photo = FashionFitPhoto::where('profile_id', $profile->id)->where('uuid', $photoUuid)->with('file')->firstOrFail();
        Storage::disk($photo->file->disk)->delete($photo->file->stored_path);
        $photo->file->delete();
        $photo->delete();
        $profile->update(['status' => 'photos_pending']);
        $service->audit('customer', $profile->customer_id, 'photo_deleted', FashionFitPhoto::class, $photo->id, ['view' => $photo->view]);
        return response()->json(['message' => 'Photo deleted.']);
    }

    public function submitAnalysis(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $uuid);
        abort_unless($this->activeConsent($profile)?->ai_processing_allowed, 403, 'Fashion Fit AI consent is required.');
        $views = $profile->photos()->where('status', 'accepted')->pluck('view')->all();
        $missing = array_values(array_diff(config('fashion_fit_ai.required_views'), $views));
        if ($missing !== []) {
            return response()->json(['error' => 'required_views_missing', 'missing_views' => $missing], 422);
        }
        $analysis = FashionFitAnalysis::create([
            'uuid' => Str::uuid(), 'profile_id' => $profile->id, 'customer_id' => $profile->customer_id, 'status' => 'uploaded',
        ]);
        $profile->update(['status' => 'analysis_pending']);
        $service->audit('customer', $profile->customer_id, 'analysis_submitted', FashionFitAnalysis::class, $analysis->id);
        ProcessFashionFitAnalysis::dispatch($analysis->id);
        return response()->json(['data' => $analysis], 202);
    }

    public function analysis(Request $request, string $profileUuid, string $analysisUuid)
    {
        $profile = $this->ownedProfile($request, $profileUuid);
        $analysis = FashionFitAnalysis::where('profile_id', $profile->id)->where('uuid', $analysisUuid)->with('measurements')->firstOrFail();
        return response()->json(['data' => $analysis]);
    }

    public function correctMeasurement(Request $request, string $profileUuid, int $measurementId, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $profileUuid);
        abort_unless(in_array($profile->status, ['customer_review', 'approved'], true), 409, 'Profile is not available for correction.');
        $measurement = FashionFitMeasurement::where('profile_id', $profile->id)->findOrFail($measurementId);
        $data = $request->validate(['value' => ['required', 'numeric', 'gt:0'], 'unit' => ['required', Rule::in(['in', 'cm'])]]);
        $measurement->update([
            'original_value' => $measurement->original_value ?? $measurement->value,
            'value' => $data['value'], 'unit' => $data['unit'], 'source' => 'manual_correction',
            'requires_confirmation' => false, 'corrected_at' => now(), 'approved_at' => null,
        ]);
        $profile->update(['status' => 'customer_review', 'approved_at' => null]);
        $service->audit('customer', $profile->customer_id, 'measurement_corrected', FashionFitMeasurement::class, $measurement->id, ['name' => $measurement->name]);
        return response()->json(['data' => $measurement->fresh()]);
    }

    public function approve(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $uuid);
        abort_unless($profile->status === 'customer_review' && $profile->measurements()->exists(), 409, 'Completed AI measurements are required.');
        abort_if($profile->measurements()->where('requires_confirmation', true)->exists(), 422, 'Measurements requiring confirmation must be corrected or confirmed.');
        $profile->measurements()->update(['approved_at' => now()]);
        $profile->update(['status' => 'approved', 'approved_at' => now()]);
        $service->audit('customer', $profile->customer_id, 'profile_approved', FashionFitProfile::class, $profile->id);
        return response()->json(['data' => $profile->fresh('measurements')]);
    }

    public function requests(Request $request)
    {
        return response()->json(FashionFitRequest::where('customer_id', $request->user()->id)->with('estimates')->latest()->paginate(20));
    }

    public function createRequest(Request $request, FashionFitAnalysisService $service)
    {
        $data = $request->validate([
            'profile_uuid' => ['required', 'uuid'], 'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'service_type' => ['required', 'string', 'max:100'], 'garment_type' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'], 'budget' => ['nullable', 'numeric', 'min:0'],
            'requested_completion_date' => ['nullable', 'date', 'after:today'],
            'share_measurements' => ['required', 'accepted'], 'share_photos' => ['required', 'boolean'],
        ]);
        $profile = $this->ownedProfile($request, $data['profile_uuid']);
        abort_unless($profile->status === 'approved', 409, 'Customer-approved measurement profile is required.');
        $consent = $this->activeConsent($profile);
        abort_unless($consent?->measurement_sharing_allowed, 403, 'Measurement sharing consent is required.');
        abort_if($data['share_photos'] && ! $consent?->photo_sharing_allowed, 403, 'Separate photo sharing consent is required.');
        abort_unless(FashionFitProviderProfile::where('vendor_id', $data['vendor_id'])->where('status', 'approved')->exists(), 422, 'Fashion Fit provider is not approved.');

        $fashionRequest = DB::transaction(function () use ($data, $profile, $service) {
            $record = FashionFitRequest::create(array_merge($data, [
                'uuid' => Str::uuid(), 'profile_id' => $profile->id, 'customer_id' => $profile->customer_id,
                'share_measurements' => true, 'status' => 'submitted', 'currency' => 'USD',
            ]));
            FashionFitAccessGrant::create([
                'request_id' => $record->id, 'profile_id' => $profile->id, 'customer_id' => $profile->customer_id,
                'vendor_id' => $record->vendor_id, 'measurements_allowed' => true,
                'photos_allowed' => (bool) $record->share_photos, 'granted_at' => now(),
            ]);
            $service->audit('customer', $profile->customer_id, 'provider_request_created', FashionFitRequest::class, $record->id, ['vendor_id' => $record->vendor_id]);
            UserNotification::create([
                'vendor_id' => $record->vendor_id, 'order_type' => 'fashion_fit',
                'data' => json_encode(['type' => 'fashion_fit_request', 'title' => 'New Fashion Fit request', 'request_uuid' => $record->uuid]),
            ]);
            return $record;
        });
        return response()->json(['data' => $fashionRequest], 201);
    }

    public function requestDetails(Request $request, string $uuid)
    {
        $record = FashionFitRequest::where('customer_id', $request->user()->id)->where('uuid', $uuid)->with('estimates')->firstOrFail();
        return response()->json(['data' => $record]);
    }

    public function decideEstimate(Request $request, string $requestUuid, int $estimateId, FashionFitAnalysisService $service)
    {
        $record = FashionFitRequest::where('customer_id', $request->user()->id)->where('uuid', $requestUuid)->firstOrFail();
        $estimate = FashionFitEstimate::where('request_id', $record->id)->findOrFail($estimateId);
        $decision = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]])['decision'];
        abort_unless(in_array($record->status, ['estimate_submitted', 'submitted'], true), 409, 'Estimate decision is not allowed.');
        if ($decision === 'accept') {
            $estimate->update(['status' => 'accepted']);
            FashionFitEstimate::where('request_id', $record->id)->where('id', '!=', $estimate->id)->update(['status' => 'declined']);
            $record->update(['status' => 'accepted', 'accepted_amount' => $estimate->amount, 'accepted_at' => now(), 'payment_status' => 'pending']);
        } else {
            $estimate->update(['status' => 'declined']);
            $record->update(['status' => 'declined', 'access_revoked_at' => now()]);
            $record->accessGrant?->update(['revoked_at' => now()]);
        }
        $service->audit('customer', $record->customer_id, 'estimate_'.$decision, FashionFitRequest::class, $record->id, ['estimate_id' => $estimate->id]);
        UserNotification::create(['vendor_id' => $record->vendor_id, 'order_type' => 'fashion_fit', 'data' => json_encode(['type' => 'fashion_fit_estimate_'.$decision, 'title' => 'Customer '.$decision.'ed the Fashion Fit estimate.'])]);
        return response()->json(['data' => $record->fresh('estimates')]);
    }

    public function stagedPayment(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $record = FashionFitRequest::where('customer_id', $request->user()->id)->where('uuid', $uuid)->firstOrFail();
        abort_unless($record->status === 'accepted' && $record->payment_status === 'pending', 409, 'Payment is not available.');
        abort_unless(config('app.env') !== 'production' || config('payment_test_mode', true), 403, 'Live payment is not enabled for this flow.');
        $reference = 'ff-test-'.Str::uuid();
        UrbanGoodzPaymentTransaction::create([
            'payable_type' => FashionFitRequest::class, 'payable_id' => $record->id,
            'provider' => 'staged_test', 'environment' => 'test', 'transaction_type' => 'fashion_fit_service',
            'internal_status' => 'captured', 'provider_status' => 'test_captured',
            'amount_minor' => (int) round(((float) $record->accepted_amount) * 100), 'currency' => $record->currency,
            'merchant_reference' => $reference, 'provider_reference' => $reference,
            'idempotency_key' => hash('sha256', 'fashion-fit-'.$record->id), 'processed_at' => now(),
        ]);
        $record->update(['payment_status' => 'test_paid', 'payment_reference' => $reference, 'status' => 'in_progress']);
        $service->audit('customer', $record->customer_id, 'test_payment_recorded', FashionFitRequest::class, $record->id, ['provider' => 'staged_test']);
        UserNotification::create(['vendor_id' => $record->vendor_id, 'order_type' => 'fashion_fit', 'data' => json_encode(['type' => 'fashion_fit_payment', 'title' => 'Fashion Fit test payment recorded.'])]);
        return response()->json(['data' => ['status' => $record->payment_status, 'reference' => $reference]]);
    }

    public function revoke(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $record = FashionFitRequest::where('customer_id', $request->user()->id)->where('uuid', $uuid)->firstOrFail();
        $record->update(['access_revoked_at' => now()]);
        $record->accessGrant?->update(['revoked_at' => now()]);
        $service->audit('customer', $record->customer_id, 'provider_access_revoked', FashionFitRequest::class, $record->id);
        return response()->json(['message' => 'Provider access revoked.']);
    }

    public function destroy(Request $request, string $uuid, FashionFitAnalysisService $service)
    {
        $profile = $this->ownedProfile($request, $uuid);
        abort_if(FashionFitRequest::where('profile_id', $profile->id)->whereIn('status', ['accepted', 'in_progress'])->exists(), 409, 'Active provider work prevents deletion.');
        FashionFitAccessGrant::where('profile_id', $profile->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        foreach ($profile->photos()->with('file')->get() as $photo) {
            Storage::disk($photo->file->disk)->delete($photo->file->stored_path);
            $photo->file->delete();
            $photo->delete();
        }
        $service->audit('customer', $profile->customer_id, 'profile_deleted', FashionFitProfile::class, $profile->id);
        $profile->delete();
        return response()->json(['message' => 'Fashion Fit profile and private photos deleted.']);
    }

    private function ownedProfile(Request $request, string $uuid): FashionFitProfile
    {
        return FashionFitProfile::where('customer_id', $request->user()->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function activeConsent(FashionFitProfile $profile): ?FashionFitConsent
    {
        return $profile->consents()->whereNull('revoked_at')->latest('accepted_at')->first();
    }
}
