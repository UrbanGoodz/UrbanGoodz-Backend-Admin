<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiActionLog;
use App\Models\AiCopilotRecommendation;
use App\Models\AiCopilotSetting;
use App\Models\AiModuleAutomationSetting;
use App\Models\AiRiskRule;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\AiCopilotService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiCopilotController extends Controller
{
    public function __construct(
        protected AiCopilotService $copilotService
    ) {}

    public function index(Request $request)
    {
        $query = AiCopilotRecommendation::with('reviewer')
            ->latest();

        $typeAliasMap = [
            'dispatch_suggestion' => ['dispatch_suggestion', 'dispatch'],
            'stuck_order_alert' => ['stuck_order_alert', 'stuck_order', 'stuck_orders'],
            'order_anywhere_triage' => ['order_anywhere_triage', 'order_anywhere'],
            'package_monitoring' => ['package_monitoring'],
            'age_verification_alert' => ['age_verification_alert', 'age_verification'],
            'load_board_alert' => ['load_board_alert', 'load_board', 'load_board_stale', 'load_board_lane', 'load_board_demand'],
            'load_acceptance_suggestion' => ['load_acceptance_suggestion', 'load_board_accept', 'load_board_driver_match'],
            'load_pricing_anomaly' => ['load_pricing_anomaly', 'load_board_repricing', 'load_board_pricing'],
        ];

        if ($request->filled('type')) {
            $reqType = $request->type;
            $matchedTypes = [$reqType];
            foreach ($typeAliasMap as $key => $aliases) {
                if ($reqType === $key || in_array($reqType, $aliases)) {
                    $matchedTypes = array_merge($matchedTypes, [$key], $aliases);
                }
            }
            $matchedTypes = array_unique($matchedTypes);
            $query->where(function($q) use ($matchedTypes, $reqType) {
                $q->whereIn('recommendation_type', $matchedTypes)
                  ->orWhere('recommendation_type', 'like', $reqType . '%');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        } else if (!$request->filled('status')) {
            $query->where('status', 'pending');
        }

        $recommendations = $query->paginate(25);

        $stats = [
            'total_pending' => AiCopilotRecommendation::where('status', 'pending')->count(),
            'total_accepted' => AiCopilotRecommendation::where('status', 'accepted')->count(),
            'total_dismissed' => AiCopilotRecommendation::where('status', 'dismissed')->count(),
            'total_snoozed' => AiCopilotRecommendation::where('status', 'snoozed')->count(),
            'total_dont_show_again' => AiCopilotRecommendation::where('status', 'dont_show_again')->count(),
            'total_resolved' => AiCopilotRecommendation::where('status', 'resolved')->count(),
            'by_type' => AiCopilotRecommendation::selectRaw('recommendation_type, COUNT(*) as count')
                ->where('status', 'pending')
                ->groupBy('recommendation_type')
                ->pluck('count', 'recommendation_type')
                ->toArray(),
        ];

        $mode = $this->copilotService->getMode();
        $settings = $this->copilotService->getAllSettings();

        return view('admin-views.urban-goodz.ai-copilot.index', compact('recommendations', 'stats', 'mode', 'settings'));
    }

    public function generate(Request $request)
    {
        try {
            $type = $request->query('type');
            $results = $this->copilotService->generateRecommendations($type);

            if (empty($results)) {
                Toastr::info('AI Ops is currently disabled. Enable it in Settings to generate recommendations.');
                return redirect()->route('admin.urban-goodz.ai-copilot.settings');
            }

            $total = collect($results)->sum('count');

            $this->copilotService->notifyHighConfidenceRecommendations($results);

            $typeLabel = $type ? ucwords(str_replace('_', ' ', $type)) : 'All Categories';
            Toastr::success("AI Copilot generated {$total} recommendations for {$typeLabel}.");

            return redirect()->route('admin.urban-goodz.ai-copilot.index', array_filter(['type' => $type]));
        } catch (\Exception $e) {
            Toastr::error('Failed to generate recommendations: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    public function accept(Request $request, $id)
    {
        $adminId = auth('admin')->id() ?? auth()->id() ?? 1;
        $rec = $this->copilotService->accept((int) $id, $adminId, $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found or already processed');
            return redirect()->back();
        }

        Toastr::success('Recommendation accepted');
        return redirect()->back();
    }

    public function dismiss(Request $request, $id)
    {
        $adminId = auth('admin')->id() ?? auth()->id() ?? 1;
        $rec = $this->copilotService->dismiss((int) $id, $adminId, $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found or already processed');
            return redirect()->back();
        }

        Toastr::success('Recommendation dismissed once');
        return redirect()->back();
    }

    public function snooze(Request $request, $id)
    {
        $until = $request->input('until_date');
        $days = $request->input('days');
        if ($days) {
            $until = now()->addDays((int) $days)->toIso8601String();
        }
        if (!$until) {
            $until = now()->addDays(7)->toIso8601String();
        }

        $adminId = auth('admin')->id() ?? auth()->id() ?? 1;
        $rec = $this->copilotService->snooze((int) $id, $adminId, $until, $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found');
            return redirect()->back();
        }

        Toastr::success('Recommendation snoozed until ' . \Carbon\Carbon::parse($until)->format('Y-m-d H:i'));
        return redirect()->back();
    }

    public function dontShowAgain(Request $request, $id)
    {
        $adminId = auth('admin')->id() ?? auth()->id() ?? 1;
        $rec = $this->copilotService->dontShowAgain((int) $id, $adminId, $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found');
            return redirect()->back();
        }

        Toastr::success('Recommendation permanently suppressed (Don\'t Show Again)');
        return redirect()->back();
    }

    public function resolve(Request $request, $id)
    {
        $adminId = auth('admin')->id() ?? auth()->id() ?? 1;
        $rec = $this->copilotService->resolve((int) $id, $adminId, $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found');
            return redirect()->back();
        }

        Toastr::success('Recommendation marked as resolved');
        return redirect()->back();
    }

    public function restore(Request $request, $id)
    {
        $adminId = auth('admin')->id() ?? auth()->id() ?? 1;
        $rec = $this->copilotService->restore((int) $id, $adminId, $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found');
            return redirect()->back();
        }

        Toastr::success('Recommendation restored to pending');
        return redirect()->back();
    }

    public function suppressed(Request $request)
    {
        $query = AiCopilotRecommendation::with('reviewer')
            ->whereIn('status', ['snoozed', 'dont_show_again', 'dismissed', 'resolved'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $recommendations = $query->paginate(25);

        return view('admin-views.urban-goodz.ai-copilot.suppressed', compact('recommendations'));
    }

    public function rollback(Request $request, $logId)
    {
        $log = $this->copilotService->rollback((int) $logId, auth('admin')->id(), $request->admin_notes);

        if (!$log) {
            Toastr::error('Action log not found or rollback not available');
            return redirect()->back();
        }

        Toastr::success('Action rolled back successfully');
        return redirect()->route('admin.urban-goodz.ai-copilot.action-logs');
    }

    public function show($id)
    {
        $recommendation = AiCopilotRecommendation::with('reviewer')->findOrFail($id);
        return view('admin-views.urban-goodz.ai-copilot.show', compact('recommendation'));
    }

    public function settings()
    {
        $allSettings = AiCopilotSetting::all()->keyBy('key');
        return view('admin-views.urban-goodz.ai-copilot.settings', compact('allSettings'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'ai_ops_enabled' => ['required', Rule::in(['off', 'recommend_only', 'supervised_automation', 'full_low_risk_automation', 'restricted_human_locked'])],
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
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        Toastr::success('AI Ops Copilot settings saved');
        return redirect()->route('admin.urban-goodz.ai-copilot.settings');
    }

    public function moduleSettings()
    {
        $modules = AiModuleAutomationSetting::orderBy('module')->get();
        return view('admin-views.urban-goodz.ai-copilot.module-settings', compact('modules'));
    }

    public function saveModuleSettings(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:ai_module_automation_settings,id',
            'enabled' => 'boolean',
            'automation_mode' => 'nullable|string|max:50',
            'min_confidence_score' => 'numeric|min:0|max:1',
            'max_auto_action_amount' => 'nullable|numeric|min:0',
            'max_risk_level' => 'required|in:low,medium,high,critical',
        ]);

        $setting = AiModuleAutomationSetting::findOrFail($validated['module_id']);
        $setting->update([
            'enabled' => $request->boolean('enabled'),
            'automation_mode' => $validated['automation_mode'] ?? $setting->automation_mode,
            'min_confidence_score' => $validated['min_confidence_score'],
            'max_auto_action_amount' => $validated['max_auto_action_amount'],
            'max_risk_level' => $validated['max_risk_level'],
        ]);

        Toastr::success(translate('Module automation settings updated'));
        return redirect()->route('admin.urban-goodz.ai-copilot.module-settings');
    }

    public function riskRules()
    {
        $rules = AiRiskRule::orderBy('risk_level')->orderBy('rule_name')->get();
        return view('admin-views.urban-goodz.ai-copilot.risk-rules', compact('rules'));
    }

    public function saveRiskRule(Request $request)
    {
        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'trigger_type' => 'required|string|max:100',
            'trigger_operator' => 'nullable|string|max:20',
            'trigger_value' => 'nullable|string|max:255',
            'risk_level' => 'required|in:low,medium,high,critical',
            'requires_approval' => 'boolean',
            'escalation_action' => 'nullable|string|max:50',
            'enabled' => 'boolean',
        ]);

        AiRiskRule::create($validated);

        Toastr::success(translate('Risk rule created'));
        return redirect()->route('admin.urban-goodz.ai-copilot.risk-rules');
    }

    public function editRiskRule($id)
    {
        $rule = AiRiskRule::findOrFail($id);
        return response()->json($rule);
    }

    public function updateRiskRule(Request $request, $id)
    {
        $rule = AiRiskRule::findOrFail($id);

        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'trigger_type' => 'required|string|max:100',
            'trigger_operator' => 'nullable|string|max:20',
            'trigger_value' => 'nullable|string|max:255',
            'risk_level' => 'required|in:low,medium,high,critical',
            'requires_approval' => 'boolean',
            'escalation_action' => 'nullable|string|max:50',
            'enabled' => 'boolean',
        ]);

        $rule->update($validated);

        Toastr::success(translate('Risk rule updated'));
        return redirect()->route('admin.urban-goodz.ai-copilot.risk-rules');
    }

    public function deleteRiskRule($id)
    {
        $rule = AiRiskRule::findOrFail($id);
        $rule->delete();

        Toastr::success(translate('Risk rule deleted'));
        return redirect()->route('admin.urban-goodz.ai-copilot.risk-rules');
    }

    public function toggleRiskRule($id)
    {
        $rule = AiRiskRule::findOrFail($id);
        $rule->update(['enabled' => !$rule->enabled]);

        Toastr::success($rule->enabled ? translate('Risk rule enabled') : translate('Risk rule disabled'));
        return redirect()->route('admin.urban-goodz.ai-copilot.risk-rules');
    }

    public function actionLogs(Request $request)
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

        return view('admin-views.urban-goodz.ai-copilot.action-logs', compact('logs', 'modules'));
    }

    public function loadBoardAnalytics()
    {
        $totalLoads = UrbanGoodzLoadBoardLoad::count();
        $activeLoads = UrbanGoodzLoadBoardLoad::where('status', '!=', 'expired')->count();
        $avgRate = UrbanGoodzLoadBoardLoad::where('rate_per_mile', '>', 0)->avg('rate_per_mile');
        $totalPayout = UrbanGoodzLoadBoardLoad::sum('payout_amount');

        $loadsByState = UrbanGoodzLoadBoardLoad::selectRaw('origin_state, COUNT(*) as count')
            ->groupBy('origin_state')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        $loadsByEquipment = UrbanGoodzLoadBoardLoad::selectRaw('equipment_type, COUNT(*) as count')
            ->groupBy('equipment_type')
            ->orderByDesc('count')
            ->get();

        $loadRecs = \App\Models\AiCopilotRecommendation::where('recommendation_type', 'like', 'load_board%')
            ->latest()
            ->paginate(25);

        $loadRecStats = \App\Models\AiCopilotRecommendation::where('recommendation_type', 'like', 'load_board%')
            ->selectRaw('recommendation_type, status, COUNT(*) as count')
            ->groupBy('recommendation_type', 'status')
            ->get()
            ->groupBy('recommendation_type');

        $loadRecsPending = \App\Models\AiCopilotRecommendation::where('recommendation_type', 'like', 'load_board%')
            ->where('status', 'pending')
            ->count();

        $loadRecsExecuted = \App\Models\AiCopilotRecommendation::where('recommendation_type', 'like', 'load_board%')
            ->where('status', 'accepted')
            ->count();

        $weeklyTrend = UrbanGoodzLoadBoardLoad::selectRaw('YEAR(created_at) as y, WEEK(created_at) as w, COUNT(*) as count')
            ->where('created_at', '>=', now()->subWeeks(8))
            ->groupBy('y', 'w')
            ->orderBy('y')
            ->orderBy('w')
            ->get();

        return view('admin-views.urban-goodz.ai-copilot.load-board-analytics', compact(
            'totalLoads', 'activeLoads', 'avgRate', 'totalPayout',
            'loadsByState', 'loadsByEquipment', 'loadRecs', 'loadRecStats',
            'loadRecsPending', 'loadRecsExecuted', 'weeklyTrend'
        ));
    }
}
