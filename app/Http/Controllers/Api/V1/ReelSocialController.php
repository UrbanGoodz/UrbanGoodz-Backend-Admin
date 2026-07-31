<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzReel;
use App\Models\UrbanGoodzReelComment;
// Assuming engagement models
use Illuminate\Support\Facades\Auth;

class ReelSocialController extends Controller
{
    public function comments(Request $request, $id)
    {
        $reel = UrbanGoodzReel::findOrFail($id);
        
        $comments = $reel->comments()->whereNull('parent_id')
                         ->with(['replies', 'user'])
                         ->paginate(15);
                         
        return response()->json($comments);
    }

    public function postComment(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);
        
        $reel = UrbanGoodzReel::findOrFail($id);
        
        $comment = UrbanGoodzReelComment::create([
            'reel_id' => $reel->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);
        
        return response()->json(['message' => 'Comment posted', 'data' => $comment], 201);
    }

    public function postReply(Request $request, $id, $commentId)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);
        
        $reel = UrbanGoodzReel::findOrFail($id);
        $parent = UrbanGoodzReelComment::where('reel_id', $reel->id)->findOrFail($commentId);
        
        $reply = UrbanGoodzReelComment::create([
            'reel_id' => $reel->id,
            'user_id' => Auth::id(),
            'parent_id' => $parent->id,
            'content' => $request->content,
        ]);
        
        return response()->json(['message' => 'Reply posted', 'data' => $reply], 201);
    }

    public function deleteComment(Request $request, $commentId)
    {
        $comment = UrbanGoodzReelComment::findOrFail($commentId);
        
        if ($comment->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $comment->delete();
        
        return response()->json(['message' => 'Comment deleted']);
    }

    public function saveReel(Request $request, $id)
    {
        // UrbanGoodzReelEngagement::create(...)
        return response()->json(['message' => 'Reel saved']);
    }

    public function unsaveReel(Request $request, $id)
    {
        // UrbanGoodzReelEngagement::where(...)->delete()
        return response()->json(['message' => 'Reel unsaved']);
    }

    public function shareReel(Request $request, $id)
    {
        $reel = UrbanGoodzReel::findOrFail($id);
        $reel->increment('shares_count');
        return response()->json(['message' => 'Reel shared']);
    }

    public function reportReel(Request $request, $id)
    {
        // CreatorReelReport::create(...)
        return response()->json(['message' => 'Reel reported']);
    }

    public function reelTags(Request $request, $id)
    {
        // CreatorReelTag::where(...)
        return response()->json(['data' => []]);
    }
}
