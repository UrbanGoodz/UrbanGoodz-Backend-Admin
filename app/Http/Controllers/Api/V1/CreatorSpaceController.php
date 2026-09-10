<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzReel;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorCampaignAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CreatorSpaceController extends Controller
{
    public function register(Request $request)
    {
        if (UrbanGoodzCreatorProfile::where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'A creator profile already exists for this account'], 422);
        }

        $validated = $request->validate([
            'handle' => 'required|string|unique:urban_goodz_creator_profiles,handle',
            'display_name' => 'required|string',
            'bio' => 'nullable|string',
            'categories' => 'nullable|array',
            'audience_info' => 'nullable|array',
            'portfolio' => 'nullable|array',
            'city' => 'nullable|string',
            'zone' => 'nullable|string',
            'social_links' => 'nullable|array',
        ]);

        $profile = UrbanGoodzCreatorProfile::create(array_merge($validated, [
            'user_id' => Auth::id(),
            'verification_status' => 'unverified',
        ]));

        return response()->json(['message' => 'Creator profile created', 'data' => $profile], 201);
    }

    public function profile(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->first();
        if (!$profile) return response()->json(['message' => 'Not found'], 404);

        $earnings = $profile->earnings()->sum('amount');
        $campaignCount = $profile->campaigns()->count();
        $reelCount = $profile->reels()->count();

        $profile->earnings_summary = $earnings;
        $profile->campaign_count = $campaignCount;
        $profile->reel_count = $reelCount;

        return response()->json(['data' => $profile]);
    }

    public function updateProfile(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'bio' => 'nullable|string',
            'social_links' => 'nullable|array',
            'categories' => 'nullable|array',
            'audience_info' => 'nullable|array',
            'portfolio' => 'nullable|array',
            'city' => 'nullable|string',
            'zone' => 'nullable|string',
        ]);

        $profile->update($validated);
        return response()->json(['message' => 'Profile updated', 'data' => $profile]);
    }

    public function uploadAvatar(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $request->validate(['avatar' => 'required|file|image']);

        $path = $request->file('avatar')->store('creator-avatars', 'public');
        $profile->update(['avatar_url' => $path]);

        return response()->json(['message' => 'Avatar uploaded', 'url' => $path]);
    }

    public function uploadBanner(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $request->validate(['banner' => 'required|file|image']);

        $path = $request->file('banner')->store('creator-banners', 'public');
        $profile->update(['banner_url' => $path]);

        return response()->json(['message' => 'Banner uploaded', 'url' => $path]);
    }

    public function verificationStatus(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        
        return response()->json([
            'verification_status' => $profile->verification_status,
            'verified_at' => $profile->verified_at,
            'requirements' => [
                'has_avatar' => !empty($profile->avatar_url),
                'has_bio' => !empty($profile->bio),
                'min_followers' => $profile->follower_count >= 100,
            ]
        ]);
    }

    public function submitVerification(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $profile->update(['verification_status' => 'submitted']);
        
        return response()->json(['message' => 'Verification submitted']);
    }

    public function myReels(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $reels = $profile->reels()->paginate(15);
        
        return response()->json($reels);
    }

    public function uploadReel(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();

        // Column names below match the real `reels` table
        // (Modules\ReelsModule\Entities\Reel) - description/thumbnail/video,
        // not the caption/thumbnail_url/video_url columns this previously
        // wrote to a model class that did not exist. Creator Space is
        // social-first: no store_id/module linkage is required to post.
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov',
            'thumbnail' => 'required|file|image',
            'caption' => 'required|string|max:2000',
        ]);

        $videoPath = $request->file('video')->store('reels/videos', 'public');
        $thumbnailPath = $request->file('thumbnail')->store('reels/thumbnails', 'public');

        $reel = UrbanGoodzReel::create([
            'store_id' => null,
            'module_id' => \Modules\ReelsModule\Support\ReelModuleConfig::defaultModuleId(),
            'module_type' => \Modules\ReelsModule\Support\ReelModuleConfig::defaultModuleType(),
            'creator_profile_id' => $profile->id,
            'description' => $request->caption,
            'video' => $videoPath,
            'thumbnail' => $thumbnailPath,
            'is_always_visible' => true,
            'status' => true,
            'publication_status' => 'draft',
            'moderation_status' => 'pending',
            'created_by_id' => Auth::id(),
            'created_by_type' => User::class,
        ]);

        return response()->json(['message' => 'Reel uploaded', 'data' => $reel], 201);
    }

    public function updateReel(Request $request, $id)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $reel = $profile->reels()->findOrFail($id);

        $validated = $request->validate([
            'caption' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
        ]);

        // 'caption' is accepted as an alias of the real `description` column
        // for backward compatibility with callers built against the
        // previously-broken (nonexistent) schema.
        $description = $validated['description'] ?? $validated['caption'] ?? null;
        if ($description !== null) {
            $reel->update(['description' => $description]);
        }

        return response()->json(['message' => 'Reel updated', 'data' => $reel]);
    }

    public function deleteReel(Request $request, $id)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $reel = $profile->reels()->findOrFail($id);

        $reel->delete();

        return response()->json(['message' => 'Reel deleted']);
    }

    public function addReelTags(Request $request, $id)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $reel = $profile->reels()->findOrFail($id);

        // creator_reel_tags.store_id is required (tagging a reel to a
        // commerce entity means naming which store owns that entity) - this
        // is the "optional commerce connection": a reel is never required
        // to have a tag row, but a tag row always names its store.
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'taggable_type' => 'required|string',
            'taggable_id' => 'required|integer',
            'label' => 'nullable|string',
        ]);

        $tag = \App\Models\CreatorReelTag::create([
            'reel_id' => $reel->id,
            'store_id' => $validated['store_id'],
            'taggable_type' => $validated['taggable_type'],
            'taggable_id' => $validated['taggable_id'],
            'label' => $validated['label'] ?? null,
        ]);

        return response()->json(['message' => 'Tag added', 'data' => $tag], 201);
    }

    public function removeReelTag(Request $request, $id, $tagId)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $reel = $profile->reels()->findOrFail($id);
        
        $tag = \App\Models\CreatorReelTag::where('reel_id', $reel->id)->findOrFail($tagId);
        $tag->delete();
        
        return response()->json(['message' => 'Tag removed']);
    }

    public function storefront(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        
        $reels = $profile->reels()->where('publication_status', 'published')->get();
        $campaigns = $profile->campaigns()->where('status', 'active')->get();
        // Assuming products relationship
        $products = [];
        
        return response()->json([
            'profile' => $profile,
            'reels' => $reels,
            'campaigns' => $campaigns,
            'products' => $products,
        ]);
    }

    public function browseCampaigns(Request $request)
    {
        // Assuming UrbanGoodzCreatorCampaign model
        $query = \App\Models\UrbanGoodzCreatorCampaign::where('status', 'open');
        
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        return response()->json($query->paginate(15));
    }

    public function applyCampaign(Request $request, $id)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();

        // Previously this skipped straight to the insert: applying to a
        // nonexistent campaign_id tripped the campaign_id foreign key
        // constraint and surfaced as a raw 500 (with APP_DEBUG on, a full
        // SQL exception + stack trace in the response body) instead of a
        // clean 404.
        \App\Models\UrbanGoodzCreatorCampaign::findOrFail($id);

        $assignment = UrbanGoodzCreatorCampaignAssignment::create([
            'creator_profile_id' => $profile->id,
            'campaign_id' => $id,
            'approval_status' => 'pending',
        ]);

        return response()->json(['message' => 'Applied to campaign', 'data' => $assignment], 201);
    }

    public function myCampaigns(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $campaigns = $profile->campaigns()->with('campaign')->get();
        
        return response()->json($campaigns);
    }

    public function submitDeliverable(Request $request, $id)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $assignment = $profile->campaigns()->findOrFail($id);
        
        $assignment->update([
            'status' => 'submitted',
            'deliverable_content' => $request->content_url,
        ]);
        
        return response()->json(['message' => 'Deliverable submitted', 'data' => $assignment]);
    }

    public function earnings(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        
        $total = $profile->earnings()->sum('amount');
        $pending = $profile->earnings()->where('status', 'pending')->sum('amount');
        $paid = $profile->earnings()->where('status', 'paid')->sum('amount');
        
        $history = $profile->earnings()->paginate(15);
        
        return response()->json([
            'summary' => ['total' => $total, 'pending' => $pending, 'paid' => $paid],
            'history' => $history,
        ]);
    }

    public function analytics(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        
        return response()->json([
            'views' => 1000,
            'follower_growth' => 50,
            'engagement_rate' => 5.2,
        ]);
    }

    public function payoutStatus(Request $request)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        
        return response()->json(['payout_setup_status' => $profile->payout_setup_status]);
    }
}
