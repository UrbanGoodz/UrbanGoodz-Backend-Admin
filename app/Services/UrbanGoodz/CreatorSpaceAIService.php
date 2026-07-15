<?php

namespace App\Services\UrbanGoodz;

use App\Models\Vendor;
use App\Models\UrbanGoodzCreatorContent;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorProduct;
use App\Models\UrbanGoodzCreatorProfile;
use Illuminate\Support\Facades\Log;

class CreatorSpaceAIService
{
    private UrbanGoodzAIService $ai;

    public function __construct(UrbanGoodzAIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Generate a reel script with hook, narration, CTA, and hashtag suggestions.
     */
    public function generateReelScript(string $productDescription, string $targetAudience, string $style = 'engaging'): array
    {
        $systemPrompt = <<<'PROMPT'
You are a viral short-form video scriptwriter for Urban Goodz, a local commerce and lifestyle marketplace.
Write a 30-60 second reel/video script optimized for TikTok and Instagram Reels.

Return ONLY valid JSON:
{
  "hook": "First 1-3 seconds — the attention-grabbing opening line or visual cue",
  "narration": "Full narration broken into 2-3 segments with timestamps (e.g. 0:00-0:05, 0:05-0:20, 0:20-0:40)",
  "call_to_action": "Closing CTA directing viewers to shop on Urban Goodz or visit the vendor",
  "hashtags": ["5-8 trending and relevant hashtags"],
  "visual_directions": "Brief notes on camera angles, transitions, or on-screen text for the editor",
  "estimated_duration_seconds": 45,
  "style": "the style used",
  "engagement_hooks": ["2-3 tactics to boost saves/shares (e.g. 'Wait for it...', 'Comment your favorite...')"]
}
PROMPT;

        $userMessage = <<<MSG
Product/service description: {$productDescription}
Target audience: {$targetAudience}
Style preference: {$style}
MSG;

        $raw = $this->ai->chat($systemPrompt, $userMessage);
        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return ['success' => true] + $parsed;
        }

        return $this->errorResponse('generateReelScript', 'Could not generate reel script. Please try again.');
    }

    /**
     * Analyze products and suggest optimal reel tag placement, pricing display, and promotional angles.
     */
    public function generateProductTags(array $productIds): array
    {
        $products = UrbanGoodzCreatorProduct::whereIn('id', $productIds)->get();

        if ($products->isEmpty()) {
            return ['success' => false, 'error' => 'No valid products found for the given IDs.'];
        }

        $productData = $products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => $p->price,
            'currency' => $p->currency,
        ])->toArray();

        $systemPrompt = <<<'PROMPT'
You are a social commerce strategist for Urban Goodz.
Analyze the products listed and suggest optimal strategies for each when featured in a short-form reel video.

For each product, recommend:
- Where to place the product tag in the reel (timing and screen position)
- How to display the price visually (overlay style, discount callout, etc.)
- Promotional angles (urgency, social proof, lifestyle aspiration, bundle deal, etc.)

Return ONLY valid JSON:
{
  "products": [
    {
      "product_id": "id",
      "product_name": "name",
      "tag_placement": {
        "timing": "when to appear in the reel (e.g. '0:12, after the hook')",
        "screen_position": "e.g. top-right, bottom-center",
        "animation": "e.g. pop-in, slide-up, fade"
      },
      "pricing_display": {
        "style": "e.g. bold overlay, price sticker, crossed-out original",
        "highlight": "e.g. 'SALE', '% OFF', 'Free Delivery'"
      },
      "promotional_angles": ["list of 2-3 promotional angles to highlight"],
      "best_for": "Which content style or reel format this product works best in"
    }
  ],
  "general_tips": ["2-3 general tips for tagging products in reels"],
  "monetization_notes": "Notes on maximizing creator earnings from these tagged products"
}
PROMPT;

        $raw = $this->ai->chat($systemPrompt, "Products to analyze:\n" . json_encode($productData, JSON_PRETTY_PRINT));
        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return ['success' => true] + $parsed;
        }

        return $this->errorResponse('generateProductTags', 'Could not generate product tag suggestions.');
    }

    /**
     * Create a platform-optimized caption with hashtags, mentions, and engagement prompts.
     */
    public function generateCreatorCaption(string $reelContent, array $tags, string $platform = 'instagram'): array
    {
        $tagList = implode(', ', $tags);

        $systemPrompt = <<<PROMPT
You are a social media copywriter specializing in short-form commerce content for Urban Goodz.
Write an optimized caption for a creator's reel.

Platform: {$platform}
Engagement priorities by platform:
- Instagram: saveable, shareable, niche hashtags, clean line breaks
- TikTok: conversational, trending sounds/hashtags, comment-bait

Return ONLY valid JSON:
{
  "caption": "The full caption text with line breaks and emojis",
  "hashtags": ["ordered list — mix of broad reach and niche tags, 15-25 total"],
  "mentions": ["relevant vendor/brand handles to tag for reach"],
  "engagement_prompt": "A specific question or CTA to drive comments",
  "first_comment": "A follow-up comment to post immediately for extra hashtags or context",
  "platform_tips": "2-3 platform-specific optimization tips for this caption",
  "best_posting_time": "Recommended time window to post for maximum reach"
}
PROMPT;

        $userMessage = <<<MSG
Reel content summary: {$reelContent}
Product/vendor tags: {$tagList}
Target platform: {$platform}
MSG;

        $raw = $this->ai->chat($systemPrompt, $userMessage);
        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return ['success' => true] + $parsed;
        }

        return $this->errorResponse('generateCreatorCaption', 'Could not generate caption. Please try again.');
    }

    /**
     * Analyze a creator's content + earnings data and suggest improvements.
     */
    public function analyzeCreatorPerformance(int $creatorId): array
    {
        $profile = UrbanGoodzCreatorProfile::with(['content', 'earnings', 'campaigns.campaign'])->find($creatorId);

        if (!$profile) {
            return ['success' => false, 'error' => 'Creator profile not found.'];
        }

        $contentData = $profile->content->map(fn($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'content_type' => $c->content_type,
            'likes' => $c->likes_count,
            'shares' => $c->shares_count,
            'saves' => $c->saves_count,
            'clicks' => $c->clicks_count,
            'is_published' => $c->is_published,
            'is_shoppable' => $c->is_shoppable,
            'status' => $c->status,
            'published_at' => $c->published_at?->toDateTimeString(),
        ])->toArray();

        $earningsData = $profile->earnings->map(fn($e) => [
            'id' => $e->id,
            'type' => $e->type,
            'amount' => $e->amount,
            'status' => $e->status,
            'created_at' => $e->created_at?->toDateTimeString(),
            'paid_at' => $e->paid_at?->toDateTimeString(),
        ])->toArray();

        $campaignData = $profile->campaigns->map(fn($a) => [
            'campaign_title' => $a->campaign?->title,
            'campaign_type' => $a->campaign?->type,
            'approval_status' => $a->approval_status,
        ])->toArray();

        $systemPrompt = <<<'PROMPT'
You are a creator economy performance analyst for Urban Goodz.
Analyze the creator's content performance and earnings data to identify what works, what doesn't, and how to grow.

Provide actionable, specific recommendations. Reference actual numbers from the data.

Return ONLY valid JSON:
{
  "summary": "1-2 sentence overall performance snapshot",
  "top_performing_content": [
    {
      "content_id": "id",
      "title": "title",
      "why_it_worked": "reason based on the metrics",
      "replicate_tip": "how to create similar content"
    }
  ],
  "content_style_analysis": {
    "best_content_type": "which content_type performs best and why",
    "best_engagement_metric": "which metric (likes/shares/saves/clicks) is strongest",
    "improvement_areas": ["specific content types or tactics to try"]
  },
  "earnings_insights": {
    "total_earned": "sum from data",
    "average_per_content": "calculated average",
    "earning_trend": "increasing|stable|declining",
    "monetization_tips": ["2-3 ways to increase earnings"]
  },
  "peak_posting_analysis": {
    "best_days": "recommended posting days based on published_at patterns",
    "best_times": "recommended posting times for their audience",
    "frequency_recommendation": "ideal posting cadence"
  },
  "audience_growth_strategy": ["3-5 specific strategies tailored to this creator's strengths"],
  "action_items": ["Top 3 things the creator should do this week"]
}
PROMPT;

        $context = [
            'creator' => [
                'id' => $profile->id,
                'display_name' => $profile->display_name,
                'handle' => $profile->handle,
                'city' => $profile->city,
                'niches' => $profile->niches,
                'is_featured' => $profile->is_featured,
            ],
            'content' => $contentData,
            'earnings' => $earningsData,
            'campaigns' => $campaignData,
        ];

        $raw = $this->ai->chat($systemPrompt, "Analyze this creator's performance and provide recommendations.", $context);
        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return ['success' => true] + $parsed;
        }

        return $this->errorResponse('analyzeCreatorPerformance', 'Could not generate performance analysis.');
    }

    /**
     * Match a creator's profile to potential brand/vendor partnerships.
     */
    public function matchCreatorToBrand(int $creatorId): array
    {
        $profile = UrbanGoodzCreatorProfile::with(['content', 'earnings', 'campaigns.campaign'])->find($creatorId);

        if (!$profile) {
            return ['success' => false, 'error' => 'Creator profile not found.'];
        }

        $vendors = Vendor::select('id', 'name', 'status')
            ->where('status', 'active')
            ->limit(50)
            ->get();

        $creatorData = [
            'display_name' => $profile->display_name,
            'handle' => $profile->handle,
            'bio' => $profile->bio,
            'city' => $profile->city,
            'zone' => $profile->zone,
            'niches' => $profile->niches,
            'content_types' => $profile->content->pluck('content_type')->unique()->values()->toArray(),
            'total_content' => $profile->content->count(),
            'total_earned' => $profile->earnings->where('status', 'paid')->sum('amount'),
            'engagement_total' => $profile->content->sum('clicks_count'),
            'campaign_count' => $profile->campaigns->count(),
        ];

        $vendorData = $vendors->map(fn($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'status' => $v->status,
        ])->toArray();

        $systemPrompt = <<<'PROMPT'
You are a brand partnership matching specialist for Urban Goodz's creator economy.
Match the creator's profile, niche, and audience to the most compatible vendor/brand partners available on the platform.

Consider:
- Creator's niche(s), city, and content style
- Vendor industry/category compatibility
- Commission potential and mutual value
- Historical campaign participation alignment

Return ONLY valid JSON:
{
  "matches": [
    {
      "vendor_id": "id",
      "vendor_name": "name",
      "match_score": 0.0-1.0,
      "match_reason": "Why this is a good partnership for both sides",
      "campaign_idea": "A specific content campaign concept for this creator + vendor",
      "estimated_revenue_potential": "high|medium|low",
      "outreach_talking_points": ["2-3 points for the initial pitch"]
    }
  ],
  "creator_strengths": ["key strengths that make this creator attractive to brands"],
  "missing_niches": ["niches/categories where the creator has no vendor partnerships yet but could succeed"],
  "portfolio_recommendations": ["content types or skills the creator should develop to attract more brands"],
  "overall_market_position": "How this creator compares to typical Urban Goodz creators"
}
PROMPT;

        $context = ['creator' => $creatorData, 'available_vendors' => $vendorData];

        $raw = $this->ai->chat($systemPrompt, "Match this creator to the best brand partnerships from the vendor list.", $context);
        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return ['success' => true] + $parsed;
        }

        return $this->errorResponse('matchCreatorToBrand', 'Could not generate brand matches.');
    }

    /**
     * Provide AI analysis of a reel's engagement potential and improvement suggestions.
     */
    public function generateReelAnalytics(string $contentId): array
    {
        $content = UrbanGoodzCreatorContent::with(['profile', 'campaign', 'earnings'])->find($contentId);

        if (!$content) {
            return ['success' => false, 'error' => 'Content not found.'];
        }

        $contentData = [
            'id' => $content->id,
            'title' => $content->title,
            'description' => $content->description,
            'content_type' => $content->content_type,
            'likes' => $content->likes_count,
            'shares' => $content->shares_count,
            'saves' => $content->saves_count,
            'clicks' => $content->clicks_count,
            'is_shoppable' => $content->is_shoppable,
            'is_featured' => $content->is_featured,
            'cta_label' => $content->cta_label,
            'vendor_name' => $content->linked_vendor_name,
            'published_at' => $content->published_at?->toDateTimeString(),
            'creator' => [
                'display_name' => $content->profile?->display_name,
                'handle' => $content->profile?->handle,
                'niches' => $content->profile?->niches,
            ],
            'campaign' => [
                'title' => $content->campaign?->title,
                'type' => $content->campaign?->type,
            ],
            'total_earnings' => $content->earnings->sum('amount'),
        ];

        $systemPrompt = <<<'PROMPT'
You are a short-form video analytics expert for Urban Goodz.
Analyze this reel/video content's engagement performance and provide actionable improvement recommendations.

Calculate engagement rates, compare to platform benchmarks, and suggest specific optimizations.

Return ONLY valid JSON:
{
  "engagement_score": "0-100 overall score based on metrics",
  "engagement_rate_breakdown": {
    "like_rate": "likes relative to typical reach benchmarks",
    "share_rate": "shares relative to likes (virality indicator)",
    "save_rate": "saves relative to likes (value indicator)",
    "click_rate": "clicks relative to views (conversion indicator)"
  },
  "performance_assessment": "Overall assessment of how this content is performing",
  "strengths": ["What's working well based on the metrics"],
  "improvements": [
    {
      "area": "specific area to improve",
      "current_state": "what the metrics suggest",
      "recommendation": "specific actionable fix",
      "expected_impact": "high|medium|low"
    }
  ],
  "shoppable_optimization": {
    "current_status": "is_shoppable status and CTA assessment",
    "recommendations": ["specific steps to improve conversion from views to purchases"]
  },
  "content_enhancements": ["Specific content or editing changes to boost engagement"],
  "next_steps": ["Top 3 prioritized actions to improve this reel's performance"]
}
PROMPT;

        $context = ['content' => $contentData];

        $raw = $this->ai->chat($systemPrompt, "Analyze this reel's performance and suggest improvements.", $context);
        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return ['success' => true] + $parsed;
        }

        return $this->errorResponse('generateReelAnalytics', 'Could not generate reel analytics.');
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    private function parseJson(string $raw): ?array
    {
        $cleaned = trim($raw);

        // Strip markdown code fences if present
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        }

        $parsed = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        return null;
    }

    private function errorResponse(string $method, string $message): array
    {
        Log::warning("CreatorSpaceAI: {$method} failed", ['message' => $message]);

        return [
            'success' => false,
            'error' => 'generation_failed',
            'message' => $message,
        ];
    }
}
