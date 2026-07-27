<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use App\Services\UrbanGoodz\Compensation\CompensationContext;
use App\Services\UrbanGoodz\Compensation\CompensationEngine;
use App\Services\UrbanGoodz\Compensation\CompensationSimulator;
use App\Services\UrbanGoodz\Compensation\RuleAdministrator;
use App\Services\UrbanGoodz\Compensation\RuleResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Rule resolution precedence, versioning, audit history and immutability.
 */
class UrbanGoodzCompensationRuleLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): RuleAdministrator
    {
        return new RuleAdministrator();
    }

    private function publish(array $attributes): UrbanGoodzCompensationRule
    {
        $admin = $this->admin();
        $rule = $admin->createDraft(array_merge([
            'rule_key' => 'k.' . uniqid(),
            'name' => 'Rule',
            'work_type' => 'delivery',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ], $attributes), 1);

        return $admin->publish($rule, 1);
    }

    private function ctx(array $overrides = []): CompensationContext
    {
        return CompensationContext::fromArray(array_merge([
            'work_type' => 'delivery',
        ], $overrides));
    }

    // ------------------------------------------------------------- precedence

    public function test_higher_priority_rule_wins(): void
    {
        $this->publish([
            'rule_key' => 'prec.low', 'priority' => 1,
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);
        $this->publish([
            'rule_key' => 'prec.high', 'priority' => 50,
            'components' => ['flat' => ['amount_cents' => 2000]],
        ]);

        $winner = (new RuleResolver())->resolve($this->ctx());

        $this->assertSame('prec.high', $winner->rule_key);
    }

    public function test_specificity_breaks_a_priority_tie(): void
    {
        $this->publish([
            'rule_key' => 'spec.general', 'priority' => 10,
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);
        $this->publish([
            'rule_key' => 'spec.zoned', 'priority' => 10, 'zone_id' => 77,
            'components' => ['flat' => ['amount_cents' => 3000]],
        ]);

        $winner = (new RuleResolver())->resolve($this->ctx(['zone_id' => 77]));

        $this->assertSame('spec.zoned', $winner->rule_key);
    }

    public function test_scope_mismatch_excludes_a_rule_entirely(): void
    {
        $this->publish([
            'rule_key' => 'scope.van', 'priority' => 99,
            'vehicle_scope' => ['cargo_van'],
            'components' => ['flat' => ['amount_cents' => 9999]],
        ]);
        $this->publish([
            'rule_key' => 'scope.any', 'priority' => 1,
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);

        $winner = (new RuleResolver())->resolve($this->ctx(['vehicle_type' => 'box_truck']));

        $this->assertSame('scope.any', $winner->rule_key);
    }

    public function test_draft_rules_are_never_resolvable(): void
    {
        $this->admin()->createDraft([
            'rule_key' => 'draft.only',
            'name' => 'Draft',
            'work_type' => 'delivery',
            'priority' => 100,
            'components' => ['flat' => ['amount_cents' => 5000]],
        ], 1);

        $this->assertNull((new RuleResolver())->resolve($this->ctx()));
    }

    public function test_disabled_and_out_of_window_rules_are_not_resolvable(): void
    {
        $disabled = $this->publish([
            'rule_key' => 'off.disabled',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);
        $this->admin()->setActive($disabled, false, 1);

        $this->publish([
            'rule_key' => 'off.expired',
            'effective_from' => Carbon::now()->subDays(10),
            'effective_to' => Carbon::now()->subDay(),
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);

        $this->assertNull((new RuleResolver())->resolve($this->ctx()));
    }

    public function test_effective_window_is_honoured_at_the_requested_time(): void
    {
        $this->publish([
            'rule_key' => 'window.future',
            'effective_from' => Carbon::now()->addDays(5),
            'components' => ['flat' => ['amount_cents' => 4200]],
        ]);

        $resolver = new RuleResolver();

        $this->assertNull($resolver->resolve($this->ctx()));
        $this->assertNotNull($resolver->resolve($this->ctx(), Carbon::now()->addDays(6)));
    }

    // -------------------------------------------------------------- versioning

    public function test_revising_a_published_rule_creates_a_new_draft_version(): void
    {
        $admin = $this->admin();
        $published = $this->publish([
            'rule_key' => 'ver.rule',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);

        $revised = $admin->revise($published, [
            'components' => ['flat' => ['amount_cents' => 1500]],
        ], 1);

        $this->assertSame(2, $revised->version);
        $this->assertSame(UrbanGoodzCompensationRule::STATE_DRAFT, $revised->state);

        // The published version is untouched.
        $this->assertSame(1000, $published->fresh()->components['flat']['amount_cents']);
        $this->assertSame(UrbanGoodzCompensationRule::STATE_PUBLISHED, $published->fresh()->state);
    }

    public function test_publishing_a_new_version_archives_the_previous_one(): void
    {
        $admin = $this->admin();
        $v1 = $this->publish([
            'rule_key' => 'ver.super',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);

        $v2 = $admin->publish(
            $admin->revise($v1, ['components' => ['flat' => ['amount_cents' => 2000]]], 1),
            1
        );

        $this->assertSame(UrbanGoodzCompensationRule::STATE_ARCHIVED, $v1->fresh()->state);
        $this->assertSame(UrbanGoodzCompensationRule::STATE_PUBLISHED, $v2->fresh()->state);

        $winner = (new RuleResolver())->resolve($this->ctx());
        $this->assertSame(2, $winner->version);
    }

    public function test_archived_rules_may_not_be_revised_or_published(): void
    {
        $admin = $this->admin();
        $rule = $this->publish(['rule_key' => 'ver.arch', 'components' => ['flat' => ['amount_cents' => 100]]]);
        $admin->archive($rule, 1);

        $this->expectException(RuntimeException::class);
        $admin->revise($rule->fresh(), ['name' => 'nope'], 1);
    }

    // ------------------------------------------------------------------ audit

    public function test_every_lifecycle_transition_is_audited(): void
    {
        $admin = $this->admin();
        $rule = $admin->createDraft([
            'rule_key' => 'audit.rule',
            'name' => 'Audited',
            'work_type' => 'delivery',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ], 42);

        $admin->publish($rule, 42);
        $admin->setActive($rule->fresh(), false, 42);

        $events = array_map(
            fn ($audit) => $audit->event,
            $admin->history('audit.rule')
        );

        $this->assertContains('created', $events);
        $this->assertContains('published', $events);
        $this->assertContains('disabled', $events);
    }

    public function test_audit_records_the_actor_and_the_value_change(): void
    {
        $admin = $this->admin();
        $rule = $admin->createDraft([
            'rule_key' => 'audit.values',
            'name' => 'Before',
            'work_type' => 'delivery',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ], 7);

        $admin->revise($rule, ['name' => 'After'], 7);

        $update = collect($admin->history('audit.values'))->firstWhere('event', 'updated');

        $this->assertNotNull($update);
        $this->assertSame(7, $update->actor_id);
        $this->assertSame('Before', $update->old_values['name']);
        $this->assertSame('After', $update->new_values['name']);
    }

    // ------------------------------------------------------------- validation

    public function test_rule_without_components_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->admin()->createDraft([
            'rule_key' => 'bad.empty',
            'name' => 'Empty',
            'work_type' => 'delivery',
            'components' => [],
        ], 1);
    }

    public function test_minimum_above_maximum_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->admin()->createDraft([
            'rule_key' => 'bad.range',
            'name' => 'Bad range',
            'work_type' => 'delivery',
            'components' => ['flat' => ['amount_cents' => 100]],
            'minimum_payout_cents' => 5000,
            'maximum_payout_cents' => 1000,
        ], 1);
    }

    public function test_unsupported_work_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->admin()->createDraft([
            'rule_key' => 'bad.work',
            'name' => 'Bad',
            'work_type' => 'teleportation',
            'components' => ['flat' => ['amount_cents' => 100]],
        ], 1);
    }

    // ----------------------------------------------- no retroactive mutation

    public function test_finalized_results_are_immutable(): void
    {
        $rule = $this->publish([
            'rule_key' => 'immutable.rule',
            'components' => ['flat' => ['amount_cents' => 2500]],
        ]);

        $engine = new CompensationEngine();
        $ctx = $this->ctx(['subject_type' => 'order', 'subject_id' => 1234, 'driver_id' => 9]);
        $result = $engine->record($engine->calculateWithRule($rule, $ctx), $ctx, true);

        $this->expectException(RuntimeException::class);

        $result->driver_cents = 999999;
        $result->save();
    }

    public function test_changing_a_rule_does_not_alter_a_recorded_payout(): void
    {
        $admin = $this->admin();
        $rule = $this->publish([
            'rule_key' => 'history.rule',
            'components' => ['flat' => ['amount_cents' => 2500]],
        ]);

        $engine = new CompensationEngine();
        $ctx = $this->ctx(['subject_type' => 'order', 'subject_id' => 555, 'driver_id' => 3]);
        $recorded = $engine->record($engine->calculateWithRule($rule, $ctx), $ctx, true);

        $admin->publish(
            $admin->revise($rule, ['components' => ['flat' => ['amount_cents' => 100]]], 1),
            1
        );

        $this->assertSame(2500, $recorded->fresh()->driver_cents);
        $this->assertSame(1, $recorded->fresh()->rule_version);
    }

    // ------------------------------------------------------------- simulator

    public function test_simulator_reports_candidates_and_does_not_persist(): void
    {
        $this->publish([
            'rule_key' => 'sim.low', 'priority' => 1,
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);
        $this->publish([
            'rule_key' => 'sim.high', 'priority' => 20,
            'components' => ['flat' => ['amount_cents' => 3000]],
        ]);

        $before = UrbanGoodzCompensationResult::count();
        $simulation = (new CompensationSimulator())->simulate($this->ctx());

        $this->assertTrue($simulation['matched']);
        $this->assertSame('sim.high', $simulation['selected_rule']['rule_key']);
        $this->assertCount(2, $simulation['candidates']);
        $this->assertSame(3000, $simulation['calculation']['driver_cents']);
        $this->assertSame($before, UrbanGoodzCompensationResult::count());
    }

    public function test_simulator_reports_when_nothing_matches(): void
    {
        $simulation = (new CompensationSimulator())->simulate($this->ctx(['work_type' => 'medical']));

        $this->assertFalse($simulation['matched']);
        $this->assertNull($simulation['calculation']);
    }

    public function test_simulator_compares_a_draft_against_the_published_rule(): void
    {
        $admin = $this->admin();
        $published = $this->publish([
            'rule_key' => 'sim.compare',
            'components' => ['flat' => ['amount_cents' => 1000]],
        ]);
        $draft = $admin->revise($published, ['components' => ['flat' => ['amount_cents' => 1750]]], 1);

        $comparison = (new CompensationSimulator())->compare($published, $draft, $this->ctx());

        $this->assertSame(750, $comparison['driver_delta_cents']);
        $this->assertSame('7.50', $comparison['driver_delta']);
    }

    // ---------------------------------------------------- engine integration

    public function test_engine_throws_when_no_rule_matches(): void
    {
        $this->expectException(RuntimeException::class);

        (new CompensationEngine())->calculate($this->ctx(['work_type' => 'logistics']));
    }

    public function test_recorded_result_stores_context_breakdown_and_explanation(): void
    {
        $rule = $this->publish([
            'rule_key' => 'record.rule',
            'components' => ['base' => ['amount_cents' => 500], 'per_mile' => ['rate_cents' => 60]],
            'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['percent' => 10]],
        ]);

        $engine = new CompensationEngine();
        $ctx = $this->ctx([
            'miles' => 10,
            'customer_charge_cents' => 5000,
            'subject_type' => 'order',
            'subject_id' => 777,
            'driver_id' => 11,
        ]);

        $recorded = $engine->record($engine->calculateWithRule($rule, $ctx), $ctx, true);

        $this->assertSame(1100, $recorded->driver_cents);
        $this->assertSame(5000, $recorded->gross_cents);
        // JSON has no int/float distinction for whole numbers, so 10.0 round-trips
        // as 10. Compare numerically rather than by type.
        $this->assertEquals(10, $recorded->context['miles']);
        $this->assertNotEmpty($recorded->breakdown['lines']);
        $this->assertStringContainsString('DRIVER TOTAL', $recorded->explanation);
        $this->assertSame(500, $recorded->splits['dispatcher_cents']);
    }
}
