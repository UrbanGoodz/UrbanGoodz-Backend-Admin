<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzCreatorFollow;
use App\Models\UrbanGoodzCreatorBlock;
use App\Models\UrbanGoodzCreatorReport; // Assuming this model exists
use Illuminate\Support\Facades\Auth;

class CreatorDiscoveryController extends Controller
{
    public function index(Request $request)
    {
        $blockedIds = [];
        if (Auth::check()) {
            $blockedIds = UrbanGoodzCreatorBlock::where('blocker_user_id', Auth::id())
                ->pluck('blocked_user_id')->toArray();
        }

        $query = UrbanGoodzCreatorProfile::approved()->visible();

        if (!empty($blockedIds)) {
            $query->whereNotIn('user_id', $blockedIds);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('handle', 'like', '%' . $request->search . '%')
                  ->orWhere('display_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('city')) {
            $query->city($request->city);
        }

        if ($request->has('category')) {
            $query->category($request->category);
        }

        $sort = $request->get('sort', 'popular');
        if ($sort == 'popular') {
            $query->orderBy('follower_count', 'desc');
        } elseif ($sort == 'newest') {
            $query->orderBy('created_at', 'desc');
        } elseif ($sort == 'name') {
            $query->orderBy('display_name', 'asc');
        }

        return response()->json($query->paginate(15));
    }

    public function show(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->approved()->visible()->firstOrFail();
        return response()->json($profile);
    }

    public function creatorReels(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->approved()->visible()->firstOrFail();
        $reels = $profile->reels()->where('publication_status', 'published')->paginate(15);
        return response()->json($reels);
    }

    public function creatorStorefront(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->approved()->visible()->firstOrFail();
        // Assuming related models
        $reels = $profile->reels()->where('publication_status', 'published')->get();
        return response()->json([
            'profile' => $profile,
            'reels' => $reels,
            'products' => [], // Add product logic
        ]);
    }

    public function follow(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->firstOrFail();
        
        $follow = UrbanGoodzCreatorFollow::firstOrCreate([
            'user_id' => Auth::id(),
            'creator_profile_id' => $profile->id,
        ]);
        
        if ($follow->wasRecentlyCreated) {
            $profile->incrementFollowerCount();
        }
        
        return response()->json(['message' => 'Followed']);
    }

    public function unfollow(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->firstOrFail();
        
        $follow = UrbanGoodzCreatorFollow::where([
            'user_id' => Auth::id(),
            'creator_profile_id' => $profile->id,
        ])->first();
        
        if ($follow) {
            $follow->delete();
            $profile->decrementFollowerCount();
        }
        
        return response()->json(['message' => 'Unfollowed']);
    }

    public function reportCreator(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->firstOrFail();
        
        // UrbanGoodzCreatorReport::create([
        //     'reporter_id' => Auth::id(),
        //     'creator_profile_id' => $profile->id,
        //     'reason' => $request->reason,
        // ]);
        
        return response()->json(['message' => 'Reported']);
    }

    public function blockCreator(Request $request, $handle)
    {
        $profile = UrbanGoodzCreatorProfile::where('handle', $handle)->firstOrFail();
        
        UrbanGoodzCreatorBlock::firstOrCreate([
            'blocker_user_id' => Auth::id(),
            'blocked_user_id' => $profile->user_id,
        ]);
        
        return response()->json(['message' => 'Blocked']);
    }
}
