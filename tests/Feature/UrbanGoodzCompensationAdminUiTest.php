<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use App\Models\UrbanGoodzCompensationRuleAudit;
use App\Services\UrbanGoodz\Compensation\CompensationContext;
use App\Services\UrbanGoodz\Compensation\CompensationEngine;
use App\Services\UrbanGoodz\Compensation\RuleAdministrator;
use App\Support\Compensation\CompensationPermission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Driver Pricing & Compensation admin surface.
 *
 * The routes live in routes/admin/urban_goodz_compensation.php and are NOT yet
 * required from routes/admin.php — that file belongs to the integration
 * authority. The suite registers the route file itself so the controller,
 * permissions, validation and views are exercised over real HTTP.
 */
class UrbanGoodzCompensationAdminUiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function () {
            // Exercise this surface's own permission enforcement. Production keeps
            // the full ['admin'] stack; its 2FA and module gates have their own
            // suites. 'web' is retained here because session state and the shared
            // $errors bag are part of what these pages render.
            config()->set('urban_goodz_compensation.route_middleware', ['web']);

            // layouts.admin.app pulls in the full 6amMart chrome, including a
            // sidebar partial resolved from runtime module state that does not
            // exist in an isolated request. Render the pages standalone so the
            // assertions are about this surface, not the surrounding chrome.
            config()->set('urban_goodz_compensation.layout', 'admin-views.urban-goodz.compensation._standalone_layout');

            Route::group(['namespace' => 'App\Http\Controllers'], function () {
                require base_path('routes/admin/urban_goodz_compensation.php');
            });

            // Routes added after the collection has been used are present but not
            // resolvable by name until the lookup maps are rebuilt.
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->refreshActionLookups();
        });

        parent::setUp();

        if (!Route::has('admin.urban-goodz.compensation.index')) {
            $this->fail('Compensation routes failed to register for the test run.');
        }
    }

    private function superAdmin(): Admin
    {
        return Admin::create([
            'f_name' => 'Super',
            'l_name' => 'Admin',
            'email' => 'super-' . uniqid() . '@urban-goodz.test',
            'password' => bcrypt('secret-password'),
            'role_id' => 1,
            'status' => 1,
        ]);
    }

    /** @param array<int,string> $permissions */
    private function restrictedAdmin(array $permissions): Admin
    {
        $role = AdminRole::create([
            'name' => 'comp-role-' . uniqid(),
            'modules' => json_encode($permissions),
            'status' => 1,
        ]);

        return Admin::create([
            'f_name' => 'Limited',
            'l_name' => 'Operator',
            'email' => 'limited-' . uniqid() . '@urban-goodz.test',
            'password' => bcrypt('secret-password'),
            'role_id' => $role->id,
            'status' => 1,
        ]);
    }

    private function draft(array $overrides = []): UrbanGoodzCompensationRule
    {
        return (new RuleAdministrator())->createDraft(array_merge([
            'rule_key' => 'ui.' . uniqid(),
            'name' => 'UI rule',
            'work_type' => 'delivery',
            'priority' => 0,
            'rounding_mode' => 'half_up',
            'components' => ['flat' => ['amount_cents' => 1000]],
            'splits' => [],
        ], $overrides), 1);
    }

    // ------------------------------------------------------------- access

    public function test_unauthenticated_visitor_cannot_reach_the_surface(): void
    {
        // With the admin middleware stripped for this suite, the controller's own
        // guard is what must refuse an unauthenticated caller — it fails closed
        // rather than relying on the route stack.
        $this->get(route('admin.urban-goodz.compensation.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_the_rules_overview(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.index'))
            ->assertOk()
            ->assertSee('Driver Pricing', false)
            ->assertSee('Rules Overview', false);
    }

    public function test_admin_without_view_permission_is_denied(): void
    {
        $this->actingAs($this->restrictedAdmin([]), 'admin')
            ->get(route('admin.urban-goodz.compensation.index'))
            ->assertForbidden();
    }

    public function test_view_permission_does_not_grant_publish(): void
    {
        $admin = $this->restrictedAdmin([CompensationPermission::VIEW_RULES]);
        $rule = $this->draft();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.urban-goodz.compensation.publish', $rule->id), ['confirm' => 1])
            ->assertForbidden();

        $this->assertSame(
            UrbanGoodzCompensationRule::STATE_DRAFT,
            $rule->fresh()->state,
            'A denied publish must not change rule state.'
        );
    }

    public function test_simulate_permission_is_required_for_the_simulator(): void
    {
        $this->actingAs($this->restrictedAdmin([CompensationPermission::VIEW_RULES]), 'admin')
            ->get(route('admin.urban-goodz.compensation.simulator'))
            ->assertForbidden();

        $this->actingAs($this->restrictedAdmin([CompensationPermission::SIMULATE]), 'admin')
            ->get(route('admin.urban-goodz.compensation.simulator'))
            ->assertOk();
    }

    public function test_calculation_history_requires_its_own_permission(): void
    {
        $this->actingAs($this->restrictedAdmin([CompensationPermission::VIEW_RULES]), 'admin')
            ->get(route('admin.urban-goodz.compensation.calculations'))
            ->assertForbidden();
    }

    // --------------------------------------------------------- draft creation

    public function test_super_admin_can_create_a_draft(): void
    {
        $key = 'ui.create.' . uniqid();

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => $key,
                'name' => 'Standard marketplace delivery',
                'work_type' => 'delivery',
                'priority' => 10,
                'rounding_mode' => 'half_up',
                'components' => [
                    'base' => ['amount_cents' => 500],
                    'per_mile' => ['rate_cents' => 60],
                ],
                'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['percent' => 10]],
            ])
            ->assertRedirect();

        $rule = UrbanGoodzCompensationRule::where('rule_key', $key)->first();

        $this->assertNotNull($rule);
        $this->assertSame(1, $rule->version);
        $this->assertSame(UrbanGoodzCompensationRule::STATE_DRAFT, $rule->state);
        $this->assertSame(500, $rule->components['base']['amount_cents']);
    }

    public function test_creating_a_draft_writes_an_audit_entry(): void
    {
        $key = 'ui.audit.' . uniqid();

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => $key,
                'name' => 'Audited rule',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'half_up',
                'components' => ['flat' => ['amount_cents' => 900]],
            ]);

        $this->assertDatabaseHas('urban_goodz_compensation_rule_audits', [
            'rule_key' => $key,
            'event' => 'created',
        ]);
    }

    // ------------------------------------------------------------ validation

    public function test_negative_component_values_are_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => 'ui.negative',
                'name' => 'Negative',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'half_up',
                'components' => ['per_mile' => ['rate_cents' => -50]],
            ])
            ->assertSessionHasErrors('components.per_mile.rate_cents');

        $this->assertDatabaseMissing('urban_goodz_compensation_rules', ['rule_key' => 'ui.negative']);
    }

    public function test_split_percentages_over_one_hundred_are_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => 'ui.oversplit',
                'name' => 'Oversplit',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'half_up',
                'components' => ['flat' => ['amount_cents' => 100]],
                'splits' => [
                    'dispatcher' => ['percent' => 60],
                    'creator' => ['percent' => 50],
                ],
            ])
            ->assertSessionHasErrors('splits');
    }

    public function test_negative_split_percentage_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => 'ui.negsplit',
                'name' => 'Negative split',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'half_up',
                'components' => ['flat' => ['amount_cents' => 100]],
                'splits' => ['dispatcher' => ['percent' => -5]],
            ])
            ->assertSessionHasErrors('splits.dispatcher.percent');
    }

    public function test_unknown_component_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => 'ui.unknown',
                'name' => 'Unknown component',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'half_up',
                'components' => ['per_mile_typo' => ['rate_cents' => 50]],
            ])
            ->assertSessionHasErrors('components.per_mile_typo');
    }

    public function test_minimum_above_maximum_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => 'ui.range',
                'name' => 'Bad range',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'half_up',
                'components' => ['flat' => ['amount_cents' => 100]],
                'minimum_payout_cents' => 5000,
                'maximum_payout_cents' => 1000,
            ])
            ->assertSessionHasErrors('minimum_payout_cents');
    }

    public function test_invalid_rounding_mode_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.store'), [
                'rule_key' => 'ui.rounding',
                'name' => 'Bad rounding',
                'work_type' => 'delivery',
                'priority' => 0,
                'rounding_mode' => 'banker_special',
                'components' => ['flat' => ['amount_cents' => 100]],
            ])
            ->assertSessionHasErrors('rounding_mode');
    }

    // ------------------------------------------------------------ publishing

    public function test_publishing_requires_explicit_confirmation(): void
    {
        $rule = $this->draft();

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.publish', $rule->id), [])
            ->assertSessionHasErrors('confirm');

        $this->assertSame(UrbanGoodzCompensationRule::STATE_DRAFT, $rule->fresh()->state);
    }

    public function test_confirmed_publish_publishes_and_audits(): void
    {
        $rule = $this->draft();

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.publish', $rule->id), ['confirm' => 1])
            ->assertRedirect();

        $this->assertSame(UrbanGoodzCompensationRule::STATE_PUBLISHED, $rule->fresh()->state);
        $this->assertDatabaseHas('urban_goodz_compensation_rule_audits', [
            'rule_key' => $rule->rule_key,
            'event' => 'published',
        ]);
    }

    public function test_published_rule_cannot_be_edited_in_place(): void
    {
        $rule = $this->draft();
        (new RuleAdministrator())->publish($rule, 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.edit', $rule->id))
            ->assertRedirect(route('admin.urban-goodz.compensation.show', $rule->id))
            ->assertSessionHas('warning');
    }

    public function test_revising_a_published_rule_creates_a_new_draft_version(): void
    {
        $rule = $this->draft();
        (new RuleAdministrator())->publish($rule, 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.new-version', $rule->id))
            ->assertRedirect();

        $versions = UrbanGoodzCompensationRule::where('rule_key', $rule->rule_key)->orderBy('version')->get();

        $this->assertCount(2, $versions);
        $this->assertSame(UrbanGoodzCompensationRule::STATE_PUBLISHED, $versions[0]->state);
        $this->assertSame(UrbanGoodzCompensationRule::STATE_DRAFT, $versions[1]->state);
    }

    public function test_versions_page_lists_every_version(): void
    {
        $rule = $this->draft();
        $administrator = new RuleAdministrator();
        $administrator->publish($rule, 1);
        $administrator->revise($rule->fresh(), ['name' => 'Revised'], 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.versions', $rule->rule_key))
            ->assertOk()
            ->assertSee('v1', false)
            ->assertSee('v2', false);
    }

    // ------------------------------------------------------ conflict detection

    public function test_rule_detail_reports_an_overlapping_published_rule(): void
    {
        $administrator = new RuleAdministrator();

        $existing = $this->draft(['rule_key' => 'conflict.existing', 'name' => 'Existing rule', 'priority' => 90]);
        $administrator->publish($existing, 1);

        $candidate = $this->draft(['rule_key' => 'conflict.candidate', 'name' => 'Candidate rule', 'priority' => 10]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.show', $candidate->id))
            ->assertOk()
            ->assertSee('Existing rule', false)
            ->assertSee('wins over this rule', false);
    }

    public function test_impact_summary_names_the_version_that_would_be_archived(): void
    {
        $administrator = new RuleAdministrator();
        $rule = $this->draft(['rule_key' => 'impact.rule']);
        $administrator->publish($rule, 1);
        $draft = $administrator->revise($rule->fresh(), ['name' => 'v2'], 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.show', $draft->id))
            ->assertOk()
            ->assertSee('Publishing will archive currently published', false);
    }

    // -------------------------------------------------------------- simulator

    public function test_simulator_shows_components_splits_and_final_amount(): void
    {
        $rule = $this->draft([
            'rule_key' => 'sim.ui',
            'name' => 'Simulator rule',
            'components' => ['base' => ['amount_cents' => 500], 'per_mile' => ['rate_cents' => 60]],
            'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['percent' => 10]],
        ]);
        (new RuleAdministrator())->publish($rule, 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.simulate'), [
                'work_type' => 'delivery',
                'miles' => 10,
                'customer_charge_cents' => 5000,
            ])
            ->assertOk()
            ->assertSee('Simulator rule', false)
            ->assertSee('Base pay', false)
            ->assertSee('Mileage', false)
            ->assertSee('11.00', false)     // 500 + 600 driver amount
            ->assertSee('Why this rule won', false);
    }

    public function test_simulator_never_persists_a_payout(): void
    {
        $rule = $this->draft(['rule_key' => 'sim.nopersist']);
        (new RuleAdministrator())->publish($rule, 1);

        $before = UrbanGoodzCompensationResult::count();

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.simulate'), [
                'work_type' => 'delivery',
                'miles' => 25,
                'customer_charge_cents' => 9000,
            ])
            ->assertOk();

        $this->assertSame($before, UrbanGoodzCompensationResult::count());
    }

    public function test_simulator_warns_on_a_deficit(): void
    {
        $rule = $this->draft([
            'rule_key' => 'sim.deficit',
            'components' => ['flat' => ['amount_cents' => 9000]],
            'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['percent' => 20]],
        ]);
        (new RuleAdministrator())->publish($rule, 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.simulate'), [
                'work_type' => 'delivery',
                'customer_charge_cents' => 10000,
            ])
            ->assertOk()
            ->assertSee('Deficit', false)
            ->assertSee('pays out more than the job collects', false);
    }

    public function test_simulator_displays_pass_through_separately_from_earnings(): void
    {
        $rule = $this->draft([
            'rule_key' => 'sim.passthrough',
            'components' => [
                'per_mile' => ['rate_cents' => 50],
                'tolls' => ['reimburse' => 1],
            ],
            'minimum_payout_cents' => 1200,
        ]);
        (new RuleAdministrator())->publish($rule, 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.simulate'), [
                'work_type' => 'delivery',
                'miles' => 2,
                'tolls_cents' => 450,
            ])
            ->assertOk()
            ->assertSee('Pass-through', false)
            ->assertSee('16.50', false);   // 1200 clamp + 450 tolls
    }

    public function test_simulator_rejects_negative_input(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.simulate'), [
                'work_type' => 'delivery',
                'miles' => -10,
            ])
            ->assertSessionHasErrors('miles');
    }

    public function test_simulator_reports_when_no_rule_matches(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.urban-goodz.compensation.simulate'), [
                'work_type' => 'medical',
            ])
            ->assertOk()
            ->assertSee('No rule matched', false);
    }

    // --------------------------------------------- calculations and immutability

    public function test_calculation_detail_marks_finalized_results_immutable(): void
    {
        $rule = $this->draft(['rule_key' => 'calc.final']);
        (new RuleAdministrator())->publish($rule, 1);

        $engine = new CompensationEngine();
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'customer_charge_cents' => 4000,
            'subject_type' => 'order',
            'subject_id' => 4321,
            'driver_id' => 8,
        ]);
        $result = $engine->record($engine->calculateWithRule($rule->fresh(), $ctx), $ctx, true);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.calculation', $result->id))
            ->assertOk()
            ->assertSee('final', false)
            ->assertSee('sealed', false);
    }

    public function test_deficit_alerts_page_lists_deficit_results(): void
    {
        $rule = $this->draft([
            'rule_key' => 'calc.deficit',
            'components' => ['flat' => ['amount_cents' => 9000]],
            'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['percent' => 20]],
        ]);
        (new RuleAdministrator())->publish($rule, 1);

        $engine = new CompensationEngine();
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'customer_charge_cents' => 10000,
            'subject_type' => 'order',
            'subject_id' => 9911,
        ]);
        $engine->record($engine->calculateWithRule($rule->fresh(), $ctx), $ctx, true);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.deficits'))
            ->assertOk()
            ->assertSee('9911', false)
            ->assertSee('deficit calculation', false);
    }

    // ---------------------------------------------------------- other pages

    public function test_audit_page_renders_entries(): void
    {
        $rule = $this->draft(['rule_key' => 'page.audit']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.audit', ['rule_key' => $rule->rule_key]))
            ->assertOk()
            ->assertSee('created', false);
    }

    public function test_split_configuration_page_lists_published_rules(): void
    {
        $rule = $this->draft([
            'rule_key' => 'page.splits',
            'name' => 'Split display rule',
            'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['percent' => 12.5]],
        ]);
        (new RuleAdministrator())->publish($rule, 1);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.urban-goodz.compensation.splits'))
            ->assertOk()
            ->assertSee('Split display rule', false)
            ->assertSee('12.5', false);
    }

    public function test_published_and_archived_listings_filter_by_state(): void
    {
        $administrator = new RuleAdministrator();
        $published = $this->draft(['rule_key' => 'page.pub', 'name' => 'Published listing rule']);
        $administrator->publish($published, 1);

        $archived = $this->draft(['rule_key' => 'page.arch', 'name' => 'Archived listing rule']);
        $administrator->archive($archived, 1);

        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.urban-goodz.compensation.published'))
            ->assertOk()
            ->assertSee('Published listing rule', false)
            ->assertDontSee('Archived listing rule', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.urban-goodz.compensation.archived'))
            ->assertOk()
            ->assertSee('Archived listing rule', false);
    }
}
