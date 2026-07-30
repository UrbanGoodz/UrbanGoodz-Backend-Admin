<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzFinancialLedgerEntry;
use App\Models\UrbanGoodzFinancialRule;
use App\Models\UrbanGoodzReconciliationRun;
use App\Models\UrbanGoodzSettlementSnapshot;
use App\Services\UrbanGoodz\FinancialControl\FinancialControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UrbanGoodzFinancialControlController extends Controller
{
    public function __construct(private readonly FinancialControlService $financialControl) {}

    public function index(Request $request)
    {
        $this->authorizeView();

        $rules = UrbanGoodzFinancialRule::query()
            ->when($request->filled('family'), fn ($query) => $query->where('rule_family', $request->string('family')))
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderByDesc('version')
            ->paginate(25, ['*'], 'rules_page');

        $settlements = UrbanGoodzSettlementSnapshot::query()
            ->with('reconciliationRuns')
            ->latest('settled_at')
            ->paginate(25, ['*'], 'settlements_page');

        $stats = [
            'active_rules' => UrbanGoodzFinancialRule::where('is_active', true)->count(),
            'settled_cents' => (int) UrbanGoodzSettlementSnapshot::sum('shopper_total_cents'),
            'provider_proceeds_cents' => (int) UrbanGoodzSettlementSnapshot::sum('provider_proceeds_cents'),
            'driver_net_cents' => (int) UrbanGoodzSettlementSnapshot::sum('driver_net_cents'),
            'platform_net_cents' => (int) UrbanGoodzSettlementSnapshot::sum('platform_net_cents'),
            'out_of_balance' => UrbanGoodzSettlementSnapshot::where('reconciliation_status', 'out_of_balance')->count(),
        ];

        return view('admin-views.urban-goodz.financial-control.index', [
            'rules' => $rules,
            'settlements' => $settlements,
            'stats' => $stats,
            'families' => UrbanGoodzFinancialRule::FAMILIES,
            'calculationTypes' => UrbanGoodzFinancialRule::CALCULATION_TYPES,
            'scopes' => UrbanGoodzFinancialRule::SCOPES,
            'simulation' => session('financial_simulation'),
            'exampleScenarios' => $this->exampleScenarios(),
        ]);
    }

    public function storeRule(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validateRule($request);

        UrbanGoodzFinancialRule::create($data + [
            'rule_key' => (string) Str::uuid(),
            'version' => 1,
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return back()->with('success', translate('Financial rule created.'));
    }

    public function updateRule(Request $request, UrbanGoodzFinancialRule $financialRule)
    {
        $this->authorizeManage();
        $data = $this->validateRule($request);

        DB::transaction(function () use ($data, $financialRule) {
            $current = UrbanGoodzFinancialRule::query()
                ->whereKey($financialRule->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($current->is_active, 409, 'Only the current active rule version can be updated.');

            $nextVersion = (int) UrbanGoodzFinancialRule::where('rule_key', $current->rule_key)
                ->max('version') + 1;
            $current->update(['is_active' => false]);
            UrbanGoodzFinancialRule::create($data + [
                'rule_key' => $current->rule_key,
                'version' => $nextVersion,
                'supersedes_id' => $current->id,
                'created_by_admin_id' => auth('admin')->id(),
            ]);
        });

        return back()->with('success', translate('A new immutable financial rule version was published.'));
    }

    public function deactivateRule(UrbanGoodzFinancialRule $financialRule)
    {
        $this->authorizeManage();
        $financialRule->update([
            'is_active' => false,
            'change_reason' => trim(($financialRule->change_reason ? $financialRule->change_reason.' | ' : '').'Deactivated by Master Admin'),
        ]);

        return back()->with('success', translate('Financial rule deactivated.'));
    }

    public function ruleHistory(UrbanGoodzFinancialRule $financialRule)
    {
        $this->authorizeView();

        return response()->json([
            'rule_key' => $financialRule->rule_key,
            'versions' => UrbanGoodzFinancialRule::where('rule_key', $financialRule->rule_key)
                ->orderByDesc('version')
                ->get(),
        ]);
    }

    public function simulate(Request $request)
    {
        $this->authorizeView();
        $context = $this->validateContext($request);
        $result = $this->financialControl->simulate($context);

        if ($request->expectsJson()) {
            return response()->json(['data' => $result]);
        }

        return back()
            ->withInput()
            ->with('financial_simulation', $result)
            ->with('success', translate('Settlement simulation completed using live effective rules.'));
    }

    public function storeSettlement(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'source_type' => ['required', 'string', 'max:100'],
            'source_id' => ['required', 'string', 'max:191'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $context = $this->validateContext($request);
        $snapshot = $this->financialControl->settle(
            $data['source_type'],
            $data['source_id'],
            $context,
            $data['idempotency_key']
        );

        return $request->expectsJson()
            ? response()->json(['data' => $snapshot], 201)
            : back()->with('success', translate('Settlement snapshot recorded and reconciled.'));
    }

    public function showSettlement(UrbanGoodzSettlementSnapshot $settlement)
    {
        $this->authorizeView();

        return response()->json([
            'data' => $settlement->load(['ledgerEntries', 'reconciliationRuns']),
        ]);
    }

    public function refund(Request $request, UrbanGoodzSettlementSnapshot $settlement)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $snapshot = $this->financialControl->refund(
            $settlement,
            $data['amount_cents'],
            $data['reason'],
            $data['idempotency_key']
        );

        return $request->expectsJson()
            ? response()->json(['data' => $snapshot])
            : back()->with('success', translate('Refund reversal was posted and reconciled.'));
    }

    public function reverse(Request $request, UrbanGoodzSettlementSnapshot $settlement)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);
        $snapshot = $this->financialControl->reverse(
            $settlement,
            $data['reason'],
            $data['idempotency_key']
        );

        return $request->expectsJson()
            ? response()->json(['data' => $snapshot])
            : back()->with('success', translate('Settlement was fully reversed and reconciled.'));
    }

    public function reconcile(UrbanGoodzSettlementSnapshot $settlement)
    {
        $this->authorizeManage();
        $run = $this->financialControl->reconcile($settlement, auth('admin')->id());

        return request()->expectsJson()
            ? response()->json(['data' => $run])
            : back()->with('success', translate('Reconciliation completed: ').$run->status);
    }

    public function ledger(Request $request)
    {
        $this->authorizeView();
        $query = UrbanGoodzFinancialLedgerEntry::with('settlement')->latest();
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type'));
        }

        return response()->json(['data' => $query->paginate(100)]);
    }

    public function reconciliation(Request $request)
    {
        $this->authorizeView();
        $query = UrbanGoodzReconciliationRun::with('settlement')->latest('ran_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json(['data' => $query->paginate(100)]);
    }

    private function validateRule(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rule_family' => ['required', Rule::in(UrbanGoodzFinancialRule::FAMILIES)],
            'calculation_type' => ['required', Rule::in(UrbanGoodzFinancialRule::CALCULATION_TYPES)],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'rate_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'scope_type' => ['required', Rule::in(UrbanGoodzFinancialRule::SCOPES)],
            'scope_key' => ['nullable', 'string', 'max:191'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000000'],
            'visibility_roles' => ['nullable', 'array'],
            'visibility_roles.*' => ['string', Rule::in(['master_admin', 'admin', 'business', 'provider', 'driver', 'shopper'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'change_reason' => ['required', 'string', 'max:500'],
        ]);

        $data['amount_cents'] = (int) ($data['amount_cents'] ?? 0);
        $data['rate_basis_points'] = (int) ($data['rate_basis_points'] ?? 0);
        $data['scope_key'] = $data['scope_type'] === 'platform' ? null : ($data['scope_key'] ?? null);
        $data['visibility_roles'] = $data['visibility_roles'] ?? ['master_admin', 'admin'];
        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['calculation_type'] === 'percentage' && $data['rate_basis_points'] === 0) {
            abort(422, 'Percentage rules require positive basis points.');
        }
        if ($data['scope_type'] !== 'platform' && ! $data['scope_key']) {
            abort(422, 'Scoped rules require a scope key.');
        }

        return $data;
    }

    private function validateContext(Request $request): array
    {
        return $request->validate([
            'currency' => ['nullable', 'string', 'max:8'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'business_id' => ['nullable', 'integer', 'min:1'],
            'provider_id' => ['nullable', 'integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'service_type' => ['required', 'string', 'max:100'],
            'merchandise_subtotal_cents' => ['required', 'integer', 'min:0'],
            'delivery_charge_cents' => ['required', 'integer', 'min:0'],
            'miles_milli' => ['required', 'integer', 'min:0'],
            'package_count' => ['required', 'integer', 'min:0'],
            'stop_count' => ['required', 'integer', 'min:0'],
            'route_count' => ['required', 'integer', 'min:0'],
            'hours_minutes' => ['required', 'integer', 'min:0'],
            'return_count' => ['required', 'integer', 'min:0'],
            'exception_count' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function exampleScenarios(): array
    {
        return [
            'Retail delivery' => [
                'service_type' => 'marketplace_delivery',
                'merchandise_subtotal_cents' => 12500,
                'delivery_charge_cents' => 1299,
                'miles_milli' => 8400,
                'package_count' => 2,
                'stop_count' => 2,
                'route_count' => 1,
                'hours_minutes' => 45,
                'return_count' => 0,
                'exception_count' => 0,
            ],
            'Medical STAT route' => [
                'service_type' => 'medical_courier_stat',
                'merchandise_subtotal_cents' => 0,
                'delivery_charge_cents' => 8500,
                'miles_milli' => 27600,
                'package_count' => 4,
                'stop_count' => 5,
                'route_count' => 1,
                'hours_minutes' => 110,
                'return_count' => 1,
                'exception_count' => 1,
            ],
        ];
    }

    private function authorizeView(): void
    {
        abort_unless(
            Helpers::module_permission_check('urban_goodz_payments_view')
                || Helpers::module_permission_check('urban_goodz_financial_control_view')
                || Helpers::module_permission_check('urban_goodz_financial_control_manage'),
            403
        );
    }

    private function authorizeManage(): void
    {
        abort_unless(
            Helpers::module_permission_check('urban_goodz_financial_control_manage'),
            403
        );
    }
}
