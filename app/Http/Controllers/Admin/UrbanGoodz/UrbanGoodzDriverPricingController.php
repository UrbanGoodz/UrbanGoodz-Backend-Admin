<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzDriverPricingPolicy;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Models\UrbanGoodzActivityLog;
use App\Models\Zone;
use App\Models\DMVehicle;
use App\Services\UrbanGoodz\UrbanGoodzDriverPricingService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverPricingController extends Controller
{
    private const POLICY_TYPES = [
        'marketplace_delivery',
        'courier_parcel',
        'business_routes',
        'business_multi_stop',
        'dedicated_routes',
        'logistics_loads',
        'medical_courier',
        'order_anywhere',
        'returns_exceptions',
    ];

    private const PAYOUT_MODELS = [
        'fixed_payout',
        'base_mileage',
        'base_mileage_time',
        'per_stop',
        'per_package',
        'percentage_of_revenue',
        'dynamic_ai',
        'manual_quote',
    ];

    public function __construct(
        protected UrbanGoodzDriverPricingService $pricingService
    ) {}

    public function index(Request $request)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_view')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        // 1. Fetch Pricing Policies
        $policiesQuery = UrbanGoodzDriverPricingPolicy::with('zone')->latest();
        $typeFilter = $request->query('type');
        if ($typeFilter && in_array($typeFilter, self::POLICY_TYPES, true)) {
            $policiesQuery->where('policy_type', $typeFilter);
        }
        $policies = $policiesQuery->get();

        // 2. Fetch Payout Requests (Reused from DedicatedRouteController)
        $payouts = UrbanGoodzDriverPayoutRequest::with(['driver', 'approver'])->latest()->paginate(25, ['*'], 'payout_page');
        $payoutStats = [
            'total_pending' => UrbanGoodzDriverPayoutRequest::where('status', 'pending')->sum('requested_amount'),
            'total_paid' => UrbanGoodzDriverPayoutRequest::where('status', 'paid')->sum('net_amount'),
            'total_fees' => UrbanGoodzDriverPayoutRequest::where('status', 'paid')->sum('instant_fee'),
            'pending_count' => UrbanGoodzDriverPayoutRequest::where('status', 'pending')->count(),
        ];

        // 3. Fetch Driver Earnings (Reused from DedicatedRouteController)
        $earnings = UrbanGoodzDriverEarning::with(['driver', 'package', 'route'])->latest()->paginate(25, ['*'], 'earning_page');
        $earningStats = [
            'pending' => UrbanGoodzDriverEarning::where('status', 'pending')->sum('amount'),
            'approved' => UrbanGoodzDriverEarning::where('status', 'approved')->sum('amount'),
            'paid' => UrbanGoodzDriverEarning::where('status', 'paid')->sum('amount'),
            'total' => UrbanGoodzDriverEarning::sum('amount'),
        ];

        $zones = Zone::all();
        $vehicles = DMVehicle::active()->get();

        return view('admin-views.urban-goodz.driver-pricing.index', compact(
            'policies', 'payouts', 'payoutStats', 'earnings', 'earningStats', 'zones', 'vehicles'
        ));
    }

    public function create()
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $zones = Zone::all();
        $vehicles = DMVehicle::active()->get();
        return view('admin-views.urban-goodz.driver-pricing.create', compact('zones', 'vehicles'));
    }

    public function store(Request $request)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $validated = $request->validate([
            'policy_type' => ['required', 'string', Rule::in(self::POLICY_TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'payout_model' => ['required', 'string', Rule::in(self::PAYOUT_MODELS)],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'base_fare' => ['nullable', 'numeric', 'min:0'],
            'rate_per_mile' => ['nullable', 'numeric', 'min:0'],
            'rate_per_minute' => ['nullable', 'numeric', 'min:0'],
            'rate_per_stop' => ['nullable', 'numeric', 'min:0'],
            'rate_per_package' => ['nullable', 'numeric', 'min:0'],
            'revenue_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            
            'dynamic_pricing_enabled' => ['nullable', 'boolean'],
            'recommendation_only' => ['nullable', 'boolean'],
            'auto_apply_within_limits' => ['nullable', 'boolean'],
            'dispatcher_approval_required' => ['nullable', 'boolean'],
            'admin_approval_required' => ['nullable', 'boolean'],
            'live_pricing_enabled' => ['nullable', 'boolean'],
            'sandbox_pricing_enabled' => ['nullable', 'boolean'],
            
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'vehicle_multipliers' => ['nullable', 'array'],
            'urgency_premium' => ['nullable', 'numeric', 'min:0'],
            'deadhead_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'waiting_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'return_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'exception_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'minimum_payout' => ['nullable', 'numeric', 'min:0'],
            'maximum_payout' => ['nullable', 'numeric', 'min:0'],
            'minimum_margin' => ['nullable', 'numeric', 'min:0', 'max:100'],
            
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $validated['policy_type'] = $validated['policy_type'] === 'business_multi_stop'
            ? 'business_routes'
            : $validated['policy_type'];

        // Convert checkbox variables
        $validated['dynamic_pricing_enabled'] = $request->boolean('dynamic_pricing_enabled');
        $validated['recommendation_only'] = $request->boolean('recommendation_only');
        $validated['auto_apply_within_limits'] = $request->boolean('auto_apply_within_limits');
        $validated['dispatcher_approval_required'] = $request->boolean('dispatcher_approval_required');
        $validated['admin_approval_required'] = $request->boolean('admin_approval_required');
        $validated['live_pricing_enabled'] = $request->boolean('live_pricing_enabled');
        $validated['sandbox_pricing_enabled'] = $request->boolean('sandbox_pricing_enabled', true);

        // Deduplicate zone policy (one policy type per zone)
        $exists = UrbanGoodzDriverPricingPolicy::where('policy_type', $validated['policy_type'])
            ->where('zone_id', $validated['zone_id'] ?? null)
            ->exists();
        if ($exists) {
            Toastr::error(translate('A policy already exists for this service type and zone. Please edit the existing policy instead.'));
            return redirect()->back()->withInput();
        }

        $policy = UrbanGoodzDriverPricingPolicy::create($validated);

        // Log audit
        $this->pricingService->logPolicyActivity($policy, 'created', "Driver pricing policy '{$policy->name}' created.");

        Toastr::success(translate('Pricing policy created successfully'));
        return redirect()->route('admin.urban-goodz.driver-pricing.index');
    }

    public function edit($id)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $policy = UrbanGoodzDriverPricingPolicy::findOrFail($id);
        $zones = Zone::all();
        $vehicles = DMVehicle::active()->get();
        return view('admin-views.urban-goodz.driver-pricing.edit', compact('policy', 'zones', 'vehicles'));
    }

    public function update(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $policy = UrbanGoodzDriverPricingPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payout_model' => ['required', 'string', Rule::in(self::PAYOUT_MODELS)],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'base_fare' => ['nullable', 'numeric', 'min:0'],
            'rate_per_mile' => ['nullable', 'numeric', 'min:0'],
            'rate_per_minute' => ['nullable', 'numeric', 'min:0'],
            'rate_per_stop' => ['nullable', 'numeric', 'min:0'],
            'rate_per_package' => ['nullable', 'numeric', 'min:0'],
            'revenue_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            
            'dynamic_pricing_enabled' => ['nullable', 'boolean'],
            'recommendation_only' => ['nullable', 'boolean'],
            'auto_apply_within_limits' => ['nullable', 'boolean'],
            'dispatcher_approval_required' => ['nullable', 'boolean'],
            'admin_approval_required' => ['nullable', 'boolean'],
            'live_pricing_enabled' => ['nullable', 'boolean'],
            'sandbox_pricing_enabled' => ['nullable', 'boolean'],
            
            'vehicle_multipliers' => ['nullable', 'array'],
            'urgency_premium' => ['nullable', 'numeric', 'min:0'],
            'deadhead_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'waiting_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'return_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'exception_pay_rate' => ['nullable', 'numeric', 'min:0'],
            'minimum_payout' => ['nullable', 'numeric', 'min:0'],
            'maximum_payout' => ['nullable', 'numeric', 'min:0'],
            'minimum_margin' => ['nullable', 'numeric', 'min:0', 'max:100'],
            
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $validated['dynamic_pricing_enabled'] = $request->boolean('dynamic_pricing_enabled');
        $validated['recommendation_only'] = $request->boolean('recommendation_only');
        $validated['auto_apply_within_limits'] = $request->boolean('auto_apply_within_limits');
        $validated['dispatcher_approval_required'] = $request->boolean('dispatcher_approval_required');
        $validated['admin_approval_required'] = $request->boolean('admin_approval_required');
        $validated['live_pricing_enabled'] = $request->boolean('live_pricing_enabled');
        $validated['sandbox_pricing_enabled'] = $request->boolean('sandbox_pricing_enabled', true);

        $oldValues = $policy->only(array_keys($validated));

        $policy->update($validated);

        // Log audit
        $this->pricingService->logPolicyActivity($policy, 'updated', "Driver pricing policy '{$policy->name}' updated.", $oldValues);

        Toastr::success(translate('Pricing policy updated successfully'));
        return redirect()->route('admin.urban-goodz.driver-pricing.index');
    }

    public function destroy($id)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $policy = UrbanGoodzDriverPricingPolicy::findOrFail($id);
        $oldValues = $policy->toArray();
        $policy->delete();

        // Log audit
        UrbanGoodzActivityLog::create([
            'loggable_type' => UrbanGoodzDriverPricingPolicy::class,
            'loggable_id' => $id,
            'event' => 'deleted',
            'description' => "Driver pricing policy '{$oldValues['name']}' deleted.",
            'causer_type' => 'App\Models\Admin',
            'causer_id' => auth('admin')->id(),
            'old_values' => $oldValues,
        ]);

        Toastr::success(translate('Pricing policy deleted successfully'));
        return redirect()->route('admin.urban-goodz.driver-pricing.index');
    }

    /**
     * Display version audit history log for a policy.
     */
    public function history($id)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_view')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $policy = UrbanGoodzDriverPricingPolicy::findOrFail($id);
        $logs = UrbanGoodzActivityLog::where('loggable_type', UrbanGoodzDriverPricingPolicy::class)
            ->where('loggable_id', $id)
            ->latest()
            ->get();

        return view('admin-views.urban-goodz.driver-pricing.history', compact('policy', 'logs'));
    }

    /**
     * Rollback a pricing policy to a previous version log state.
     */
    public function rollback(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_driver_payouts_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $policy = UrbanGoodzDriverPricingPolicy::findOrFail($id);
        $logId = $request->input('log_id');
        $log = UrbanGoodzActivityLog::findOrFail($logId);

        if ($log->loggable_type !== UrbanGoodzDriverPricingPolicy::class || (int) $log->loggable_id !== (int) $id) {
            Toastr::error(translate('Invalid rollback log mapping.'));
            return redirect()->back();
        }

        $oldValues = $policy->toArray();
        // Extract restoration values (either old_values or new_values from the log depending on the target state)
        $restoredValues = $log->old_values ?? $log->new_values;

        if (empty($restoredValues)) {
            Toastr::error(translate('No historical data found in this log to restore.'));
            return redirect()->back();
        }

        // Apply subset of columns
        $fillable = $policy->getFillable();
        $restoredSubset = array_intersect_key($restoredValues, array_flip($fillable));

        $policy->update($restoredSubset);

        // Log rollback event
        UrbanGoodzActivityLog::create([
            'loggable_type' => UrbanGoodzDriverPricingPolicy::class,
            'loggable_id' => $policy->id,
            'event' => 'rollback',
            'description' => "Rolled back policy '{$policy->name}' to state from log #{$logId}.",
            'causer_type' => 'App\Models\Admin',
            'causer_id' => auth('admin')->id(),
            'old_values' => $oldValues,
            'new_values' => $restoredSubset,
        ]);

        Toastr::success(translate('Pricing policy rolled back successfully'));
        return redirect()->route('admin.urban-goodz.driver-pricing.index');
    }
}
