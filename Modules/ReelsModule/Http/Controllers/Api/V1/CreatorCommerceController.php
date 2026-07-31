<?php

namespace Modules\ReelsModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreatorCommerceAttribution;
use App\Models\CreatorReelReport;
use App\Models\Order;
use App\Models\UrbanGoodzCreatorCampaign;
use App\Models\UrbanGoodzCreatorCampaignAssignment;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\ReelsModule\Entities\Reel;
use Modules\ReelsModule\Entities\ReelComment;
use Modules\ReelsModule\Http\Resources\ReelCommentResource;
use Modules\ReelsModule\Services\ReelApiService;

class CreatorCommerceController extends Controller
{
    public function profiles(Request $request)
    {
        $profiles = UrbanGoodzCreatorProfile::query()
            ->where('status', 'approved')->where('is_approved', true)
            ->when($request->filled('handle'), fn ($q) => $q->where('handle', $request->string('handle')))
            ->withCount(['content', 'earnings'])
            ->paginate(min(max((int) $request->input('limit', 20), 1), 100));

        return response()->json($profiles);
    }

    public function legacyAction(Request $request, ReelApiService $reelApiService)
    {
        $data = $request->validate([
            'reel_id' => 'required|integer',
            'action' => 'required|in:like,view,visit',
            'guest_id' => 'nullable|string|max:100',
        ]);
        $reel = Reel::active()->findOrFail($data['reel_id']);
        $user = $request->user('api');

        if ($data['action'] === 'like') {
            return response()->json($reelApiService->toggleLike($reel, (int) $user->id));
        }

        if ($data['action'] === 'view') {
            $reelApiService->trackView($reel, $user?->id, $user ? null : ($data['guest_id'] ?? null));
        } else {
            $reelApiService->trackVisit($reel, $user?->id, $user ? null : ($data['guest_id'] ?? null));
        }

        return response()->json([
            'message' => 'Reel action recorded.',
            'stats' => [
                'total_views' => (int) $reel->fresh()->total_views,
                'total_likes' => (int) $reel->fresh()->total_likes,
                'total_comments' => (int) $reel->fresh()->total_comments,
                'total_store_visits' => (int) $reel->fresh()->total_store_visits,
            ],
        ]);
    }

    public function legacyConversion(Request $request)
    {
        return $request->filled('attribution_id')
            ? $this->convertOrder($request)
            : $this->beginAttribution($request);
    }

    public function comments(Request $request, Reel $reel)
    {
        abort_unless(Reel::active()->whereKey($reel->id)->exists(), 404);

        $comments = ReelComment::query()
            ->where('reel_id', $reel->id)
            ->whereNull('parent_id')
            ->whereIn('status', ['published', 'deleted'])
            ->with([
                'user:id,f_name,l_name,image',
                'replies' => fn ($query) => $query->published()->with('user:id,f_name,l_name,image'),
            ])
            ->oldest('id')
            ->paginate(min(max((int) $request->input('limit', 20), 1), 100));

        return ReelCommentResource::collection($comments);
    }

    public function storeComment(Request $request, Reel $reel)
    {
        abort_unless(Reel::active()->whereKey($reel->id)->exists(), 404);
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate([
            'body' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer',
        ]);

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = ReelComment::published()
                ->where('reel_id', $reel->id)
                ->findOrFail($data['parent_id']);
            abort_if($parent->parent_id, 422, 'Replies may only be one level deep.');
        }

        $comment = DB::transaction(function () use ($request, $reel, $data, $parent) {
            $comment = ReelComment::create([
                'reel_id' => $reel->id,
                'user_id' => $request->user('api')->id,
                'parent_id' => $parent?->id,
                'body' => trim($data['body']),
                'status' => 'published',
            ]);
            Reel::query()->whereKey($reel->id)->increment('total_comments');

            return $comment;
        });

        return (new ReelCommentResource($comment->load('user:id,f_name,l_name,image')))
            ->response()
            ->setStatusCode(201);
    }

    public function deleteComment(Request $request, ReelComment $comment)
    {
        abort_unless((int) $comment->user_id === (int) $request->user('api')->id, 403);

        DB::transaction(function () use ($comment) {
            $locked = ReelComment::query()->lockForUpdate()->findOrFail($comment->id);
            if ($locked->status !== 'published') {
                return;
            }

            $locked->update(['status' => 'deleted', 'body' => '']);
            Reel::query()->whereKey($locked->reel_id)
                ->where('total_comments', '>', 0)
                ->decrement('total_comments');
        });

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function opportunities(Request $request)
    {
        $profile = $this->approvedCustomerProfile($request);

        $campaigns = UrbanGoodzCreatorCampaign::query()
            ->with('vendor:id,f_name,l_name')
            ->whereIn('status', ['active', 'open', 'published'])
            ->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->with(['assignments' => fn ($query) => $query->where('creator_profile_id', $profile->id)])
            ->latest('id')
            ->paginate(min(max((int) $request->input('limit', 20), 1), 100));

        return response()->json($campaigns);
    }

    public function acceptOpportunity(Request $request, UrbanGoodzCreatorCampaign $campaign)
    {
        $profile = $this->approvedCustomerProfile($request);
        abort_unless(in_array($campaign->status, ['active', 'open', 'published'], true), 409, 'Campaign is not accepting creators.');
        abort_if($campaign->deadline && $campaign->deadline->isPast(), 410, 'Campaign deadline has passed.');

        $assignment = UrbanGoodzCreatorCampaignAssignment::firstOrCreate(
            ['campaign_id' => $campaign->id, 'creator_profile_id' => $profile->id],
            [
                'creator_application_id' => $profile->creator_application_id,
                'approval_status' => 'pending',
                'creator_notes' => $request->validate(['notes' => 'nullable|string|max:2000'])['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => $assignment->wasRecentlyCreated ? 'Campaign application submitted.' : 'Campaign application already exists.',
            'data' => $assignment->load('campaign'),
        ], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function assignments(Request $request)
    {
        $profile = $this->approvedCustomerProfile($request);

        return response()->json($profile->campaigns()
            ->with('campaign.vendor:id,f_name,l_name')
            ->latest('id')
            ->paginate(min(max((int) $request->input('limit', 20), 1), 100)));
    }

    public function updateAssignment(Request $request, UrbanGoodzCreatorCampaignAssignment $assignment)
    {
        $profile = $this->approvedCustomerProfile($request);
        abort_unless((int) $assignment->creator_profile_id === (int) $profile->id, 404);
        $data = $request->validate([
            'status' => 'required|in:accepted,declined,completed',
            'notes' => 'nullable|string|max:2000',
        ]);
        abort_if($assignment->approval_status === 'completed', 409, 'Completed assignments cannot be changed.');

        $assignment->update([
            'approval_status' => $data['status'],
            'creator_notes' => $data['notes'] ?? $assignment->creator_notes,
            'approved_at' => $data['status'] === 'accepted' ? now() : $assignment->approved_at,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return response()->json(['message' => 'Campaign assignment updated.', 'data' => $assignment->fresh('campaign')]);
    }

    public function analytics(Request $request)
    {
        $profile = $this->approvedCustomerProfile($request);

        return response()->json($this->analyticsForProfile($profile));
    }

    public function vendorAnalytics(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('vendor_id', $request['vendor']->id)->firstOrFail();

        return response()->json($this->analyticsForProfile($profile));
    }

    public function vendorCampaigns(Request $request)
    {
        return response()->json(UrbanGoodzCreatorCampaign::query()
            ->where('vendor_id', $request['vendor']->id)
            ->withCount('assignments')
            ->latest('id')
            ->paginate(min(max((int) $request->input('limit', 20), 1), 100)));
    }

    public function storeVendorCampaign(Request $request)
    {
        $data = $this->validateCampaign($request);
        $campaign = UrbanGoodzCreatorCampaign::create($data + ['vendor_id' => $request['vendor']->id]);

        return response()->json(['message' => 'Creator campaign created.', 'data' => $campaign], 201);
    }

    public function updateVendorCampaign(Request $request, UrbanGoodzCreatorCampaign $campaign)
    {
        abort_unless((int) $campaign->vendor_id === (int) $request['vendor']->id, 404);
        $campaign->update($this->validateCampaign($request));

        return response()->json(['message' => 'Creator campaign updated.', 'data' => $campaign->fresh()]);
    }

    public function vendorCampaignAssignments(Request $request, UrbanGoodzCreatorCampaign $campaign)
    {
        abort_unless((int) $campaign->vendor_id === (int) $request['vendor']->id, 404);

        return response()->json($campaign->assignments()
            ->with('profile:id,handle,display_name,avatar_url')
            ->latest('id')
            ->paginate(min(max((int) $request->input('limit', 20), 1), 100)));
    }

    public function reviewVendorCampaignAssignment(
        Request $request,
        UrbanGoodzCreatorCampaign $campaign,
        UrbanGoodzCreatorCampaignAssignment $assignment
    ) {
        abort_unless((int) $campaign->vendor_id === (int) $request['vendor']->id, 404);
        abort_unless((int) $assignment->campaign_id === (int) $campaign->id, 404);
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,completed',
            'notes' => 'nullable|string|max:2000',
        ]);
        $assignment->update([
            'approval_status' => $data['status'],
            'admin_notes' => $data['notes'] ?? $assignment->admin_notes,
            'approved_at' => $data['status'] === 'approved' ? now() : $assignment->approved_at,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return response()->json(['message' => 'Campaign assignment reviewed.', 'data' => $assignment->fresh('profile')]);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'reel_id' => 'required|integer',
            'reason' => 'required|string|in:spam,unsafe,misleading,copyright,other',
            'details' => 'nullable|string|max:1000',
            'guest_id' => 'nullable|string|max:100',
        ]);
        $reel = Reel::active()->whereKey($data['reel_id'])->firstOrFail();
        $user = $request->user('api');
        abort_unless($user || ! empty($data['guest_id']), 422, 'Authenticated user or guest_id is required.');

        $report = CreatorReelReport::create([
            'reel_id' => $reel->id,
            'user_id' => $user?->id,
            'guest_id' => $user ? null : $data['guest_id'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return response()->json(['message' => 'Report submitted.', 'report_id' => $report->id], 201);
    }

    public function beginAttribution(Request $request)
    {
        $data = $request->validate([
            'reel_id' => 'required|integer',
            'tag_id' => 'required|integer',
        ]);
        $reel = Reel::active()->with('commerceTags')->findOrFail($data['reel_id']);
        $tag = $reel->commerceTags->firstWhere('id', $data['tag_id']);
        abort_unless($tag, 422, 'The selected tag does not belong to this reel.');
        abort_unless($reel->creator_profile_id, 422, 'This reel has no approved creator attribution.');

        $attribution = CreatorCommerceAttribution::create([
            'id' => (string) Str::uuid(),
            'reel_id' => $reel->id,
            'creator_profile_id' => $reel->creator_profile_id,
            'store_id' => $reel->store_id,
            'user_id' => $request->user('api')->id,
            'source_type' => $tag->taggable_type,
            'status' => 'initiated',
        ]);

        return response()->json([
            'attribution_id' => $attribution->id,
            'tag' => $tag,
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ], 201);
    }

    public function convertOrder(Request $request)
    {
        $data = $request->validate([
            'attribution_id' => 'required|uuid',
            'order_id' => 'required|integer',
        ]);
        $userId = (int) $request->user('api')->id;

        $result = DB::transaction(function () use ($data, $userId) {
            $attribution = CreatorCommerceAttribution::whereKey($data['attribution_id'])
                ->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            abort_unless($attribution->status === 'initiated', 409, 'Attribution has already been finalized.');
            abort_if($attribution->created_at->lt(now()->subHours(24)), 410, 'Attribution has expired.');

            $order = Order::withoutGlobalScopes()->whereKey($data['order_id'])
                ->where('user_id', $userId)->where('store_id', $attribution->store_id)->firstOrFail();
            $rate = min(max((float) config('reels.creator_commission_rate', 5), 0), 100);
            $gross = (float) $order->order_amount;
            $commission = round($gross * $rate / 100, 2);

            $attribution->update([
                'source_type' => 'order', 'source_id' => $order->id,
                'gross_amount' => $gross, 'commission_rate' => $rate,
                'commission_amount' => $commission, 'status' => 'converted',
                'converted_at' => now(),
            ]);
            UrbanGoodzCreatorEarning::create([
                'creator_profile_id' => $attribution->creator_profile_id,
                'type' => 'reel_commission', 'amount' => $commission,
                'currency' => 'USD', 'status' => 'pending',
                'source_type' => Order::class, 'source_id' => $order->id,
                'notes' => 'Creator commerce attribution '.$attribution->id,
            ]);
            UserNotification::create([
                'vendor_id' => $attribution->creatorProfile?->vendor_id,
                'title' => 'Creator commerce conversion',
                'description' => 'A reel-attributed order was recorded.',
                'data' => json_encode(['type' => 'creator_attribution', 'attribution_id' => $attribution->id]),
            ]);

            return $attribution->fresh();
        });

        return response()->json(['message' => 'Order attribution recorded.', 'data' => $result]);
    }

    public function vendorProfile(Request $request)
    {
        $vendor = $request['vendor'];
        $profile = UrbanGoodzCreatorProfile::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['display_name' => trim($vendor->f_name.' '.$vendor->l_name), 'status' => 'pending']
        );

        return response()->json($profile);
    }

    public function updateVendorProfile(Request $request)
    {
        $data = $request->validate([
            'handle' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.]+$/i', Rule::unique('urban_goodz_creator_profiles')->ignore($request['vendor']->id, 'vendor_id')],
            'display_name' => 'required|string|max:120', 'bio' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100', 'zone' => 'nullable|string|max:100',
            'niches' => 'nullable|array|max:20', 'niches.*' => 'string|max:60',
            'social_links' => 'nullable|array|max:10',
        ]);
        $profile = UrbanGoodzCreatorProfile::firstOrNew(['vendor_id' => $request['vendor']->id]);
        $profile->fill($data);
        if (! $profile->exists) {
            $profile->status = 'pending';
        }
        $profile->save();

        return response()->json(['message' => 'Creator profile saved.', 'data' => $profile]);
    }

    public function publish(Request $request, Reel $reel)
    {
        $storeId = $request['vendor']->stores[0]->id;
        abort_unless((int) $reel->store_id === (int) $storeId, 404);
        $profile = UrbanGoodzCreatorProfile::where('vendor_id', $request['vendor']->id)->firstOrFail();
        abort_unless($profile->is_approved && $profile->status === 'approved', 403, 'Creator approval is required.');
        abort_unless($reel->video && $reel->thumbnail, 422, 'Video and thumbnail are required.');
        abort_unless($reel->commerceTags()->exists(), 422, 'At least one authorized product or service tag is required.');

        $reel->update([
            'creator_profile_id' => $profile->id,
            'publication_status' => 'pending_review',
            'moderation_status' => 'pending',
            'status' => false,
        ]);

        return response()->json(['message' => 'Reel submitted for moderation.', 'data' => $reel]);
    }

    public function unpublish(Request $request, Reel $reel)
    {
        abort_unless((int) $reel->store_id === (int) $request['vendor']->stores[0]->id, 404);
        $reel->update(['publication_status' => 'draft', 'status' => false, 'published_at' => null]);

        return response()->json(['message' => 'Reel unpublished.']);
    }

    public function earnings(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('vendor_id', $request['vendor']->id)->firstOrFail();

        return response()->json([
            'summary' => $profile->earnings()->selectRaw('status, SUM(amount) amount')->groupBy('status')->get(),
            'records' => $profile->earnings()->latest()
                ->paginate(min(max((int) $request->input('limit', 20), 1), 100)),
        ]);
    }

    private function approvedCustomerProfile(Request $request): UrbanGoodzCreatorProfile
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        $profile = UrbanGoodzCreatorProfile::query()
            ->where('is_approved', true)
            ->where('status', 'approved')
            ->whereHas('application', function ($query) use ($user) {
                $query->where(function ($identity) use ($user) {
                    if ($user->email) {
                        $identity->orWhere('email', $user->email);
                    }
                    if ($user->phone) {
                        $identity->orWhere('phone', $user->phone);
                    }
                });
            })
            ->first();

        abort_unless($profile, 403, 'An approved creator profile is required.');

        return $profile;
    }

    private function analyticsForProfile(UrbanGoodzCreatorProfile $profile): array
    {
        $reels = Reel::withoutGlobalScopes()
            ->where('creator_profile_id', $profile->id);
        $reelIds = (clone $reels)->pluck('id');
        $attributions = CreatorCommerceAttribution::query()
            ->where('creator_profile_id', $profile->id);
        $visits = (int) (clone $reels)->sum('total_store_visits');
        $conversions = (int) (clone $attributions)->where('status', 'converted')->count();

        return [
            'creator_profile_id' => $profile->id,
            'reels' => [
                'total' => (int) (clone $reels)->count(),
                'published' => (int) (clone $reels)->where('publication_status', 'published')->count(),
                'views' => (int) (clone $reels)->sum('total_views'),
                'likes' => (int) (clone $reels)->sum('total_likes'),
                'comments' => (int) (clone $reels)->sum('total_comments'),
                'store_visits' => $visits,
            ],
            'commerce' => [
                'attributions' => (int) (clone $attributions)->count(),
                'conversions' => $conversions,
                'gross_amount' => (string) (clone $attributions)->where('status', 'converted')->sum('gross_amount'),
                'commission_amount' => (string) (clone $attributions)->where('status', 'converted')->sum('commission_amount'),
                'conversion_rate' => $visits > 0 ? round(($conversions / $visits) * 100, 2) : 0.0,
            ],
            'campaigns' => [
                'total' => (int) $profile->campaigns()->count(),
                'active' => (int) $profile->campaigns()->whereIn('approval_status', ['approved', 'accepted'])->count(),
                'completed' => (int) $profile->campaigns()->where('approval_status', 'completed')->count(),
            ],
            'earnings' => $profile->earnings()
                ->selectRaw('status, COUNT(*) records, COALESCE(SUM(amount), 0) amount')
                ->groupBy('status')
                ->get(),
            'reel_ids' => $reelIds,
        ];
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:80',
            'category' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'zone' => 'nullable|string|max:100',
            'pay_type' => 'required|in:flat,commission,hybrid',
            'flat_payout' => 'nullable|numeric|min:0|max:9999999999.99',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'deadline' => 'nullable|date',
            'deliverables' => 'required|string|max:5000',
            'brief' => 'nullable|string|max:10000',
            'status' => 'required|in:draft,open,active,paused,completed,cancelled',
        ]);
    }
}
