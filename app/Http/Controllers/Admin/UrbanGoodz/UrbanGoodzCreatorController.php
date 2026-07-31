<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzCreatorBusinessLead;
use App\Models\UrbanGoodzCreatorCampaign;
use App\Models\UrbanGoodzCreatorCampaignAssignment;
use App\Models\UrbanGoodzCreatorContent;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorEventPromotion;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\Vendor;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\ReelsModule\Entities\Reel;

class UrbanGoodzCreatorController extends Controller
{
    // ====== DASHBOARD ======

    public function dashboard()
    {
        $data = [
            'pending_applications' => UrbanGoodzCreatorApplication::where('status', 'pending')->count(),
            'active_creators' => UrbanGoodzCreatorProfile::where('is_approved', true)->count(),
            'active_campaigns' => UrbanGoodzCreatorCampaign::whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
            'total_revenue' => UrbanGoodzCreatorEarning::where('status', 'paid')->sum('amount'),
            'total_earnings_pending' => UrbanGoodzCreatorEarning::where('status', 'pending')->sum('amount'),
            'total_leads' => UrbanGoodzCreatorBusinessLead::count(),
            'total_content' => UrbanGoodzCreatorContent::where('is_published', true)->count(),
            'total_event_promos' => UrbanGoodzCreatorEventPromotion::where('status', 'live')->count(),
            'recent_applications' => UrbanGoodzCreatorApplication::latest()->take(5)->get(),
            'top_creators' => UrbanGoodzCreatorProfile::where('is_approved', true)
                ->withSum('earnings as total_earned', 'amount')
                ->orderByDesc('total_earned')
                ->take(5)->get(),
        ];

        return view('admin-views.urban-goodz.creator.dashboard', compact('data'));
    }

    // ====== APPLICATIONS ======

    public function applications(Request $request)
    {
        $query = UrbanGoodzCreatorApplication::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('creator_name', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%")
                    ->orWhere('username', 'like', "%$s%");
            });
        }

        $applications = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.creator.applications', compact('applications'));
    }

    public function applicationShow($id)
    {
        $application = UrbanGoodzCreatorApplication::with(['profile', 'products'])->findOrFail($id);

        return view('admin-views.urban-goodz.creator.application-show', compact('application'));
    }

    public function applicationUpdateStatus($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_applications_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $application = UrbanGoodzCreatorApplication::findOrFail($id);
        $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,suspended'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $application->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        $profile = $application->profile;
        if ($request->status === 'approved') {
            $profile ??= new UrbanGoodzCreatorProfile([
                'creator_application_id' => $application->id,
                'display_name' => $application->creator_name,
                'handle' => $this->generateHandle($application->creator_name),
                'bio' => $application->bio,
                'niches' => $application->niche ? [$application->niche] : null,
                'city' => $application->city,
                'social_links' => $application->social_links,
                'content_samples' => $application->content_samples,
            ]);
            $profile->fill([
                'status' => 'approved',
                'is_approved' => true,
                'approved_at' => now(),
            ])->save();
        } elseif ($profile) {
            $profile->update([
                'status' => $request->status,
                'is_approved' => false,
                'approved_at' => null,
            ]);
            Reel::where('creator_profile_id', $profile->id)->update(['status' => false]);
        }

        Toastr::success(translate('Application status updated.'));

        return back();
    }

    // ====== PROFILES ======

    public function profiles(Request $request)
    {
        $query = UrbanGoodzCreatorProfile::with('application');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('display_name', 'like', "%$s%")
                    ->orWhere('handle', 'like', "%$s%")
                    ->orWhere('city', 'like', "%$s%");
            });
        }
        if ($request->filled('is_approved')) {
            $query->where('is_approved', $request->is_approved === '1');
        }
        if ($request->filled('is_featured')) {
            $query->where('is_featured', $request->is_featured === '1');
        }

        $profiles = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.creator.profiles', compact('profiles'));
    }

    public function profileShow($id)
    {
        $profile = UrbanGoodzCreatorProfile::with([
            'application', 'content' => fn ($q) => $q->latest()->take(10),
            'earnings' => fn ($q) => $q->latest()->take(10),
            'campaigns.campaign',
            'leads' => fn ($q) => $q->latest()->take(5),
            'eventPromotions.event',
        ])->findOrFail($id);

        $stats = [
            'total_content' => UrbanGoodzCreatorContent::where('creator_profile_id', $id)->count(),
            'published_content' => UrbanGoodzCreatorContent::where('creator_profile_id', $id)->where('is_published', true)->count(),
            'total_earned' => UrbanGoodzCreatorEarning::where('creator_profile_id', $id)->where('status', 'paid')->sum('amount'),
            'pending_earnings' => UrbanGoodzCreatorEarning::where('creator_profile_id', $id)->where('status', 'pending')->sum('amount'),
            'total_leads' => UrbanGoodzCreatorBusinessLead::where('creator_profile_id', $id)->count(),
            'total_campaigns' => UrbanGoodzCreatorCampaignAssignment::where('creator_profile_id', $id)->count(),
            'total_engagement' => UrbanGoodzCreatorContent::where('creator_profile_id', $id)->sum('clicks_count'),
        ];

        return view('admin-views.urban-goodz.creator.profile-show', compact('profile', 'stats'));
    }

    public function profileUpdate($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_profiles_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $profile = UrbanGoodzCreatorProfile::findOrFail($id);
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:100', 'unique:urban_goodz_creator_profiles,handle,'.$id],
            'bio' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'niches' => ['nullable', 'array'],
            'is_approved' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        if (array_key_exists('is_approved', $data)) {
            $approved = (bool) $data['is_approved'];
            $data['status'] = $approved ? 'approved' : 'suspended';
            $data['approved_at'] = $approved ? now() : null;
        }
        $profile->update($data);

        if (array_key_exists('is_approved', $data) && ! $data['is_approved']) {
            Reel::where('creator_profile_id', $profile->id)->update(['status' => false]);
        }

        Toastr::success(translate('Creator profile updated.'));

        return back();
    }

    // ====== CAMPAIGNS ======

    public function campaigns(Request $request)
    {
        $query = UrbanGoodzCreatorCampaign::withCount('assignments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('title', 'like', "%$s%");
        }

        $campaigns = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.creator.campaigns', compact('campaigns'));
    }

    public function campaignCreate()
    {
        $vendors = Vendor::select('id', 'name')->orderBy('name')->get();

        return view('admin-views.urban-goodz.creator.campaign-create', compact('vendors'));
    }

    public function campaignStore(Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_campaigns_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:product_review,vendor_spotlight,food_review,event_promo,rental_showcase,fashion_styling,service_provider,business_launch,order_anywhere_discovery'],
            'category' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'pay_type' => ['required', 'in:flat,commission,hybrid'],
            'flat_payout' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deadline' => ['nullable', 'date'],
            'deliverables' => ['nullable', 'string'],
            'brief' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,open,assigned,in_progress,completed,cancelled'],
        ]);

        $data['status'] = $data['status'] ?? 'draft';

        UrbanGoodzCreatorCampaign::create($data);

        Toastr::success(translate('Campaign created.'));

        return redirect()->route('admin.urban-goodz.creator.campaigns');
    }

    public function campaignShow($id)
    {
        $campaign = UrbanGoodzCreatorCampaign::with([
            'vendor', 'assignments.profile', 'assignments.application',
            'content', 'earnings',
        ])->withCount('assignments')->findOrFail($id);

        $availableCreators = UrbanGoodzCreatorProfile::where('is_approved', true)->get();

        return view('admin-views.urban-goodz.creator.campaign-show', compact('campaign', 'availableCreators'));
    }

    public function campaignUpdate($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_campaigns_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $campaign = UrbanGoodzCreatorCampaign::findOrFail($id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'pay_type' => ['required', 'in:flat,commission,hybrid'],
            'flat_payout' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deadline' => ['nullable', 'date'],
            'deliverables' => ['nullable', 'string'],
            'brief' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,open,assigned,in_progress,completed,cancelled'],
        ]);

        $campaign->update($data);

        Toastr::success(translate('Campaign updated.'));

        return back();
    }

    public function campaignAssignCreator($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_campaigns_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $campaign = UrbanGoodzCreatorCampaign::findOrFail($id);
        $request->validate([
            'creator_profile_id' => ['required', 'exists:urban_goodz_creator_profiles,id'],
        ]);

        $existing = UrbanGoodzCreatorCampaignAssignment::where('campaign_id', $id)
            ->where('creator_profile_id', $request->creator_profile_id)
            ->first();

        if ($existing) {
            Toastr::info(translate('Creator already assigned to this campaign.'));

            return back();
        }

        UrbanGoodzCreatorCampaignAssignment::create([
            'campaign_id' => $id,
            'creator_profile_id' => $request->creator_profile_id,
            'approval_status' => 'pending',
        ]);

        if ($campaign->status === 'open') {
            $campaign->update(['status' => 'assigned']);
        }

        Toastr::success(translate('Creator assigned to campaign.'));

        return back();
    }

    // ====== CONTENT ======

    public function content(Request $request)
    {
        $query = UrbanGoodzCreatorContent::with('profile');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('content_type')) {
            $query->where('content_type', $request->content_type);
        }
        if ($request->filled('is_shoppable')) {
            $query->where('is_shoppable', $request->is_shoppable === '1');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('title', 'like', "%$s%");
        }

        $content = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.creator.content', compact('content'));
    }

    public function contentShow($id)
    {
        $item = UrbanGoodzCreatorContent::with(['profile', 'application', 'campaign', 'earnings'])->findOrFail($id);

        return view('admin-views.urban-goodz.creator.content-show', compact('item'));
    }

    public function contentUpdateStatus($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_content_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $item = UrbanGoodzCreatorContent::findOrFail($id);
        $request->validate([
            'status' => ['required', 'in:draft,submitted,approved,rejected,published'],
            'admin_notes' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'is_shoppable' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'status' => $request->status,
            'admin_notes' => $request->admin_notes ?? $item->admin_notes,
        ];

        if ($request->has('is_featured')) {
            $updateData['is_featured'] = $request->boolean('is_featured');
        }
        if ($request->has('is_shoppable')) {
            $updateData['is_shoppable'] = $request->boolean('is_shoppable');
        }
        if ($request->status === 'published' && ! $item->published_at) {
            $updateData['published_at'] = now();
            $updateData['is_published'] = true;
        }

        $item->update($updateData);

        Toastr::success(translate('Content status updated.'));

        return back();
    }

    // ====== EARNINGS ======

    public function earnings(Request $request)
    {
        $query = UrbanGoodzCreatorEarning::with(['profile', 'application', 'campaign']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('creator_id')) {
            $query->where('creator_profile_id', $request->creator_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $earnings = $query->latest()->paginate(25)->appends($request->query());

        $totals = [
            'pending' => UrbanGoodzCreatorEarning::where('status', 'pending')->sum('amount'),
            'approved' => UrbanGoodzCreatorEarning::where('status', 'approved')->sum('amount'),
            'paid' => UrbanGoodzCreatorEarning::where('status', 'paid')->sum('amount'),
        ];

        $creators = UrbanGoodzCreatorProfile::where('is_approved', true)->orderBy('display_name')->get(['id', 'display_name']);

        return view('admin-views.urban-goodz.creator.earnings', compact('earnings', 'totals', 'creators'));
    }

    public function earningsApprove($id)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $earning = UrbanGoodzCreatorEarning::findOrFail($id);
        $earning->update(['status' => 'approved']);

        Toastr::success(translate('Earning approved for payout.'));

        return back();
    }

    public function earningsMarkPaid($id)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $earning = UrbanGoodzCreatorEarning::findOrFail($id);
        $earning->update(['status' => 'paid', 'paid_at' => now()]);

        Toastr::success(translate('Earning marked as paid.'));

        return back();
    }

    // ====== BUSINESS LEADS ======

    public function leads(Request $request)
    {
        $query = UrbanGoodzCreatorBusinessLead::with(['profile', 'application']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('suggested_module')) {
            $query->where('suggested_module', $request->suggested_module);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('business_name', 'like', "%$s%")
                    ->orWhere('category', 'like', "%$s%")
                    ->orWhere('city', 'like', "%$s%");
            });
        }

        $leads = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.creator.leads', compact('leads'));
    }

    public function leadsUpdateStatus($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_leads_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $lead = UrbanGoodzCreatorBusinessLead::findOrFail($id);
        $request->validate([
            'status' => ['required', 'in:new,researching,contacted,onboarding,onboarded,rejected'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $lead->update($request->only(['status', 'admin_notes']));

        Toastr::success(translate('Lead status updated.'));

        return back();
    }

    // ====== EVENT PROMOTIONS ======

    public function eventPromotions(Request $request)
    {
        $query = UrbanGoodzCreatorEventPromotion::with(['profile', 'application', 'event', 'campaign']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('promo_type')) {
            $query->where('promo_type', $request->promo_type);
        }
        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        $promotions = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.creator.event-promotions', compact('promotions'));
    }

    public function eventPromotionsUpdateStatus($id, Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_content_manage')) {
            Toastr::error(translate('messages.access_denied'));

            return back();
        }

        $promo = UrbanGoodzCreatorEventPromotion::findOrFail($id);
        $request->validate([
            'status' => ['required', 'in:draft,submitted,approved,live,completed'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $promo->update($request->only(['status', 'admin_notes']));

        Toastr::success(translate('Event promotion status updated.'));

        return back();
    }

    // ====== AI CREATOR TOOLS ======

    public function aiTools()
    {
        return view('admin-views.urban-goodz.creator.ai-tools');
    }

    public function aiGenerate(Request $request)
    {
        if (! Helpers::module_permission_check('urban_goodz_creator_ai_tools_use')) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'action' => ['required', 'in:caption,hashtags,script,summary,concept,draft_response,translate,trends'],
            'input' => ['required', 'string', 'max:2000'],
        ]);

        $action = $request->action;
        $input = $request->input;

        $config = BusinessSetting::where(['key' => 'openai_config'])->first();
        $apiKey = $config?->value ? json_decode($config->value, true)['OPENAI_API_KEY'] ?? null : null;

        if (! $apiKey) {
            $prompts = $this->localFallbackPrompts($action, $input);

            return response()->json([
                'success' => true,
                'output' => $prompts,
                'source' => 'local',
            ]);
        }

        try {
            $client = \OpenAI::client($apiKey);
            $systemPrompt = $this->getSystemPrompt($action);
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $input],
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            $output = $response->choices[0]->message->content;

            return response()->json([
                'success' => true,
                'output' => $output,
                'source' => 'openai',
            ]);
        } catch (\Exception $e) {
            $prompts = $this->localFallbackPrompts($action, $input);

            return response()->json([
                'success' => true,
                'output' => $prompts,
                'source' => 'local',
            ]);
        }
    }

    private function getSystemPrompt(string $action): string
    {
        $prompts = [
            'caption' => 'You are an expert social media caption writer for Urban Goodz, a local commerce and lifestyle marketplace. Write engaging, concise captions.',
            'hashtags' => 'Generate relevant, high-performing hashtags for Urban Goodz local commerce content. Mix broad and niche tags.',
            'script' => 'You are a video script writer for Urban Goodz creator content. Write engaging 30-60 second video scripts that promote local businesses, services, and products.',
            'summary' => 'Summarize the following product, service, or business information concisely for use in Urban Goodz creator content.',
            'concept' => 'Suggest creative video concepts for Urban Goodz creators. Focus on local commerce, fashion, food, events, and lifestyle content.',
            'draft_response' => 'Draft a professional response email or message from a creator to a vendor or brand regarding a campaign opportunity.',
            'translate' => 'Translate the following text while preserving the tone and intent. Provide both the original and translation.',
            'trends' => 'Suggest local content trends and content ideas relevant to Urban Goodz marketplace creators.',
        ];

        return $prompts[$action] ?? 'You are an AI assistant for Urban Goodz creator platform.';
    }

    private function localFallbackPrompts(string $action, string $input): string
    {
        $fallbacks = [
            'caption' => "Check out this amazing find on Urban Goodz! 🛍️\n\n{$input}\n\n#UrbanGoodz #ShopLocal #SupportBlackBusiness",
            'hashtags' => "#UrbanGoodz #ShopLocal #BlackOwned #CommunityFirst #LocalBusiness #SupportSmallBiz #{$this->sanitizeHashtag($input)}",
            'script' => "[OPEN: Hook shot in front of {$input}]\n\n\"Hey Urban Goodz fam! I just discovered something incredible and I had to share it with you all...\"\n\n[Cut to B-roll of the product/service]\n\n\"This is available NOW through Urban Goodz. Link in bio to learn more!\"\n\n[Close: Smile, wave, CTA to visit Urban Goodz app]",
            'summary' => "Discover {$input} on Urban Goodz — your local marketplace for amazing products and services in your community.",
            'concept' => "🎥 Video Concept: \"Day in the Life\" featuring {$input}\n\nShow how this business/service/product fits into a real daily routine. Behind-the-scenes, honest review, and a special offer for viewers.",
            'draft_response' => "Hi! Thank you so much for reaching out about this opportunity. I'm very interested in collaborating and would love to discuss the details further. Here are a few ideas I have for content we could create together...\n\nLooking forward to hearing from you!\n\nBest,\n[Creator Name]",
            'translate' => "Original: {$input}\n\nTranslation: [AI translation would appear here. Configure OpenAI API key in Admin > Business Settings > OpenAI Config for full AI features.]",
            'trends' => "Based on current Urban Goodz market trends, consider creating content around:\n\n1. Local business spotlights\n2. Behind-the-scenes at Urban Goodz partner stores\n3. Product unboxing/reviews\n4. 'How to style' fashion content\n5. Local event vlogs",
        ];

        return $fallbacks[$action] ?? "Urban Goodz Creator AI: '{$input}' — Configure OpenAI API key in settings for AI-powered responses.";
    }

    private function sanitizeHashtag(string $input): string
    {
        $tag = preg_replace('/[^a-zA-Z0-9]/', '', $input);

        return strlen($tag) > 20 ? substr($tag, 0, 20) : $tag;
    }

    // ====== REPORTS ======

    public function reports(Request $request)
    {
        $type = $request->type ?? 'performance';
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : now()->subMonth();
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : now();
        $creatorId = $request->creator_id;

        $creators = UrbanGoodzCreatorProfile::where('is_approved', true)->orderBy('display_name')->get(['id', 'display_name']);

        $reportData = [];

        switch ($type) {
            case 'performance':
                $reportData = $this->buildPerformanceReport($dateFrom, $dateTo, $creatorId);
                break;
            case 'revenue':
                $reportData = $this->buildRevenueReport($dateFrom, $dateTo, $creatorId);
                break;
            case 'campaign':
                $reportData = $this->buildCampaignReport($dateFrom, $dateTo);
                break;
            case 'leads':
                $reportData = $this->buildLeadReport($dateFrom, $dateTo, $creatorId);
                break;
            case 'payout':
                $reportData = $this->buildPayoutReport($dateFrom, $dateTo, $creatorId);
                break;
            case 'event-promo':
                $reportData = $this->buildEventPromoReport($dateFrom, $dateTo, $creatorId);
                break;
        }

        return view('admin-views.urban-goodz.creator.reports', compact(
            'type', 'dateFrom', 'dateTo', 'creatorId', 'creators', 'reportData'
        ));
    }

    private function buildPerformanceReport($from, $to, $creatorId = null)
    {
        $query = UrbanGoodzCreatorProfile::where('is_approved', true)
            ->withCount(['content', 'campaigns', 'leads'])
            ->withSum(['earnings' => fn ($q) => $q->where('status', 'paid')], 'amount');

        if ($creatorId) {
            $query->where('id', $creatorId);
        }

        return [
            'rows' => $query->get(),
            'totals' => [
                'total_creators' => UrbanGoodzCreatorProfile::where('is_approved', true)->count(),
                'total_content' => UrbanGoodzCreatorContent::whereBetween('created_at', [$from, $to])->count(),
                'total_campaigns' => UrbanGoodzCreatorCampaignAssignment::whereBetween('created_at', [$from, $to])->count(),
                'total_earned' => UrbanGoodzCreatorEarning::where('status', 'paid')->whereBetween('created_at', [$from, $to])->sum('amount'),
            ],
        ];
    }

    private function buildRevenueReport($from, $to, $creatorId = null)
    {
        $query = UrbanGoodzCreatorEarning::with('profile')
            ->whereBetween('created_at', [$from, $to]);

        if ($creatorId) {
            $query->where('creator_profile_id', $creatorId);
        }

        $rows = $query->latest()->get();

        return [
            'rows' => $rows,
            'totals' => [
                'total_pending' => $rows->where('status', 'pending')->sum('amount'),
                'total_approved' => $rows->where('status', 'approved')->sum('amount'),
                'total_paid' => $rows->where('status', 'paid')->sum('amount'),
                'count' => $rows->count(),
            ],
        ];
    }

    private function buildCampaignReport($from, $to)
    {
        $campaigns = UrbanGoodzCreatorCampaign::withCount('assignments')
            ->withSum(['earnings' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'rows' => $campaigns,
            'totals' => [
                'total_campaigns' => $campaigns->count(),
                'total_assignments' => $campaigns->sum('assignments_count'),
                'total_payout' => $campaigns->sum('earnings_sum_amount'),
            ],
        ];
    }

    private function buildLeadReport($from, $to, $creatorId = null)
    {
        $query = UrbanGoodzCreatorBusinessLead::whereBetween('created_at', [$from, $to]);

        if ($creatorId) {
            $query->where('creator_profile_id', $creatorId);
        }

        $rows = $query->get();

        return [
            'rows' => $rows,
            'totals' => [
                'total' => $rows->count(),
                'onboarded' => $rows->where('status', 'onboarded')->count(),
                'new' => $rows->where('status', 'new')->count(),
            ],
        ];
    }

    private function buildPayoutReport($from, $to, $creatorId = null)
    {
        $query = UrbanGoodzCreatorEarning::with('profile')
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('created_at', [$from, $to]);

        if ($creatorId) {
            $query->where('creator_profile_id', $creatorId);
        }

        $rows = $query->latest()->get();

        return [
            'rows' => $rows,
            'totals' => [
                'total_payouts' => $rows->sum('amount'),
                'approved_count' => $rows->where('status', 'approved')->count(),
                'paid_count' => $rows->where('status', 'paid')->count(),
            ],
        ];
    }

    private function buildEventPromoReport($from, $to, $creatorId = null)
    {
        $query = UrbanGoodzCreatorEventPromotion::with(['profile', 'event'])
            ->whereBetween('created_at', [$from, $to]);

        if ($creatorId) {
            $query->where('creator_profile_id', $creatorId);
        }

        $rows = $query->get();

        return [
            'rows' => $rows,
            'totals' => [
                'total' => $rows->count(),
                'live' => $rows->where('status', 'live')->count(),
                'commission' => $rows->sum('commission_earned'),
            ],
        ];
    }

    // ====== HELPERS ======

    private function generateHandle(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $handle = $base;
        $counter = 1;

        while (UrbanGoodzCreatorProfile::where('handle', $handle)->exists()) {
            $handle = $base.$counter;
            $counter++;
        }

        return $handle;
    }
}
