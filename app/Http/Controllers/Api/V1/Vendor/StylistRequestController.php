<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzStylistBid;
use App\Models\UrbanGoodzStylistRequest;
use App\Models\UrbanGoodzStylistRequestMessage;
use App\Services\StylistRequests\StylistMeasurementAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StylistRequestController extends Controller
{
    private function provider(Request $request): UrbanGoodzServiceProvider
    {
        $provider = UrbanGoodzServiceProvider::where('vendor_id', $request['vendor']->id)->firstOrFail();
        abort_unless(
            $provider->approval_status === 'approved' && $provider->is_active,
            403,
            'Approved active provider status is required.'
        );

        return $provider;
    }

    private function visible(Request $request, UrbanGoodzStylistRequest $stylistRequest): UrbanGoodzServiceProvider
    {
        $provider = $this->provider($request);
        // A 404 rather than 403: a stylist should not be able to probe which
        // request ids exist.
        abort_unless($stylistRequest->isVisibleToProvider((int) $provider->id), 404);

        return $provider;
    }

    /** Requests this stylist is eligible to bid on. */
    public function matching(Request $request)
    {
        $provider = $this->provider($request);
        $data = $request->validate([
            'request_type' => 'nullable|in:'.implode(',', UrbanGoodzStylistRequest::TYPES),
            'city' => 'nullable|string|max:120',
        ]);

        $query = UrbanGoodzStylistRequest::whereIn('status', ['published', 'bidding'])
            ->where(function ($q) use ($provider) {
                $q->where('visibility', 'qualified_stylists')
                    ->orWhereHas('invites', fn ($i) => $i->where('provider_id', $provider->id));
            })
            ->when(isset($data['request_type']), fn ($q) => $q->where('request_type', $data['request_type']))
            ->when(isset($data['city']), fn ($q) => $q->where('city', $data['city']))
            ->with('images')
            ->latest();

        return response()->json($query->paginate(30));
    }

    public function show(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $provider = $this->visible($request, $stylistRequest);

        return response()->json([
            'data' => $stylistRequest->load('images'),
            'my_bids' => $stylistRequest->bids()->where('provider_id', $provider->id)->with('milestones')->get(),
        ]);
    }

    public function ask(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $provider = $this->visible($request, $stylistRequest);
        $data = $request->validate(['body' => 'required|string|max:4000']);

        $message = UrbanGoodzStylistRequestMessage::create([
            'stylist_request_id' => $stylistRequest->id,
            'provider_id' => $provider->id,
            'sender_type' => 'stylist',
            'sender_id' => $provider->id,
            'body' => $data['body'],
        ]);

        return response()->json(['message' => 'Question sent.', 'data' => $message], 201);
    }

    public function messages(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $provider = $this->visible($request, $stylistRequest);

        return response()->json([
            'data' => UrbanGoodzStylistRequestMessage::where('stylist_request_id', $stylistRequest->id)
                ->where('provider_id', $provider->id)
                ->oldest()
                ->get(),
        ]);
    }

    public function bid(Request $request, UrbanGoodzStylistRequest $stylistRequest)
    {
        $provider = $this->visible($request, $stylistRequest);
        abort_unless($stylistRequest->acceptsBids(), 409, 'This request is not accepting bids.');
        $data = $this->bidData($request);

        $bid = DB::transaction(function () use ($stylistRequest, $provider, $data) {
            // A new bid supersedes this stylist's previous live bid rather than
            // editing it, so the Shopper keeps the full offer history.
            $previous = $stylistRequest->bids()
                ->where('provider_id', $provider->id)
                ->whereIn('status', ['submitted', 'revised'])
                ->latest()
                ->first();
            if ($previous) {
                $previous->update(['status' => 'superseded']);
            }

            $created = UrbanGoodzStylistBid::create([
                'stylist_request_id' => $stylistRequest->id,
                'provider_id' => $provider->id,
                'amount_minor' => $data['amount_minor'],
                'deposit_minor' => $data['deposit_minor'] ?? 0,
                'currency' => $stylistRequest->currency,
                'message' => $data['message'] ?? null,
                'estimated_days' => $data['estimated_days'] ?? null,
                'fitting_required' => $data['fitting_required'] ?? false,
                'fittings_count' => $data['fittings_count'] ?? 0,
                'expires_at' => $data['expires_at'] ?? null,
                'status' => $previous ? 'revised' : 'submitted',
                'supersedes_bid_id' => $previous?->id,
            ]);

            foreach ($data['milestones'] ?? [] as $index => $milestone) {
                $created->milestones()->create($milestone + ['sort_order' => $index]);
            }

            return $created;
        });

        if ($stylistRequest->status === 'published') {
            $stylistRequest->update(['status' => 'bidding']);
        }
        $stylistRequest->invites()
            ->where('provider_id', $provider->id)
            ->update(['status' => 'bid', 'responded_at' => now()]);

        return response()->json(['message' => 'Bid submitted.', 'data' => $bid->load('milestones')], 201);
    }

    public function withdrawBid(Request $request, UrbanGoodzStylistBid $bid)
    {
        $provider = $this->provider($request);
        abort_unless((int) $bid->provider_id === (int) $provider->id, 404);
        abort_unless($bid->isSelectable(), 409, 'This bid can no longer be withdrawn.');
        $bid->update(['status' => 'withdrawn']);

        return response()->json(['message' => 'Bid withdrawn.']);
    }

    /** Approved measurements, only when the Shopper has granted access. */
    public function measurements(Request $request, UrbanGoodzStylistRequest $stylistRequest, StylistMeasurementAccessService $access)
    {
        $provider = $this->provider($request);

        return response()->json(['data' => $access->measurementsFor($stylistRequest, (int) $provider->id)]);
    }

    /** Body photos, only when the Shopper has separately allowed photos. */
    public function photos(Request $request, UrbanGoodzStylistRequest $stylistRequest, StylistMeasurementAccessService $access)
    {
        $provider = $this->provider($request);

        return response()->json(['data' => $access->photosFor($stylistRequest, (int) $provider->id)]);
    }

    public function updateMilestone(Request $request, \App\Models\UrbanGoodzStylistBidMilestone $milestone)
    {
        $provider = $this->provider($request);
        $bid = $milestone->bid;
        abort_unless($bid && (int) $bid->provider_id === (int) $provider->id, 404);
        abort_unless($bid->status === 'accepted', 409, 'Only milestones on an accepted bid can be updated.');
        $data = $request->validate(['status' => 'required|in:pending,in_progress,completed']);

        $milestone->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return response()->json(['message' => 'Milestone updated.', 'data' => $milestone->fresh()]);
    }

    private function bidData(Request $request): array
    {
        return $request->validate([
            'amount_minor' => 'required|integer|min:1|max:100000000',
            'deposit_minor' => 'nullable|integer|min:0|lte:amount_minor',
            'message' => 'nullable|string|max:4000',
            'estimated_days' => 'nullable|integer|min:1|max:365',
            'fitting_required' => 'nullable|boolean',
            'fittings_count' => 'nullable|integer|min:0|max:20',
            'expires_at' => 'nullable|date|after:now',
            'milestones' => 'nullable|array|max:12',
            'milestones.*.title' => 'required|string|max:255',
            'milestones.*.description' => 'nullable|string|max:1000',
            'milestones.*.amount_minor' => 'nullable|integer|min:0',
            'milestones.*.due_at' => 'nullable|date',
        ]);
    }
}
