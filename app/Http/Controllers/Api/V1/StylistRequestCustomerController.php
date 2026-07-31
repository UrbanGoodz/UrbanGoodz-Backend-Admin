<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzStylistBid;
use App\Models\UrbanGoodzStylistRequest;
use App\Models\UrbanGoodzStylistRequestInvite;
use App\Models\UrbanGoodzStylistRequestMessage;
use App\Services\StylistRequests\StylistMeasurementAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StylistRequestCustomerController extends Controller
{
    private function owned(Request $request, UrbanGoodzStylistRequest $stylistRequest): UrbanGoodzStylistRequest
    {
        abort_unless((int) $stylistRequest->user_id === (int) $request->user()->id, 404);

        return $stylistRequest;
    }

    public function index(Request $request)
    {
        return response()->json(
            UrbanGoodzStylistRequest::where('user_id', $request->user()->id)
                ->withCount('bids')
                ->with('images')
                ->latest()
                ->paginate(30)
        );
    }

    public function show(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $this->owned($request, $stylistRequest);

        return response()->json($stylistRequest->load(['images', 'invites', 'grants', 'bids.milestones']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_type' => 'required|in:'.implode(',', UrbanGoodzStylistRequest::TYPES),
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'garment_type' => 'nullable|string|max:64',
            'occasion' => 'nullable|string|max:64',
            'budget_min_minor' => 'nullable|integer|min:0',
            'budget_max_minor' => 'nullable|integer|min:0|gte:budget_min_minor',
            'currency' => 'nullable|string|size:3',
            'deadline_at' => 'nullable|date|after:now',
            'service_preference' => 'nullable|in:in_person,remote,either',
            'city' => 'nullable|string|max:120',
            'region_code' => 'nullable|string|max:16',
            'postal_code' => 'nullable|string|max:24',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'visibility' => 'nullable|in:invited_only,qualified_stylists',
            'fashion_fit_profile_id' => 'nullable|integer',
            'images' => 'nullable|array|max:10',
            'images.*.media_path' => 'required|string|max:2048',
            'images.*.caption' => 'nullable|string|max:255',
        ]);

        $stylistRequest = DB::transaction(function () use ($data, $request) {
            $created = UrbanGoodzStylistRequest::create([
                'user_id' => $request->user()->id,
                'request_type' => $data['request_type'],
                'title' => $data['title'],
                'description' => $data['description'],
                'garment_type' => $data['garment_type'] ?? null,
                'occasion' => $data['occasion'] ?? null,
                'budget_min_minor' => $data['budget_min_minor'] ?? null,
                'budget_max_minor' => $data['budget_max_minor'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'deadline_at' => $data['deadline_at'] ?? null,
                'service_preference' => $data['service_preference'] ?? 'either',
                'city' => $data['city'] ?? null,
                'region_code' => $data['region_code'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'visibility' => $data['visibility'] ?? 'qualified_stylists',
                'fashion_fit_profile_id' => $data['fashion_fit_profile_id'] ?? null,
                'status' => 'draft',
            ]);
            foreach ($data['images'] ?? [] as $index => $image) {
                $created->images()->create([
                    'media_path' => $image['media_path'],
                    'caption' => $image['caption'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return $created;
        });

        return response()->json(['message' => 'Stylist request created.', 'data' => $stylistRequest->load('images')], 201);
    }

    public function publish(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $this->owned($request, $stylistRequest);
        abort_unless($stylistRequest->status === 'draft', 409, 'Only a draft request can be published.');
        if ($stylistRequest->visibility === 'invited_only') {
            abort_unless($stylistRequest->invites()->exists(), 422, 'Invite at least one stylist before publishing.');
        }
        $stylistRequest->update(['status' => 'published', 'published_at' => now()]);

        return response()->json(['message' => 'Stylist request published.', 'data' => $stylistRequest->fresh()]);
    }

    public function invite(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $this->owned($request, $stylistRequest);
        abort_if(in_array($stylistRequest->status, ['awarded', 'completed', 'canceled'], true), 409, 'This request is closed.');
        $data = $request->validate(['provider_ids' => 'required|array|max:25', 'provider_ids.*' => 'integer']);

        $eligible = UrbanGoodzServiceProvider::whereIn('id', $data['provider_ids'])
            ->where('approval_status', 'approved')
            ->where('is_active', true)
            ->pluck('id');
        abort_if($eligible->isEmpty(), 422, 'None of the selected stylists are available.');

        foreach ($eligible as $providerId) {
            UrbanGoodzStylistRequestInvite::firstOrCreate(
                ['stylist_request_id' => $stylistRequest->id, 'provider_id' => $providerId],
                ['status' => 'invited']
            );
        }

        return response()->json(['message' => 'Stylists invited.', 'data' => $stylistRequest->invites()->get()]);
    }

    /** Bid comparison: every live bid with its milestones, cheapest first. */
    public function bids(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $this->owned($request, $stylistRequest);

        return response()->json([
            'data' => $stylistRequest->bids()
                ->whereIn('status', ['submitted', 'revised'])
                ->with('milestones')
                ->orderBy('amount_minor')
                ->get(),
        ]);
    }

    public function selectBid(Request $request, UrbanGoodzStylistRequest $stylistRequest, UrbanGoodzStylistBid $bid, StylistMeasurementAccessService $access)
    {
        $this->owned($request, $stylistRequest);
        abort_unless((int) $bid->stylist_request_id === (int) $stylistRequest->id, 404);
        abort_unless($stylistRequest->acceptsBids(), 409, 'This request is no longer accepting a selection.');
        abort_unless($bid->isSelectable(), 409, 'This bid can no longer be selected.');

        DB::transaction(function () use ($stylistRequest, $bid) {
            $bid->update(['status' => 'accepted']);
            $stylistRequest->bids()->whereKeyNot($bid->id)->whereIn('status', ['submitted', 'revised'])->update(['status' => 'rejected']);
            $stylistRequest->update([
                'status' => 'awarded',
                'awarded_bid_id' => $bid->id,
                'awarded_provider_id' => $bid->provider_id,
            ]);
        });

        // Selecting a stylist shares approved measurements by default; photos
        // stay private until the Shopper explicitly allows them.
        if ($stylistRequest->fashion_fit_profile_id !== null) {
            $access->grant($stylistRequest->fresh(), (int) $bid->provider_id, true, false);
        }

        return response()->json(['message' => 'Stylist selected.', 'data' => $stylistRequest->fresh(['bids', 'grants'])]);
    }

    public function messages(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $this->owned($request, $stylistRequest);
        $data = $request->validate(['provider_id' => 'required|integer']);

        return response()->json([
            'data' => UrbanGoodzStylistRequestMessage::where('stylist_request_id', $stylistRequest->id)
                ->where('provider_id', $data['provider_id'])
                ->oldest()
                ->get(),
        ]);
    }

    public function sendMessage(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $this->owned($request, $stylistRequest);
        $data = $request->validate([
            'provider_id' => 'required|integer',
            'body' => 'required|string|max:4000',
        ]);
        abort_unless(
            $stylistRequest->bids()->where('provider_id', $data['provider_id'])->exists()
                || $stylistRequest->invites()->where('provider_id', $data['provider_id'])->exists(),
            422,
            'You can only message stylists you invited or who bid on this request.'
        );

        $message = UrbanGoodzStylistRequestMessage::create([
            'stylist_request_id' => $stylistRequest->id,
            'provider_id' => $data['provider_id'],
            'sender_type' => 'customer',
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['message' => 'Message sent.', 'data' => $message], 201);
    }

    public function grantAccess(Request $request, UrbanGoodzStylistRequest $stylistRequest, StylistMeasurementAccessService $access)
    {
        $this->owned($request, $stylistRequest);
        $data = $request->validate([
            'provider_id' => 'required|integer',
            'measurements_allowed' => 'nullable|boolean',
            'photos_allowed' => 'nullable|boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $grant = $access->grant(
            $stylistRequest,
            (int) $data['provider_id'],
            (bool) ($data['measurements_allowed'] ?? true),
            (bool) ($data['photos_allowed'] ?? false),
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null
        );

        return response()->json(['message' => 'Access updated.', 'data' => $grant]);
    }

    public function setPhotoAccess(Request $request, UrbanGoodzStylistRequest $stylistRequest, StylistMeasurementAccessService $access)
    {
        $this->owned($request, $stylistRequest);
        $data = $request->validate(['provider_id' => 'required|integer', 'allowed' => 'required|boolean']);

        return response()->json([
            'message' => 'Photo access updated.',
            'data' => $access->setPhotoAccess($stylistRequest, (int) $data['provider_id'], (bool) $data['allowed']),
        ]);
    }

    public function revokeAccess(Request $request, UrbanGoodzStylistRequest $stylistRequest, StylistMeasurementAccessService $access)
    {
        $this->owned($request, $stylistRequest);
        $data = $request->validate(['provider_id' => 'required|integer']);
        $access->revoke($stylistRequest, (int) $data['provider_id']);

        return response()->json(['message' => 'Access revoked.']);
    }
}
