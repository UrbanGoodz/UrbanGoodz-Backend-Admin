<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompensationRuleRequest;
use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use App\Models\UrbanGoodzCompensationRuleAudit;
use App\Services\UrbanGoodz\Compensation\CompensationContext;
use App\Services\UrbanGoodz\Compensation\CompensationSimulator;
use App\Services\UrbanGoodz\Compensation\Money;
use App\Services\UrbanGoodz\Compensation\RuleAdministrator;
use App\Services\UrbanGoodz\Compensation\RuleResolver;
use App\Support\Compensation\CompensationPermission;
use App\Support\Compensation\ComponentCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Driver Pricing & Compensation Command Center surface.
 *
 * Permission is checked in every action rather than relying solely on route
 * middleware, so the surface fails closed even if it is wired up without the
 * middleware attached.
 */
class UrbanGoodzCompensationController extends Controller
{
    public function __construct(
        private readonly RuleAdministrator $admin = new RuleAdministrator(),
        private readonly CompensationSimulator $simulator = new CompensationSimulator(),
        private readonly RuleResolver $resolver = new RuleResolver(),
    ) {
    }

    private function guard(string $permission): void
    {
        if (!CompensationPermission::allows(auth('admin')->user(), $permission)) {
            abort(403, 'You do not have permission to perform this compensation action.');
        }
    }

    /**
     * Navigation tabs, filtered to what this admin may actually open.
     */
    private function tabs(array $permissions): array
    {
        $definitions = [
            ['admin.urban-goodz.compensation.index', 'Rules Overview', CompensationPermission::VIEW_RULES],
            ['admin.urban-goodz.compensation.published', 'Published', CompensationPermission::VIEW_RULES],
            ['admin.urban-goodz.compensation.archived', 'Archived', CompensationPermission::VIEW_RULES],
            ['admin.urban-goodz.compensation.simulator', 'Simulator', CompensationPermission::SIMULATE],
            ['admin.urban-goodz.compensation.calculations', 'Calculation History', CompensationPermission::VIEW_CALCULATIONS],
            ['admin.urban-goodz.compensation.audit', 'Adjustment Audit', CompensationPermission::VIEW_CALCULATIONS],
            ['admin.urban-goodz.compensation.deficits', 'Deficit Alerts', CompensationPermission::VIEW_CALCULATIONS],
            ['admin.urban-goodz.compensation.splits', 'Split Configuration', CompensationPermission::VIEW_RULES],
        ];

        $tabs = [];

        foreach ($definitions as [$routeName, $label, $permission]) {
            if ($permissions[$permission] ?? false) {
                $tabs[] = ['route' => $routeName, 'label' => $label];
            }
        }

        return $tabs;
    }

    private function shared(array $data = []): array
    {
        $permissions = CompensationPermission::map(auth('admin')->user());

        return array_merge([
            'permissions' => $permissions,
            'tabs' => $this->tabs($permissions),
            'catalog' => ComponentCatalog::groups(),
            'vehicles' => ComponentCatalog::VEHICLES,
            'workTypes' => ComponentCatalog::WORK_TYPES,
            'serviceScopes' => ComponentCatalog::SERVICE_SCOPES,
            'splitParties' => ComponentCatalog::splitParties(),
            'roundingModes' => Money::modes(),
        ], $data);
    }

    // ---------------------------------------------------------------- 1. index

    public function index(Request $request)
    {
        $this->guard(CompensationPermission::VIEW_RULES);

        return view('admin-views.urban-goodz.compensation.index', $this->shared([
            'rules' => $this->query($request)->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'work_type', 'state']),
            'heading' => 'Rules Overview',
            'stateFilter' => null,
        ]));
    }

    // ------------------------------------------------- 6/7. published, archived

    public function published(Request $request)
    {
        $this->guard(CompensationPermission::VIEW_RULES);

        return view('admin-views.urban-goodz.compensation.index', $this->shared([
            'rules' => $this->query($request)->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
                ->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'work_type']),
            'heading' => 'Published Rules',
            'stateFilter' => UrbanGoodzCompensationRule::STATE_PUBLISHED,
        ]));
    }

    public function archived(Request $request)
    {
        $this->guard(CompensationPermission::VIEW_RULES);

        return view('admin-views.urban-goodz.compensation.index', $this->shared([
            'rules' => $this->query($request)->where('state', UrbanGoodzCompensationRule::STATE_ARCHIVED)
                ->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'work_type']),
            'heading' => 'Archived Rules',
            'stateFilter' => UrbanGoodzCompensationRule::STATE_ARCHIVED,
        ]));
    }

    private function query(Request $request)
    {
        return UrbanGoodzCompensationRule::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->string('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('rule_key', 'like', $term));
            })
            ->when($request->filled('work_type'), fn ($q) => $q->where('work_type', $request->string('work_type')))
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))
            ->orderByDesc('priority')
            ->orderBy('rule_key')
            ->orderByDesc('version');
    }

    // ----------------------------------------------------- 2/3. create and edit

    public function create()
    {
        $this->guard(CompensationPermission::CREATE_DRAFT);

        return view('admin-views.urban-goodz.compensation.form', $this->shared([
            'rule' => new UrbanGoodzCompensationRule([
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => Money::HALF_UP,
                'components' => [],
                'splits' => [],
            ]),
            'mode' => 'create',
        ]));
    }

    public function store(CompensationRuleRequest $request)
    {
        $this->guard(CompensationPermission::CREATE_DRAFT);

        try {
            $rule = $this->admin->createDraft(
                $request->ruleAttributes(),
                auth('admin')->id()
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['components' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.urban-goodz.compensation.show', $rule->id)
            ->with('success', "Draft v{$rule->version} created.");
    }

    public function edit(int $id)
    {
        $this->guard(CompensationPermission::EDIT_DRAFT);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);

        if ($rule->state !== UrbanGoodzCompensationRule::STATE_DRAFT) {
            return redirect()
                ->route('admin.urban-goodz.compensation.show', $rule->id)
                ->with('warning', 'Published and archived rules cannot be edited in place. Create a new version instead.');
        }

        return view('admin-views.urban-goodz.compensation.form', $this->shared([
            'rule' => $rule,
            'mode' => 'edit',
        ]));
    }

    public function update(CompensationRuleRequest $request, int $id)
    {
        $this->guard(CompensationPermission::EDIT_DRAFT);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);

        try {
            $updated = $this->admin->revise($rule, $request->ruleAttributes(), auth('admin')->id());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['components' => $e->getMessage()]);
        }

        $message = $updated->id === $rule->id
            ? 'Draft updated.'
            : "Published rule revised — draft v{$updated->version} created.";

        return redirect()
            ->route('admin.urban-goodz.compensation.show', $updated->id)
            ->with('success', $message);
    }

    /**
     * Explicitly branch a published rule into a new draft version.
     */
    public function newVersion(int $id)
    {
        $this->guard(CompensationPermission::CREATE_DRAFT);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);

        try {
            $draft = $this->admin->revise($rule, [], auth('admin')->id());
        } catch (Throwable $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.urban-goodz.compensation.edit', $draft->id)
            ->with('success', "Draft v{$draft->version} created from v{$rule->version}.");
    }

    // -------------------------------------------------------------- 4. detail

    public function show(int $id)
    {
        $this->guard(CompensationPermission::VIEW_RULES);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);

        return view('admin-views.urban-goodz.compensation.show', $this->shared([
            'rule' => $rule,
            'impact' => $this->impactSummary($rule),
            'conflicts' => $this->detectConflicts($rule),
            'audits' => UrbanGoodzCompensationRuleAudit::where('rule_id', $rule->id)
                ->orderByDesc('id')->limit(20)->get(),
        ]));
    }

    // ------------------------------------------------------------ 5. versions

    public function versions(string $ruleKey)
    {
        $this->guard(CompensationPermission::VIEW_RULES);

        $versions = UrbanGoodzCompensationRule::where('rule_key', $ruleKey)
            ->orderByDesc('version')->get();

        abort_if($versions->isEmpty(), 404);

        return view('admin-views.urban-goodz.compensation.versions', $this->shared([
            'ruleKey' => $ruleKey,
            'versions' => $versions,
            'audits' => $this->admin->history($ruleKey),
        ]));
    }

    // ------------------------------------------------------------ publishing

    public function publish(Request $request, int $id)
    {
        $this->guard(CompensationPermission::PUBLISH);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);

        $request->validate([
            'confirm' => ['required', 'accepted'],
            'effective_from' => ['nullable', 'date'],
        ], [
            'confirm.accepted' => 'You must confirm publication explicitly.',
        ]);

        if ($request->filled('effective_from')) {
            $rule->effective_from = Carbon::parse($request->input('effective_from'));
            $rule->save();
        }

        try {
            $published = $this->admin->publish($rule, auth('admin')->id());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.urban-goodz.compensation.show', $published->id)
            ->with('success', "Published v{$published->version}. Previous published version archived.");
    }

    public function archive(int $id)
    {
        $this->guard(CompensationPermission::ARCHIVE);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);
        $this->admin->archive($rule, auth('admin')->id());

        return back()->with('success', "Rule {$rule->rule_key} v{$rule->version} archived.");
    }

    public function toggleActive(int $id)
    {
        $this->guard(CompensationPermission::PUBLISH);

        $rule = UrbanGoodzCompensationRule::findOrFail($id);
        $this->admin->setActive($rule, !$rule->is_active, auth('admin')->id());

        return back()->with('success', $rule->fresh()->is_active ? 'Rule enabled.' : 'Rule disabled.');
    }

    // ------------------------------------------------------------ 8. simulator

    public function simulator()
    {
        $this->guard(CompensationPermission::SIMULATE);

        return view('admin-views.urban-goodz.compensation.simulator', $this->shared([
            'simulation' => null,
            'input' => [],
        ]));
    }

    /**
     * Run a simulation. This never persists a payout — CompensationSimulator
     * calls calculateWithRule() and never record().
     */
    public function simulate(Request $request)
    {
        $this->guard(CompensationPermission::SIMULATE);

        $validated = $request->validate([
            'work_type' => ['required', 'string', 'in:' . implode(',', UrbanGoodzCompensationRule::WORK_TYPES)],
            'service_scope' => ['nullable', 'string', 'max:64'],
            'market' => ['nullable', 'string', 'max:64'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'vehicle_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(ComponentCatalog::VEHICLES))],
            'miles' => ['nullable', 'numeric', 'min:0'],
            'loaded_miles' => ['nullable', 'numeric', 'min:0'],
            'deadhead_miles' => ['nullable', 'numeric', 'min:0'],
            'stops' => ['nullable', 'integer', 'min:0'],
            'packages' => ['nullable', 'integer', 'min:0'],
            'delivered_packages' => ['nullable', 'integer', 'min:0'],
            'minutes' => ['nullable', 'integer', 'min:0'],
            'wait_minutes' => ['nullable', 'integer', 'min:0'],
            'detention_minutes' => ['nullable', 'integer', 'min:0'],
            'layover_nights' => ['nullable', 'integer', 'min:0'],
            'extra_stops' => ['nullable', 'integer', 'min:0'],
            'customer_charge_cents' => ['nullable', 'integer', 'min:0'],
            'linehaul_cents' => ['nullable', 'integer', 'min:0'],
            'delivery_charge_cents' => ['nullable', 'integer', 'min:0'],
            'tolls_cents' => ['nullable', 'integer', 'min:0'],
            'reimbursements_cents' => ['nullable', 'integer', 'min:0'],
            'tips_cents' => ['nullable', 'integer', 'min:0'],
            'batched_orders' => ['nullable', 'integer', 'min:0'],
        ]);

        $flags = [];
        foreach ([
            'is_peak', 'is_after_hours', 'is_weekend', 'is_overnight', 'is_stat',
            'requires_chain_of_custody', 'requires_temperature_control', 'is_heavy_item',
            'driver_assist', 'is_cancelled', 'is_failed_delivery', 'is_failed_handoff',
            'is_redelivery', 'is_return_trip', 'is_return_specimen', 'route_completed',
        ] as $flag) {
            $flags[$flag] = $request->boolean($flag);
        }

        try {
            $ctx = CompensationContext::fromArray(array_merge($validated, $flags));
            $simulation = $this->simulator->simulate($ctx);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['simulator' => $e->getMessage()]);
        }

        return view('admin-views.urban-goodz.compensation.simulator', $this->shared([
            'simulation' => $simulation,
            'input' => array_merge($validated, $flags),
        ]));
    }

    // ------------------------------------------------------- 9. calculations

    public function calculations(Request $request)
    {
        $this->guard(CompensationPermission::VIEW_CALCULATIONS);

        $results = UrbanGoodzCompensationResult::query()
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->filled('rule_key'), fn ($q) => $q->where('rule_key', $request->string('rule_key')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin-views.urban-goodz.compensation.calculations', $this->shared([
            'results' => $results,
            'filters' => $request->only(['driver_id', 'rule_key', 'subject_type']),
        ]));
    }

    public function calculation(int $id)
    {
        $this->guard(CompensationPermission::VIEW_CALCULATIONS);

        return view('admin-views.urban-goodz.compensation.calculation', $this->shared([
            'result' => UrbanGoodzCompensationResult::findOrFail($id),
        ]));
    }

    // ------------------------------------------------------- 10. adjustment audit

    public function audit(Request $request)
    {
        $this->guard(CompensationPermission::VIEW_CALCULATIONS);

        $audits = UrbanGoodzCompensationRuleAudit::query()
            ->when($request->filled('rule_key'), fn ($q) => $q->where('rule_key', $request->string('rule_key')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin-views.urban-goodz.compensation.audit', $this->shared([
            'audits' => $audits,
            'filters' => $request->only(['rule_key', 'event']),
        ]));
    }

    // --------------------------------------------------------- 11. deficits

    public function deficits()
    {
        $this->guard(CompensationPermission::VIEW_CALCULATIONS);

        // A deficit means the rule paid out more than the job collected.
        $deficits = UrbanGoodzCompensationResult::query()
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->filter(fn ($r) => (bool) ($r->splits['is_deficit'] ?? false))
            ->values();

        return view('admin-views.urban-goodz.compensation.deficits', $this->shared([
            'deficits' => $deficits,
            'totalDeficitCents' => $deficits->sum(fn ($r) => abs((int) ($r->splits['platform_cents'] ?? 0))),
        ]));
    }

    // ----------------------------------------------------- 12. split config

    public function splits(Request $request)
    {
        $this->guard(CompensationPermission::VIEW_RULES);

        $rules = UrbanGoodzCompensationRule::query()
            ->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
            ->orderBy('work_type')->orderByDesc('priority')
            ->get();

        return view('admin-views.urban-goodz.compensation.splits', $this->shared([
            'rules' => $rules,
        ]));
    }

    // ------------------------------------------------------------- helpers

    /**
     * What publishing this rule would displace, and which scopes it covers.
     */
    private function impactSummary(UrbanGoodzCompensationRule $rule): array
    {
        $currentlyPublished = UrbanGoodzCompensationRule::where('rule_key', $rule->rule_key)
            ->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
            ->where('id', '!=', $rule->id)
            ->first();

        return [
            'would_archive' => $currentlyPublished
                ? ['id' => $currentlyPublished->id, 'version' => $currentlyPublished->version]
                : null,
            'scopes' => [
                'work_type' => $rule->work_type,
                'service_scope' => $rule->service_scope ?? 'any',
                'vehicle_scope' => $rule->vehicle_scope ?: ['any'],
                'market_scope' => $rule->market_scope ?: ['any'],
                'zone_id' => $rule->zone_id ?? 'any',
            ],
            'effective_from' => optional($rule->effective_from)->toDateTimeString(),
            'effective_to' => optional($rule->effective_to)->toDateTimeString(),
            'component_count' => count($rule->components ?? []),
        ];
    }

    /**
     * Other published rules whose scope overlaps this one, with the winner
     * determined by the same precedence the resolver uses.
     */
    private function detectConflicts(UrbanGoodzCompensationRule $rule): array
    {
        $others = UrbanGoodzCompensationRule::where('work_type', $rule->work_type)
            ->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
            ->where('is_active', true)
            ->where('rule_key', '!=', $rule->rule_key)
            ->get();

        $conflicts = [];

        foreach ($others as $other) {
            if (!$this->scopesOverlap($rule, $other)) {
                continue;
            }

            $rank = [$other->priority, $other->specificity(), $other->version]
                <=> [$rule->priority, $rule->specificity(), $rule->version];

            $conflicts[] = [
                'id' => $other->id,
                'rule_key' => $other->rule_key,
                'name' => $other->name,
                'version' => $other->version,
                'priority' => $other->priority,
                'specificity' => $other->specificity(),
                'outcome' => $rank > 0 ? 'wins over this rule' : ($rank < 0 ? 'loses to this rule' : 'ambiguous tie'),
            ];
        }

        return $conflicts;
    }

    private function scopesOverlap(UrbanGoodzCompensationRule $a, UrbanGoodzCompensationRule $b): bool
    {
        $scalarOverlap = fn ($x, $y) => $x === null || $y === null || $x === $y;

        if (!$scalarOverlap($a->service_scope, $b->service_scope)) {
            return false;
        }

        if (!$scalarOverlap($a->zone_id, $b->zone_id)) {
            return false;
        }

        foreach (['vehicle_scope', 'market_scope'] as $listScope) {
            $x = $a->{$listScope};
            $y = $b->{$listScope};

            if (!empty($x) && !empty($y) && array_intersect($x, $y) === []) {
                return false;
            }
        }

        return true;
    }
}
