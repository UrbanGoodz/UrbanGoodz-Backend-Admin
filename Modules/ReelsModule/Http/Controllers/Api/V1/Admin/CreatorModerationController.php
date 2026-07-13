<?php

namespace Modules\ReelsModule\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreatorReelReport;
use App\Models\UrbanGoodzCreatorProfile;
use Illuminate\Http\Request;
use Modules\ReelsModule\Entities\Reel;
use App\Models\UserNotification;

class CreatorModerationController extends Controller
{
    public function creators(Request $request)
    {
        return response()->json(UrbanGoodzCreatorProfile::latest()->paginate(min((int) $request->input('limit', 20), 100)));
    }

    public function creatorStatus(Request $request, UrbanGoodzCreatorProfile $profile)
    {
        $data = $request->validate(['status' => 'required|in:approved,pending,suspended,rejected', 'notes' => 'nullable|string|max:2000']);
        $approved = $data['status'] === 'approved';
        $profile->update([
            'status' => $data['status'], 'is_approved' => $approved,
            'approved_at' => $approved ? now() : null, 'admin_notes' => $data['notes'] ?? null,
        ]);
        UserNotification::create([
            'vendor_id' => $profile->vendor_id,
            'title' => 'Creator profile status updated',
            'description' => 'Your creator profile is now '.$data['status'].'.',
            'data' => json_encode(['type' => 'creator_profile_status', 'status' => $data['status']]),
        ]);
        if (!$approved) {
            Reel::where('creator_profile_id', $profile->id)->update(['status' => false]);
        }
        return response()->json(['message' => 'Creator status updated.', 'data' => $profile->fresh()]);
    }

    public function reels(Request $request)
    {
        return response()->json(Reel::withoutGlobalScopes()->with(['creatorProfile', 'commerceTags', 'store'])
            ->when($request->filled('moderation_status'), fn ($q) => $q->where('moderation_status', $request->string('moderation_status')))
            ->latest()->paginate(min((int) $request->input('limit', 20), 100)));
    }

    public function moderate(Request $request, Reel $reel)
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject,remove', 'notes' => 'nullable|string|max:2000']);
        $approved = $data['decision'] === 'approve';
        $reel->update([
            'moderation_status' => $approved ? 'approved' : $data['decision'].'d',
            'publication_status' => $approved ? 'published' : 'rejected',
            'moderation_notes' => $data['notes'] ?? null,
            'status' => $approved,
            'published_at' => $approved ? now() : null,
        ]);
        UserNotification::create([
            'vendor_id' => $reel->creatorProfile?->vendor_id,
            'title' => 'Reel moderation updated',
            'description' => 'Your reel was '.$data['decision'].'d.',
            'data' => json_encode(['type' => 'reel_moderation', 'reel_id' => $reel->id, 'decision' => $data['decision']]),
        ]);
        return response()->json(['message' => 'Moderation decision recorded.', 'data' => $reel->fresh()]);
    }

    public function reports(Request $request)
    {
        return response()->json(CreatorReelReport::with('reel')->latest()->paginate(min((int) $request->input('limit', 20), 100)));
    }

    public function resolveReport(Request $request, CreatorReelReport $report)
    {
        $data = $request->validate(['status' => 'required|in:resolved,dismissed', 'remove_reel' => 'nullable|boolean']);
        $report->update(['status' => $data['status'], 'reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
        if ($request->boolean('remove_reel')) {
            Reel::whereKey($report->reel_id)->update(['status' => false, 'publication_status' => 'removed', 'moderation_status' => 'removed']);
        }
        return response()->json(['message' => 'Report resolved.']);
    }
}
