<?php

namespace Modules\ReelsModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreatorCommerceAttribution;
use App\Models\CreatorReelReport;
use App\Models\Item;
use App\Models\Order;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\ReelsModule\Entities\Reel;

class CreatorCommerceController extends Controller
{
    public function profiles(Request $request)
    {
        $profiles = UrbanGoodzCreatorProfile::query()
            ->where('status', 'approved')->where('is_approved', true)
            ->when($request->filled('handle'), fn ($q) => $q->where('handle', $request->string('handle')))
            ->withCount(['content', 'earnings'])
            ->paginate(min((int) $request->input('limit', 20), 100));

        return response()->json($profiles);
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
        abort_unless($user || !empty($data['guest_id']), 422, 'Authenticated user or guest_id is required.');

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
        if (!$profile->exists) {
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
            'records' => $profile->earnings()->latest()->paginate(min((int) $request->input('limit', 20), 100)),
        ]);
    }
}
