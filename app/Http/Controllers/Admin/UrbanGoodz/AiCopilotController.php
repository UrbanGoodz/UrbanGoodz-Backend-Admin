<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiActionLog;
use App\Models\AiCopilotRecommendation;
use App\Models\AiCopilotSetting;
use App\Models\AiModuleAutomationSetting;
use App\Models\AiRiskRule;
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

        if ($request->filled('type')) {
            $query->where('recommendation_type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $recommendations = $query->paginate(25);

        $stats = [
            'total_pending' => AiCopilotRecommendation::where('status', 'pending')->count(),
            'total_accepted' => AiCopilotRecommendation::where('status', 'accepted')->count(),
            'total_dismissed' => AiCopilotRecommendation::where('status', 'dismissed')->count(),
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
            $results = $this->copilotService->generateRecommendations();

            if (empty($results)) {
                Toastr::info('AI Ops is currently disabled. Enable it in Settings to generate recommendations.');
                return redirect()->route('admin.urban-goodz.ai-copilot.settings');
            }

            $total = collect($results)->sum('count');

            $this->copilotService->notifyHighConfidenceRecommendations($results);

            $mode = $this->copilotService->getMode();
            if ($mode === 'full_low_risk_automation' || $mode === 'supervised_automation') {
                $autoCount = AiCopilotRecommendation::where('status', 'accepted')
                    ->where('created_at', '>=', now()->subMinute())
                    ->count();
                Toastr::success("AI Copilot processed {$total} items across " . count($results) . " categories ({$autoCount} auto-executed)");
            } else {
                Toastr::success("AI Copilot generated {$total} new recommendations across " . count($results) . " categories");
            }

            return redirect()->route('admin.urban-goodz.ai-copilot.index');
        } catch (\Exception $e) {
            Toastr::error('Failed to generate recommendations: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    public function accept(Request $request, $id)
    {
        $rec = $this->copilotService->accept((int) $id, auth('admin')->id(), $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found or already processed');
            return redirect()->back();
        }

        Toastr::success('Recommendation accepted');
        return redirect()->route('admin.urban-goodz.ai-copilot.index');
    }

    public function dismiss(Request $request, $id)
    {
        $rec = $this->copilotService->dismiss((int) $id, auth('admin')->id(), $request->admin_notes);

        if (!$rec) {
            Toastr::error('Recommendation not found or already processed');
            return redirect()->back();
        }

        Toastr::success('Recommendation dismissed');
        return redirect()->route('admin.urban-goodz.ai-copilot.index');
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
}
