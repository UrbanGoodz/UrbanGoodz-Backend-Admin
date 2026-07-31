<?php

namespace App\Services\StylistRequests;

use App\Models\FashionFitAuditEvent;
use App\Models\FashionFitMeasurement;
use App\Models\FashionFitPhoto;
use App\Models\FashionFitProfile;
use App\Models\UrbanGoodzStylistMeasurementGrant;
use App\Models\UrbanGoodzStylistRequest;
use Illuminate\Support\Facades\DB;

/**
 * The single gate through which a stylist can reach a Shopper's Fashion Fit
 * body data.
 *
 * Invariants enforced here:
 *  - Body photos are never shared automatically; `photos_allowed` starts false
 *    and only the Shopper can turn it on.
 *  - Only measurements from an *approved* profile are ever returned.
 *  - The profile must belong to the requesting Shopper and be the one attached
 *    to that stylist request, so a stylist can never reach an unrelated profile.
 *  - Every read and every permission change is audited.
 */
class StylistMeasurementAccessService
{
    public function grant(
        UrbanGoodzStylistRequest $request,
        int $providerId,
        bool $allowMeasurements = true,
        bool $allowPhotos = false,
        ?\DateTimeInterface $expiresAt = null
    ): UrbanGoodzStylistMeasurementGrant {
        abort_if($request->fashion_fit_profile_id === null, 422, 'This request has no Fashion Fit profile attached.');
        $profile = $this->assertOwnedApprovedProfile($request);

        $grant = DB::transaction(function () use ($request, $providerId, $allowMeasurements, $allowPhotos, $expiresAt, $profile) {
            return UrbanGoodzStylistMeasurementGrant::updateOrCreate(
                ['stylist_request_id' => $request->id, 'provider_id' => $providerId],
                [
                    'customer_id' => $request->user_id,
                    'fashion_fit_profile_id' => $profile->id,
                    'measurements_allowed' => $allowMeasurements,
                    'photos_allowed' => $allowPhotos,
                    'granted_at' => now(),
                    'revoked_at' => null,
                    'expires_at' => $expiresAt,
                ]
            );
        });

        $this->audit($request, $providerId, 'stylist_access_granted', [
            'measurements_allowed' => $allowMeasurements,
            'photos_allowed' => $allowPhotos,
            'expires_at' => $expiresAt?->format(DATE_ATOM),
        ]);

        return $grant;
    }

    public function revoke(UrbanGoodzStylistRequest $request, int $providerId): void
    {
        $grant = UrbanGoodzStylistMeasurementGrant::where('stylist_request_id', $request->id)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        $grant->update(['revoked_at' => now(), 'measurements_allowed' => false, 'photos_allowed' => false]);

        $this->audit($request, $providerId, 'stylist_access_revoked', []);
    }

    /** Shopper-controlled photo access, separate from measurement access. */
    public function setPhotoAccess(UrbanGoodzStylistRequest $request, int $providerId, bool $allowed): UrbanGoodzStylistMeasurementGrant
    {
        $grant = UrbanGoodzStylistMeasurementGrant::where('stylist_request_id', $request->id)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        abort_unless($grant->isActive(), 409, 'This access grant has been revoked.');
        $grant->update(['photos_allowed' => $allowed]);

        $this->audit($request, $providerId, $allowed ? 'stylist_photo_access_granted' : 'stylist_photo_access_revoked', []);

        return $grant->fresh();
    }

    /**
     * Approved measurements for a stylist, or a 403 when not permitted.
     * Reading is itself an audited event.
     */
    public function measurementsFor(UrbanGoodzStylistRequest $request, int $providerId): array
    {
        $grant = $this->activeGrant($request, $providerId);
        abort_unless($grant->allowsMeasurements(), 403, 'The Shopper has not shared measurements with you.');

        $profile = $this->assertOwnedApprovedProfile($request);
        abort_unless(
            (int) $grant->fashion_fit_profile_id === (int) $profile->id,
            403,
            'This grant does not cover the profile attached to the request.'
        );

        $measurements = FashionFitMeasurement::where('profile_id', $profile->id)->get();

        $this->audit($request, $providerId, 'stylist_measurements_viewed', [
            'profile_id' => $profile->id,
            'measurement_count' => $measurements->count(),
        ]);

        return [
            'profile' => [
                'id' => $profile->id,
                'units' => $profile->units,
                'approved_at' => $profile->approved_at,
            ],
            'measurements' => $measurements,
        ];
    }

    /**
     * Body photos. Requires a separate, explicit photo permission on top of an
     * active grant — measurement access alone never exposes photos.
     */
    public function photosFor(UrbanGoodzStylistRequest $request, int $providerId): array
    {
        $grant = $this->activeGrant($request, $providerId);
        abort_unless($grant->allowsPhotos(), 403, 'The Shopper has not shared body photos with you.');

        $profile = $this->assertOwnedApprovedProfile($request);
        abort_unless(
            (int) $grant->fashion_fit_profile_id === (int) $profile->id,
            403,
            'This grant does not cover the profile attached to the request.'
        );

        $photos = FashionFitPhoto::where('profile_id', $profile->id)->get();

        $this->audit($request, $providerId, 'stylist_photos_viewed', [
            'profile_id' => $profile->id,
            'photo_count' => $photos->count(),
        ]);

        return ['photos' => $photos];
    }

    private function activeGrant(UrbanGoodzStylistRequest $request, int $providerId): UrbanGoodzStylistMeasurementGrant
    {
        $grant = UrbanGoodzStylistMeasurementGrant::where('stylist_request_id', $request->id)
            ->where('provider_id', $providerId)
            ->first();

        // A missing grant and a revoked grant are both simply "no access".
        abort_unless($grant && $grant->isActive(), 403, 'You do not have access to this Shopper\'s Fashion Fit data.');

        return $grant;
    }

    private function assertOwnedApprovedProfile(UrbanGoodzStylistRequest $request): FashionFitProfile
    {
        $profile = FashionFitProfile::whereKey($request->fashion_fit_profile_id)->firstOrFail();

        // The profile must belong to the Shopper who owns the request; this is
        // what stops a stylist reaching an unrelated customer's profile.
        abort_unless(
            (int) $profile->customer_id === (int) $request->user_id,
            403,
            'The Fashion Fit profile does not belong to this Shopper.'
        );
        abort_unless(
            $profile->approved_at !== null,
            409,
            'Only approved Fashion Fit measurements can be shared.'
        );
        abort_if(
            $profile->sharing_revoked_at !== null,
            403,
            'The Shopper has revoked sharing for this Fashion Fit profile.'
        );

        return $profile;
    }

    private function audit(UrbanGoodzStylistRequest $request, int $providerId, string $event, array $metadata): void
    {
        FashionFitAuditEvent::create([
            'actor_type' => 'stylist_request',
            'actor_id' => $providerId,
            'event' => $event,
            'auditable_type' => UrbanGoodzStylistRequest::class,
            'auditable_id' => $request->id,
            'metadata' => $metadata + [
                'stylist_request_id' => $request->id,
                'provider_id' => $providerId,
                'customer_id' => $request->user_id,
            ],
            'created_at' => now(),
        ]);
    }
}
