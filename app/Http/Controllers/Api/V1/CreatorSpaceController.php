<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzReel;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorCampaignAssignment;
use Illuminate\Support\Facades\Auth;

class CreatorSpaceController extends Controller
{
    public function register(Request $request)
    {
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
        
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov',
            'thumbnail' => 'required|file|image',
            'caption' => 'nullable|string',
        ]);

        $videoPath = $request->file('video')->store('reels/videos', 'public');
        $thumbnailPath = $request->file('thumbnail')->store('reels/thumbnails', 'public');

        $reel = UrbanGoodzReel::create([
            'creator_profile_id' => $profile->id,
            'video_url' => $videoPath,
            'thumbnail_url' => $thumbnailPath,
            'caption' => $request->caption,
            'publication_status' => 'draft',
            'moderation_status' => 'pending',
        ]);

        return response()->json(['message' => 'Reel uploaded', 'data' => $reel], 201);
    }

    public function updateReel(Request $request, $id)
    {
        $profile = UrbanGoodzCreatorProfile::where('user_id', Auth::id())->firstOrFail();
        $reel = $profile->reels()->findOrFail($id);
        
        $validated = $request->validate([
            'caption' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        
        $reel->update($validated);
        
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
        
        $validated = $request->validate([
            'taggable_type' => 'required|string',
            'taggable_id' => 'required|integer',
        ]);
        
        // Assuming CreatorReelTag model exists
        $tag = \App\Models\CreatorReelTag::create([
            'reel_id' => $reel->id,
            'taggable_type' => $validated['taggable_type'],
            'taggable_id' => $validated['taggable_id'],
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
