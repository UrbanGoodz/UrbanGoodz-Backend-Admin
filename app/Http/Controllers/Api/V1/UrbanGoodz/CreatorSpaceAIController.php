<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzCreatorCampaign;
use App\Models\UrbanGoodzCreatorProduct;
use App\Models\UrbanGoodzCreatorContent;
use App\Models\UrbanGoodzCreatorEarning;
use App\Services\UrbanGoodz\CreatorSpaceAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorSpaceAIController extends Controller
{
    public function __construct(
        private CreatorSpaceAIService $creatorAI
    ) {}

    // ─── REEL SCRIPT GENERATION ────────────────────────────────────────

    public function generateReelScript(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_description' => ['required', 'string', 'max:2000'],
            'target_audience' => ['required', 'string', 'max:500'],
            'style' => ['nullable', 'string', 'in:engaging,educational,entertaining,inspirational,behind_scenes'],
            'platform' => ['nullable', 'string', 'in:instagram,tiktok,youtube_shorts,facebook_reels'],
            'duration_seconds' => ['nullable', 'integer', 'min:15', 'max:180'],
            'key_messages' => ['nullable', 'array'],
            'key_messages.*' => ['string', 'max:200'],
            'brand_voice' => ['nullable', 'string'],
            'call_to_action' => ['nullable', 'string'],
        ]);

        $result = $this->creatorAI->generateReelScript(
            $data['product_description'],
            $data['target_audience'],
            $data['style'] ?? 'engaging'
        );

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        // Add platform-specific optimizations
        $result['platform'] = $data['platform'] ?? 'instagram';
        $result['duration_seconds'] = $data['duration_seconds'] ?? 60;
        
        if ($data['platform'] === 'tiktok') {
            $result['tiktok_optimizations'] = [
                'trending_sound_suggestion' => 'Check trending sounds in "For You" page',
                'hashtag_strategy' => '3-5 niche + 2-3 trending',
                'posting_time' => 'Peak: 6-10am / 7-11pm local',
            ];
        } elseif ($data['platform'] === 'youtube_shorts') {
            $result['shorts_optimizations'] = [
                'title_strategy' => 'Question or bold statement in first 3 seconds',
                'thumbnail' => 'Custom thumbnail with text overlay',
                'loop_ending' => 'End where you begin for replay value',
            ];
        }

        return response()->json($result);
    }

    // ─── PRODUCT TAGGING ────────────────────────────────────────────────

    public function generateProductTags(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:20'],
            'product_ids.*' => ['integer'],
        ]);

        $products = UrbanGoodzCreatorProduct::with('application')
            ->whereIn('id', array_unique($data['product_ids']))
            ->get();

        if ($products->count() !== count(array_unique($data['product_ids']))) {
            return response()->json(['success' => false, 'error' => 'Product not found.'], 404);
        }

        if ($products->contains(fn (UrbanGoodzCreatorProduct $product) => ! $this->canAccessApplication($request, $product->application))) {
            return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $result = $this->creatorAI->generateProductTags($data['product_ids']);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── CAPTION GENERATION ────────────────────────────────────────────

    public function generateCaption(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reel_content' => ['required', 'string', 'max:2000'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:instagram,tiktok'],
        ]);

        $result = $this->creatorAI->generateCreatorCaption(
            $data['reel_content'],
            $data['tags'],
            $data['platform'] ?? 'instagram'
        );

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── PERFORMANCE ANALYSIS ──────────────────────────────────────────

    public function analyzePerformance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creator_id' => ['required', 'integer'],
        ]);

        $profile = UrbanGoodzCreatorProfile::with('application')->find($data['creator_id']);

        if (!$profile) {
            return response()->json(['success' => false, 'error' => 'Creator profile not found.'], 404);
        }

        if (!$this->canAccessApplication($request, $profile->application)) {
            return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $result = $this->creatorAI->analyzeCreatorPerformance($data['creator_id']);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── BRAND MATCHING ────────────────────────────────────────────────

    public function matchBrand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creator_id' => ['required', 'integer'],
        ]);

        $profile = UrbanGoodzCreatorProfile::with('application')->find($data['creator_id']);

        if (!$profile) {
            return response()->json(['success' => false, 'error' => 'Creator profile not found.'], 404);
        }

        if (!$this->canAccessApplication($request, $profile->application)) {
            return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $result = $this->creatorAI->matchCreatorToBrand($data['creator_id']);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function matchBrands(Request $request): JsonResponse
    {
        return $this->matchBrand($request);
    }

    // ─── REEL ANALYTICS ────────────────────────────────────────────────

    public function generateReelAnalytics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_id' => ['required', 'integer'],
        ]);

        $content = UrbanGoodzCreatorContent::with(['application', 'profile.application'])->find($data['content_id']);

        if (!$content) {
            return response()->json(['success' => false, 'error' => 'Content not found.'], 404);
        }

        $application = $content->profile?->application ?: $content->application;

        if (!$this->canAccessApplication($request, $application)) {
            return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $result = $this->creatorAI->generateReelAnalytics($data['content_id']);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function analyzeReel(Request $request): JsonResponse
    {
        return $this->generateReelAnalytics($request);
    }

    // ─── CAMPAIGN MANAGEMENT ──────────────────────────────────────────

    public function getCampaigns(Request $request): JsonResponse
    {
        $creatorId = $request->input('creator_id') ?? auth('api')->id();

        $campaigns = UrbanGoodzCreatorCampaign::where('status', 'active')
            ->whereHas('assignments', fn($q) => $q->where('creator_id', $creatorId))
            ->with('assignments')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'type' => $c->type,
                'category' => $c->category,
                'city' => $c->city,
                'pay_type' => $c->pay_type,
                'flat_payout' => $c->flat_payout ? '$' . number_format($c->flat_payout, 2) : null,
                'commission_rate' => $c->commission_rate ? $c->commission_rate . '%' : null,
                'deadline' => $c->deadline?->format('M d, Y'),
                'status' => $c->status,
                'assignment_status' => $c->assignments->firstWhere('creator_id', $creatorId)?->approval_status ?? 'none',
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'campaigns' => $campaigns,
        ]);
    }

    public function applyToCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creator_id' => ['required', 'integer'],
            'campaign_id' => ['required', 'integer'],
            'pitch' => ['nullable', 'string', 'max:1000'],
            'proposed_content_type' => ['nullable', 'string'],
        ]);

        $campaign = UrbanGoodzCreatorCampaign::findOrFail($data['campaign_id']);

        $assignment = UrbanGoodzCreatorCampaignAssignment::updateOrCreate(
            [
                'campaign_id' => $data['campaign_id'],
                'creator_id' => $data['creator_id'],
            ],
            [
                'approval_status' => 'pending',
                'pitch' => $data['pitch'] ?? null,
                'proposed_content_type' => $data['proposed_content_type'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Application submitted. Brand will review.',
            'assignment_id' => $assignment->id,
        ]);
    }

    // ─── CONTENT SUBMISSION ────────────────────────────────────────────

    public function submitContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creator_id' => ['required', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content_type' => ['required', 'string', 'in:reel,story,post,video,photo'],
            'video_url' => ['nullable', 'url'],
            'thumbnail_url' => ['nullable', 'url'],
            'tags' => ['nullable', 'array'],
            'is_shoppable' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'cta_label' => ['nullable', 'string'],
            'cta_url' => ['nullable', 'url'],
        ]);

        $content = UrbanGoodzCreatorContent::create([
            'creator_id' => $data['creator_id'],
            'campaign_id' => $data['campaign_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'content_type' => $data['content_type'],
            'video_url' => $data['video_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'tags' => $data['tags'] ?? [],
            'is_shoppable' => $data['is_shoppable'] ?? false,
            'product_ids' => $data['product_ids'] ?? [],
            'cta_label' => $data['cta_label'] ?? 'Shop Now',
            'cta_url' => $data['cta_url'] ?? null,
            'status' => 'draft',
            'moderation_status' => 'pending',
            'published_at' => null,
        ]);

        // If shoppable, create earning record placeholder
        if ($data['is_shoppable'] && !empty($data['product_ids'])) {
            foreach ($data['product_ids'] as $pid) {
                UrbanGoodzCreatorProduct::find($pid);
                // Earning will be created on actual purchase via attribution
            }
        }

        return response()->json([
            'success' => true,
            'content' => $content,
            'message' => 'Content submitted for moderation.',
        ]);
    }

    // ─── EARNINGS ──────────────────────────────────────────────────────

    public function getEarnings(Request $request): JsonResponse
    {
        $creatorId = $request->input('creator_id') ?? auth('api')->id();

        $earnings = UrbanGoodzCreatorEarning::where('creator_id', $creatorId)
            ->with('content', 'campaign')
            ->orderByDesc('created_at')
            ->paginate(20);

        $summary = [
            'total_earned' => UrbanGoodzCreatorEarning::where('creator_id', $creatorId)
                ->where('status', 'paid')
                ->sum('amount'),
            'pending' => UrbanGoodzCreatorEarning::where('creator_id', $creatorId)
                ->where('status', 'pending')
                ->sum('amount'),
            'this_month' => UrbanGoodzCreatorEarning::where('creator_id', $creatorId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'by_type' => UrbanGoodzCreatorEarning::where('creator_id', $creatorId)
                ->where('status', 'paid')
                ->groupBy('type')
                ->selectRaw('type, SUM(amount) as total')
                ->pluck('total', 'type')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'earnings' => $earnings,
            'summary' => $summary,
        ]);
    }

    private function canAccessApplication(Request $request, ?UrbanGoodzCreatorApplication $application): bool
    {
        $user = $request->user();

        if (!$user || !$application) {
            return false;
        }

        $email = strtolower(trim((string) $user->email));
        $phone = preg_replace('/\D+/', '', (string) $user->phone);
        $applicationEmail = strtolower(trim((string) $application->email));
        $applicationPhone = preg_replace('/\D+/', '', (string) $application->phone);

        return ($email !== '' && $applicationEmail !== '' && $email === $applicationEmail)
            || ($phone !== '' && $applicationPhone !== '' && $phone === $applicationPhone);
    }
}
