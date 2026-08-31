<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\AiActionLog;
use App\Models\AiAgent;
use App\Models\AiApproval;
use App\Models\AiCopilotRecommendation;
use App\Models\AiCopilotSetting;
use App\Models\AiModuleAutomationSetting;
use App\Models\AiOutreachTemplate;
use App\Models\AiRiskRule;
use App\Models\AiTask;
use App\Models\AiWorkforceAction;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;
use App\Services\UrbanGoodz\AiChiefOfStaffService;
use App\Services\UrbanGoodz\AiWorkforceSettingsService;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\UrbanGoodzAIConciergeService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiOperationsController extends Controller
{
    public function __construct(
        protected UrbanGoodzAIConciergeService $conciergeService,
        protected UrbanGoodzAIService $aiService,
        protected AiWorkforceSettingsService $workforceSettingsService,
    ) {}

    public function index()
    {
        $providerConfigured = $this->aiService->isConfigured();

        $copilotMode = AiCopilotSetting::where('key', 'ai_ops_enabled')->value('value') ?? 'off';

        $conversationsToday = UrbanGoodzAIConversation::whereDate('created_at', today())->count();
        $conversationsTotal = UrbanGoodzAIConversation::count();
        $intentsActive = UrbanGoodzAIIntent::where('is_active', true)->count();
        $actionsToday = AiActionLog::whereDate('created_at', today())->count();

        $recommendationsPending = AiCopilotRecommendation::where('status', 'pending')->count();
        $recommendationsAccepted = AiCopilotRecommendation::where('status', 'accepted')->count();
        $recommendationsDismissed = AiCopilotRecommendation::where('status', 'dismissed')->count();

        $modulesEnabled = AiModuleAutomationSetting::where('enabled', true)->count();
        $modulesTotal = AiModuleAutomationSetting::count();

        $riskRulesActive = AiRiskRule::where('enabled', true)->count();
        $riskRulesTotal = AiRiskRule::count();

        $providerStatus = [
            'openai_configured' => $providerConfigured,
            'model' => config('urban_goodz.ai_model', 'gpt-4o'),
            'base_url' => $this->maskUrl(config('openai.base_url', 'https://api.openai.com/v1')),
            'timeout' => config('openai.request_timeout', 60),
        ];

        $featureToggles = AiCopilotSetting::whereIn('key', [
            'ai_ops_enabled',
            'ai_auto_dispatch_enabled',
            'ai_auto_customer_support_enabled',
            'ai_auto_driver_support_enabled',
            'ai_auto_vendor_support_enabled',
            'ai_auto_order_anywhere_triage_enabled',
            'ai_auto_package_route_assignment_enabled',
            'ai_auto_business_courier_assignment_enabled',
            'ai_escalate_high_risk_to_admin',
            'ai_audit_log_enabled',
        ])->pluck('value', 'key')->toArray();

        $loadStats = [
            'total' => UrbanGoodzLoadBoardLoad::count(),
            'available' => UrbanGoodzLoadBoardLoad::where('status', 'available')->count(),
            'assigned' => UrbanGoodzLoadBoardLoad::where('status', 'assigned')->count(),
            'in_transit' => UrbanGoodzLoadBoardLoad::whereIn('status', ['in_transit', 'picked_up'])->count(),
            'delivered' => UrbanGoodzLoadBoardLoad::where('status', 'delivered')->count(),
        ];

        return view('admin-views.urban-goodz.ai-operations.index', compact(
            'providerConfigured',
            'copilotMode',
            'conversationsToday',
            'conversationsTotal',
            'intentsActive',
            'actionsToday',
            'recommendationsPending',
            'recommendationsAccepted',
            'recommendationsDismissed',
            'modulesEnabled',
            'modulesTotal',
            'riskRulesActive',
            'riskRulesTotal',
            'providerStatus',
            'featureToggles',
            'loadStats',
        ));
    }

    public function featureControls()
    {
        if (request()->isMethod('post')) {
            $validated = request()->validate([
                'ai_auto_dispatch_enabled' => 'boolean',
                'ai_auto_customer_support_enabled' => 'boolean',
                'ai_auto_driver_support_enabled' => 'boolean',
                'ai_auto_vendor_support_enabled' => 'boolean',
                'ai_auto_order_anywhere_triage_enabled' => 'boolean',
                'ai_auto_package_route_assignment_enabled' => 'boolean',
                'ai_auto_business_courier_assignment_enabled' => 'boolean',
                'ai_escalate_high_risk_to_admin' => 'boolean',
                'ai_audit_log_enabled' => 'boolean',
            ]);

            foreach ($validated as $key => $value) {
                AiCopilotSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value ? '1' : '0']
                );
            }

            Toastr::success(translate('Feature toggles updated successfully.'));
            return redirect()->route('admin.urban-goodz.ai-operations.feature-controls');
        }

        $featureToggles = AiCopilotSetting::whereIn('key', [
            'ai_auto_dispatch_enabled',
            'ai_auto_customer_support_enabled',
            'ai_auto_driver_support_enabled',
            'ai_auto_vendor_support_enabled',
            'ai_auto_order_anywhere_triage_enabled',
            'ai_auto_package_route_assignment_enabled',
            'ai_auto_business_courier_assignment_enabled',
            'ai_escalate_high_risk_to_admin',
            'ai_audit_log_enabled',
        ])->pluck('value', 'key')->toArray();

        return view('admin-views.urban-goodz.ai-operations.feature-controls', compact('featureToggles'));
    }

    public function logs(Request $request)
    {
        $query = AiActionLog::with('recommendation', 'approver')->latest();

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action_taken')) {
            $query->where('action_taken', 'like', "%{$request->action_taken}%");
        }

        $logs = $query->paginate(50);
        $modules = AiModuleAutomationSetting::pluck('module')->toArray();

        return view('admin-views.urban-goodz.ai-operations.logs', compact('logs', 'modules'));
    }

    public function usage()
    {
        $conversationStats = [
            'total' => UrbanGoodzAIConversation::count(),
            'today' => UrbanGoodzAIConversation::whereDate('created_at', today())->count(),
            'this_week' => UrbanGoodzAIConversation::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => UrbanGoodzAIConversation::where('created_at', '>=', now()->startOfMonth())->count(),
            'resolved' => UrbanGoodzAIConversation::where('status', 'resolved')->count(),
            'pending' => UrbanGoodzAIConversation::where('status', 'pending')->count(),
            'escalated' => UrbanGoodzAIConversation::where('status', 'escalated')->count(),
        ];

        $actionStats = [
            'total' => AiActionLog::count(),
            'today' => AiActionLog::whereDate('created_at', today())->count(),
            'this_week' => AiActionLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => AiActionLog::where('created_at', '>=', now()->startOfMonth())->count(),
            'auto_executed' => AiCopilotRecommendation::where('metadata->auto_executed', true)->count(),
            'human_approved' => AiCopilotRecommendation::where('status', 'accepted')
                ->whereNull('metadata->auto_executed')->count(),
        ];

        $recommendationStats = [
            'total' => AiCopilotRecommendation::count(),
            'pending' => AiCopilotRecommendation::where('status', 'pending')->count(),
            'accepted' => AiCopilotRecommendation::where('status', 'accepted')->count(),
            'dismissed' => AiCopilotRecommendation::where('status', 'dismissed')->count(),
            'by_type' => AiCopilotRecommendation::selectRaw('recommendation_type, COUNT(*) as count')
                ->groupBy('recommendation_type')
                ->pluck('count', 'recommendation_type')
                ->toArray(),
        ];

        $dailyConversations = UrbanGoodzAIConversation::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyActions = AiActionLog::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin-views.urban-goodz.ai-operations.usage', compact(
            'conversationStats',
            'actionStats',
            'recommendationStats',
            'dailyConversations',
            'dailyActions',
        ));
    }

    public function testEndpoint(Request $request)
    {
        if (request()->isMethod('post')) {
            $validated = $request->validate([
                'query' => 'required|string|max:2000',
            ]);

            try {
                $start = microtime(true);
                $conversation = $this->conciergeService->processQuery(
                    $validated['query'],
                    null,
                    'admin_test'
                );
                $elapsed = round((microtime(true) - $start) * 1000);

                $result = [
                    'success' => true,
                    'response' => $conversation->response_text,
                    'intent' => $conversation->detectedIntent?->name ?? 'Unknown',
                    'confidence' => $conversation->confidence_score,
                    'status' => $conversation->status,
                    'elapsed_ms' => $elapsed,
                ];
            } catch (\Throwable $e) {
                $result = [
                    'success' => false,
                    'response' => 'Test failed: ' . $e->getMessage(),
                    'intent' => null,
                    'confidence' => null,
                    'status' => null,
                    'elapsed_ms' => null,
                ];
            }

            return view('admin-views.urban-goodz.ai-operations.test', [
                'result' => $result,
                'lastQuery' => $validated['query'],
            ]);
        }

        return view('admin-views.urban-goodz.ai-operations.test', [
            'result' => null,
            'lastQuery' => null,
        ]);
    }

    public function getLoadSourcingStatus()
    {
        $stats = [
            'total_loads' => UrbanGoodzLoadBoardLoad::count(),
            'available' => UrbanGoodzLoadBoardLoad::where('status', 'available')->count(),
            'assigned' => UrbanGoodzLoadBoardLoad::where('status', 'assigned')->count(),
            'in_transit' => UrbanGoodzLoadBoardLoad::whereIn('status', ['in_transit', 'picked_up'])->count(),
            'delivered' => UrbanGoodzLoadBoardLoad::where('status', 'delivered')->count(),
            'cancelled' => UrbanGoodzLoadBoardLoad::where('status', 'cancelled')->count(),
            'avg_rate_per_mile' => UrbanGoodzLoadBoardLoad::where('rate_per_mile', '>', 0)->avg('rate_per_mile'),
            'total_payout' => UrbanGoodzLoadBoardLoad::sum('payout_amount'),
            'unassigned_count' => UrbanGoodzLoadBoardLoad::whereNull('assigned_driver_id')
                ->where('status', 'available')->count(),
            'by_state' => UrbanGoodzLoadBoardLoad::selectRaw('origin_state, COUNT(*) as count')
                ->whereNotNull('origin_state')
                ->groupBy('origin_state')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'origin_state')
                ->toArray(),
            'by_equipment' => UrbanGoodzLoadBoardLoad::selectRaw('equipment_type, COUNT(*) as count')
                ->whereNotNull('equipment_type')
                ->groupBy('equipment_type')
                ->orderByDesc('count')
                ->pluck('count', 'equipment_type')
                ->toArray(),
        ];

        $pendingRecommendations = AiCopilotRecommendation::where('recommendation_type', 'like', 'load_board%')
            ->where('status', 'pending')
            ->count();

        $recentRecommendations = AiCopilotRecommendation::where('recommendation_type', 'like', 'load_board%')
            ->with('reviewer')
            ->latest()
            ->limit(20)
            ->get();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(compact('stats', 'pendingRecommendations', 'recentRecommendations'));
        }

        return view('admin-views.urban-goodz.ai-operations.load-sourcing', compact(
            'stats',
            'pendingRecommendations',
            'recentRecommendations',
        ));
    }

    public function workforceOverview()
    {
        $agentCount = AiAgent::count();
        $activeAgentCount = AiAgent::active()->count();
        $pendingTaskCount = AiTask::where('status', 'pending')->count();
        $awaitingApprovalCount = AiTask::where('status', 'awaiting_approval')->count();
        $pendingActionCount = AiWorkforceAction::where('approval_status', 'pending')->count();
        $pendingApprovalCount = AiApproval::where('decision', 'pending')->count();
        $contactableProspects = MerchantProspect::contactable()->count();
        $activeTemplates = AiOutreachTemplate::active()->count();

        return view('admin-views.urban-goodz.ai-operations.workforce.index', compact(
            'agentCount',
            'activeAgentCount',
            'pendingTaskCount',
            'awaitingApprovalCount',
            'pendingActionCount',
            'pendingApprovalCount',
            'contactableProspects',
            'activeTemplates'
        ));
    }

    public function agents(Request $request)
    {
        $agents = AiAgent::withCount(['tasks', 'actions'])
            ->orderBy('status')
            ->orderBy('name')
            ->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.agents', compact('agents'));
    }

    public function tasks(Request $request)
    {
        $query = AiTask::with('agent')->latest();

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.tasks', compact('tasks'));
    }

    public function approvals(Request $request)
    {
        $query = AiApproval::with(['action.agent', 'requestedApprover', 'approver'])->latest();

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('decision')) {
            $query->where('decision', $request->decision);
        }

        $approvals = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.approvals', compact('approvals'));
    }

    public function prospects(Request $request)
    {
        $query = MerchantProspect::withCount(['outreachMessages'])->latest();

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('status')) {
            $query->where('prospect_status', $request->status);
        }

        $prospects = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.prospects', compact('prospects'));
    }

    public function workforceActions(Request $request)
    {
        $query = AiWorkforceAction::with('agent', 'task')->latest();

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        $actions = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.actions', compact('actions'));
    }

    public function businessNeeds(Request $request)
    {
        $query = BusinessNeed::latest();

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $needs = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.business_needs', compact('needs'));
    }

    public function humanActionItems(Request $request)
    {
        $query = HumanActionItem::with('agent', 'task', 'action')->latest();

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_role')) {
            $query->where('assigned_role', $request->assigned_role);
        }

        $items = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-operations.workforce.human_actions', compact('items'));
    }

    public function briefs(Request $request)
    {
        $chiefOfStaff = app(AiChiefOfStaffService::class);

        $selectedRole = $request->get('role', 'Executive');
        
        if ($selectedRole === 'Executive') {
            $brief = $chiefOfStaff->generateExecutiveDailyBrief();
        } else {
            $brief = $chiefOfStaff->generateRoleBrief($selectedRole);
        }

        return view('admin-views.urban-goodz.ai-operations.workforce.briefs', compact('brief', 'selectedRole'));
    }

    public function settings(Request $request)
    {
        $settings = $this->workforceSettingsService->all();
        return view('admin-views.urban-goodz.ai-operations.workforce.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'global_kill_switch' => ['nullable', 'boolean'],
            'demand_min_requests' => ['required', 'integer', 'min:1', 'max:100000'],
            'demand_min_customers' => ['required', 'integer', 'min:1', 'max:100000'],
            'demand_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'demand_cooldown_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sender_name' => ['required', 'string', 'max:100'],
            'sender_email' => ['nullable', 'email:rfc', 'max:191'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'hours_start' => ['required', 'date_format:H:i'],
            'hours_end' => ['required', 'date_format:H:i', 'after:hours_start'],
        ]);

        $validated['enabled'] = $request->boolean('enabled');
        $validated['global_kill_switch'] = $request->boolean('global_kill_switch');
        $validated['sender_email'] = $validated['sender_email'] ?? '';

        $this->workforceSettingsService->save($validated);

        Toastr::success(translate('AI Workforce settings updated successfully.'));
        return back();
    }

    public function chiefOfStaff(Request $request, AiChiefOfStaffService $chiefOfStaffService)
    {
        // Fails closed and matches the sidebar guard, so navigation visibility
        // and page access cannot disagree. role_id == 1 (owner) is authoritative
        // inside module_permission_check and always passes.
        if (! Helpers::module_permission_check('urban_goodz_control_center')) {
            abort(403, translate('messages.access_denied'));
        }

        $brief = $chiefOfStaffService->generateExecutiveDailyBrief();
        $summary = $chiefOfStaffService->getCommandCenterSummary();
        $diagnostics = $chiefOfStaffService->runDiagnosticScan();
        $persona = config('urban_goodz_personas.personas.chief_of_staff.presentation', []);
        $narration = $chiefOfStaffService->narrateExecutiveBrief(auth('admin')->user()?->f_name);

        return view(
            'admin-views.urban-goodz.ai-chief-of-staff.index',
            compact('brief', 'summary', 'diagnostics', 'persona', 'narration')
        );
    }

    /**
     * Monique's real conversational chat (chief_of_staff persona, display
     * name swapped from "Skylar") -- distinct from testEndpoint() above,
     * which runs the concierge pipeline (now displayed as "Skylar") for
     * generic admin debugging. This always speaks as chief_of_staff,
     * grounded in live Command Center data, with real per-session memory.
     */
    public function chiefOfStaffChat(Request $request, \App\Services\UrbanGoodz\UrbanGoodzAIChiefOfStaffChatService $chat)
    {
        if (! Helpers::module_permission_check('urban_goodz_control_center')) {
            abort(403, translate('messages.access_denied'));
        }

        $validated = $request->validate([
            'query' => 'required|string|max:2000',
            'session_id' => 'nullable|string|max:64',
        ]);

        $admin = auth('admin')->user();
        $adminName = $admin?->f_name ? "{$admin->f_name} {$admin->l_name}" : null;

        try {
            $conversation = $chat->processQuery(
                queryText: $validated['query'],
                adminId: $admin?->id,
                adminName: $adminName,
                sessionId: $validated['session_id'] ?? null,
            );

            return response()->json([
                'success' => $conversation->status !== 'failed',
                'data' => [
                    'response' => $conversation->response_text,
                    'flagged_as_urgent' => $conversation->metadata['flagged_as_urgent'] ?? false,
                    'status' => $conversation->status,
                ],
            ], $conversation->status === 'failed' ? 503 : 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data' => ['response' => 'Monique could not process that request. No action was taken.'],
            ], 503);
        }
    }

    /**
     * Synthesize Monique's spoken executive brief (or any chief_of_staff
     * text) server-side via ElevenLabs, for the admin panel's own
     * session-authenticated surface. Mirrors
     * Api\V1\UrbanGoodz\DigitalHumanController@speak, which is scoped to
     * the Passport `auth:api` guard used by the customer/vendor/driver
     * apps -- the admin dashboard authenticates through the `admin`
     * session guard instead, so it needs its own route into the same
     * ElevenLabsVoiceService rather than reusing that one.
     */
    public function chiefOfStaffSpeak(Request $request, \App\Services\UrbanGoodz\AI\DigitalHuman\ElevenLabsVoiceService $voice)
    {
        if (! Helpers::module_permission_check('urban_goodz_control_center')) {
            abort(403, translate('messages.access_denied'));
        }

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $result = $voice->synthesize('chief_of_staff', $validated['text']);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error_code' => $result['error_code'],
                'message' => $result['message'],
            ], $result['error_code'] === 'not_configured' ? 503 : 502);
        }

        return response($result['audio'], 200)
            ->header('Content-Type', $result['mime']);
    }

    private function maskUrl(string $url): string
    {
        if (empty($url)) {
            return '(not set)';
        }

        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return '(not set)';
        }

        $scheme = $parsed['scheme'] ?? 'https';
        return $scheme . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '') . '/***';
    }
}
