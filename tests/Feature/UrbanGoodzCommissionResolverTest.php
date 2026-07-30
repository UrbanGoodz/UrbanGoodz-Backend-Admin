<?php

namespace Tests\Feature;

use App\Exceptions\CommissionConfigurationException;
use App\Models\BusinessSetting;
use App\Models\Store;
use App\Models\UrbanGoodzCommissionRule;
use App\Services\UrbanGoodz\CommissionContext;
use App\Services\UrbanGoodz\ResolvedCommission;
use App\Services\UrbanGoodz\UrbanGoodzCommissionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The universal settlement rule with a configurable rate.
 *
 * Covers the spec test matrix items that apply to rule resolution: per-module
 * rates, fixed amounts, commission basis, hierarchy precedence, effective
 * dating, invalid rates and missing configuration.
 */
class UrbanGoodzCommissionResolverTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzCommissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UrbanGoodzCommissionResolver();
        // Isolate from seeded data so precedence assertions are unambiguous.
        UrbanGoodzCommissionRule::query()->forceDelete();
    }

    private function rule(array $attributes): UrbanGoodzCommissionRule
    {
        return UrbanGoodzCommissionRule::create(array_merge([
            'name' => 'test rule',
            'commission_enabled' => true,
            'calculation_type' => UrbanGoodzCommissionRule::CALC_PERCENTAGE,
            'basis' => 'merchandise_subtotal',
            'is_active' => true,
            'version' => 1,
        ], $attributes));
    }

    private function context(string $type, int $cents, array $overrides = []): CommissionContext
    {
        return new CommissionContext(
            transactionType: $type,
            qualifyingAmountCents: $cents,
            moduleId: $overrides['moduleId'] ?? null,
            partnerType: $overrides['partnerType'] ?? null,
            partnerId: $overrides['partnerId'] ?? null,
            contractId: $overrides['contractId'] ?? null,
            serviceType: $overrides['serviceType'] ?? null,
            zoneId: $overrides['zoneId'] ?? null,
            market: $overrides['market'] ?? null,
            subjectType: $overrides['subjectType'] ?? null,
            subjectId: $overrides['subjectId'] ?? null,
            at: $overrides['at'] ?? null,
        );
    }

    // ---------- worked examples from the specification ----------

    /** Marketplace: $10.00 at 23% → $2.30 platform, $7.70 vendor. */
    public function test_marketplace_store_at_twenty_three_percent(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_MARKETPLACE_ORDER,
            'partner_type' => 'store', 'partner_id' => 4242,
            'rate_percent' => '23.0000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 1000,
            ['partnerType' => 'store', 'partnerId' => 4242]
        ));

        $this->assertSame(230, $result->commissionAmountCents);
        $this->assertSame(770, $result->partnerNetCents);
        $this->assertTrue($result->balances());
    }

    /** Load board: $1,000.00 at 5% → $50.00 platform, $950.00 carrier. */
    public function test_load_board_percentage(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_LOAD_BOARD,
            'basis' => 'booked_load_amount',
            'rate_percent' => '5.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_LOAD_BOARD, 100000)
        );

        $this->assertSame(5000, $result->commissionAmountCents);
        $this->assertSame(95000, $result->partnerNetCents);
        $this->assertSame('booked_load_amount', $result->basis);
    }

    public function test_load_board_fixed_fee(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_LOAD_BOARD,
            'basis' => 'booked_load_amount',
            'calculation_type' => UrbanGoodzCommissionRule::CALC_FIXED,
            'fixed_amount_cents' => 7500,
            'rate_percent' => null,
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_LOAD_BOARD, 100000)
        );

        $this->assertSame(7500, $result->commissionAmountCents);
        $this->assertSame(92500, $result->partnerNetCents);
        $this->assertNull($result->ratePercent);
    }

    /** Dispatcher: commission applies to the $100 fee, never the whole load. */
    public function test_dispatcher_commission_uses_the_dispatcher_fee_as_basis(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_DISPATCHER_FEE,
            'basis' => 'dispatcher_fee',
            'rate_percent' => '10.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_DISPATCHER_FEE, 10000)
        );

        $this->assertSame(1000, $result->commissionAmountCents);
        $this->assertSame(9000, $result->partnerNetCents);
        $this->assertSame('dispatcher_fee', $result->basis);
    }

    /** Medical courier: $200.00 at 10% → $20.00 platform, $180.00 provider. */
    public function test_medical_courier_job(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_MEDICAL_COURIER,
            'basis' => 'job_revenue',
            'rate_percent' => '10.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_MEDICAL_COURIER, 20000)
        );

        $this->assertSame(2000, $result->commissionAmountCents);
        $this->assertSame(18000, $result->partnerNetCents);
    }

    /** Business route: $300.00 at 8% → $24.00 platform, $276.00 provider. */
    public function test_business_route(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_BUSINESS_ROUTE,
            'basis' => 'route_charge',
            'rate_percent' => '8.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_BUSINESS_ROUTE, 30000)
        );

        $this->assertSame(2400, $result->commissionAmountCents);
        $this->assertSame(27600, $result->partnerNetCents);
    }

    /** Service booking: $100.00 at 15% → $15.00 platform, $85.00 provider. */
    public function test_service_booking(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_SERVICE_BOOKING,
            'basis' => 'booking_subtotal',
            'rate_percent' => '15.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_SERVICE_BOOKING, 10000)
        );

        $this->assertSame(1500, $result->commissionAmountCents);
        $this->assertSame(8500, $result->partnerNetCents);
    }

    /** Rental: $300.00 at 12% → $36.00 platform, $264.00 provider. */
    public function test_rental(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_RENTAL,
            'basis' => 'rental_subtotal',
            'rate_percent' => '12.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_RENTAL, 30000)
        );

        $this->assertSame(3600, $result->commissionAmountCents);
        $this->assertSame(26400, $result->partnerNetCents);
    }

    /** Creator: $500.00 at 10% → $50.00 platform, $450.00 merchant/creator. */
    public function test_creator_transaction(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_CREATOR,
            'basis' => 'creator_attributed_revenue',
            'rate_percent' => '10.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_CREATOR, 50000)
        );

        $this->assertSame(5000, $result->commissionAmountCents);
        $this->assertSame(45000, $result->partnerNetCents);
    }

    /** Fashion Fit: $150.00 at 10% → $15.00 platform, $135.00 stylist. */
    public function test_fashion_fit_service(): void
    {
        $this->rule([
            'transaction_type' => CommissionContext::TYPE_FASHION_FIT,
            'basis' => 'service_amount',
            'rate_percent' => '10.0000',
        ]);

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_FASHION_FIT, 15000)
        );

        $this->assertSame(1500, $result->commissionAmountCents);
        $this->assertSame(13500, $result->partnerNetCents);
    }

    // ---------- hierarchy ----------

    public function test_partner_rule_beats_module_and_global(): void
    {
        $this->rule(['name' => 'global', 'rate_percent' => '10.0000']);
        $this->rule(['name' => 'module', 'module_id' => 4, 'rate_percent' => '18.0000']);
        $this->rule([
            'name' => 'partner', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '23.0000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['moduleId' => 4, 'partnerType' => 'store', 'partnerId' => 14]
        ));

        $this->assertSame('partner', $result->rule->name);
        $this->assertSame(2300, $result->commissionAmountCents);
        $this->assertSame(UrbanGoodzCommissionRule::TIER_PARTNER, $result->specificity);
    }

    public function test_job_specific_override_beats_every_other_rule(): void
    {
        $this->rule(['name' => 'global', 'rate_percent' => '10.0000']);
        $this->rule([
            'name' => 'partner', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '23.0000',
        ]);
        $this->rule([
            'name' => 'contract', 'contract_id' => 99, 'rate_percent' => '20.0000',
        ]);
        $this->rule([
            'name' => 'job', 'subject_type' => 'App\\Models\\Order', 'subject_id' => 555,
            'rate_percent' => '2.5000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            [
                'partnerType' => 'store', 'partnerId' => 14, 'contractId' => 99,
                'subjectType' => 'App\\Models\\Order', 'subjectId' => 555,
            ]
        ));

        $this->assertSame('job', $result->rule->name);
        $this->assertSame(250, $result->commissionAmountCents);
    }

    public function test_a_general_rule_never_displaces_a_specific_one(): void
    {
        $this->rule([
            'name' => 'specific', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '23.0000', 'priority' => 0,
        ]);
        // Higher priority but broader scope must still lose.
        $this->rule(['name' => 'broad', 'rate_percent' => '5.0000', 'priority' => 999]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => 14]
        ));

        $this->assertSame('specific', $result->rule->name);
    }

    public function test_priority_breaks_ties_within_a_tier(): void
    {
        $this->rule(['name' => 'low', 'module_id' => 4, 'rate_percent' => '11.0000', 'priority' => 1]);
        $this->rule(['name' => 'high', 'module_id' => 4, 'rate_percent' => '17.0000', 'priority' => 9]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000, ['moduleId' => 4]
        ));

        $this->assertSame('high', $result->rule->name);
    }

    public function test_a_partner_rule_does_not_leak_across_partner_types(): void
    {
        $this->rule(['name' => 'global', 'rate_percent' => '10.0000']);
        $this->rule([
            'name' => 'store 14', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '23.0000',
        ]);

        // Same id, different partner type — must not match the store rule.
        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'carrier', 'partnerId' => 14]
        ));

        $this->assertSame('global', $result->rule->name);
    }

    public function test_service_type_rule_applies_to_stat_medical_jobs(): void
    {
        $this->rule([
            'name' => 'medical base',
            'transaction_type' => CommissionContext::TYPE_MEDICAL_COURIER,
            'basis' => 'job_revenue', 'rate_percent' => '10.0000',
        ]);
        $this->rule([
            'name' => 'stat',
            'transaction_type' => CommissionContext::TYPE_MEDICAL_COURIER,
            'service_type' => 'stat', 'basis' => 'job_revenue', 'rate_percent' => '14.0000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MEDICAL_COURIER, 20000, ['serviceType' => 'stat']
        ));

        $this->assertSame('stat', $result->rule->name);
        $this->assertSame(2800, $result->commissionAmountCents);
    }

    // ---------- effective dating ----------

    public function test_a_midday_rate_change_does_not_affect_an_earlier_transaction(): void
    {
        $this->rule([
            'name' => 'morning', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '23.0000',
            'effective_from' => '2026-07-01 00:00:00',
            'effective_to' => '2026-07-29 12:00:00',
        ]);
        $this->rule([
            'name' => 'afternoon', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '9.0000',
            'effective_from' => '2026-07-29 12:00:00',
        ]);

        $before = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => 14, 'at' => new \DateTimeImmutable('2026-07-29 09:00:00')]
        ));
        $after = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => 14, 'at' => new \DateTimeImmutable('2026-07-29 15:00:00')]
        ));

        $this->assertSame('morning', $before->rule->name);
        $this->assertSame(2300, $before->commissionAmountCents);
        $this->assertSame('afternoon', $after->rule->name);
        $this->assertSame(900, $after->commissionAmountCents);
    }

    public function test_inactive_rules_are_ignored(): void
    {
        $this->rule(['name' => 'global', 'rate_percent' => '10.0000']);
        $this->rule([
            'name' => 'disabled', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '23.0000', 'is_active' => false,
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => 14]
        ));

        $this->assertSame('global', $result->rule->name);
    }

    // ---------- zero, clamps, validation, safe failure ----------

    public function test_explicit_zero_percent_is_honoured_and_is_not_treated_as_unset(): void
    {
        $this->rule(['name' => 'global', 'rate_percent' => '10.0000']);
        $this->rule([
            'name' => 'promo', 'partner_type' => 'store', 'partner_id' => 14,
            'rate_percent' => '0.0000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => 14]
        ));

        $this->assertSame('promo', $result->rule->name);
        $this->assertSame(0, $result->commissionAmountCents);
        $this->assertSame(10000, $result->partnerNetCents);
    }

    public function test_commission_disabled_rule_yields_zero(): void
    {
        $this->rule([
            'name' => 'waived', 'partner_type' => 'store', 'partner_id' => 14,
            'commission_enabled' => false, 'rate_percent' => '23.0000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => 14]
        ));

        $this->assertSame(0, $result->commissionAmountCents);
        $this->assertSame(10000, $result->partnerNetCents);
    }

    public function test_minimum_and_maximum_clamp_the_commission(): void
    {
        $this->rule([
            'name' => 'floored', 'transaction_type' => CommissionContext::TYPE_LOAD_BOARD,
            'basis' => 'booked_load_amount', 'rate_percent' => '1.0000',
            'minimum_cents' => 2500, 'maximum_cents' => 9000,
        ]);

        $low = $this->resolver->resolve($this->context(CommissionContext::TYPE_LOAD_BOARD, 100000));
        $this->assertSame(2500, $low->commissionAmountCents, 'minimum should raise a small commission');

        $high = $this->resolver->resolve($this->context(CommissionContext::TYPE_LOAD_BOARD, 5000000));
        $this->assertSame(9000, $high->commissionAmountCents, 'maximum should cap a large commission');
    }

    public function test_commission_never_exceeds_the_qualifying_amount(): void
    {
        $this->rule([
            'name' => 'big fixed', 'transaction_type' => CommissionContext::TYPE_LOAD_BOARD,
            'basis' => 'booked_load_amount',
            'calculation_type' => UrbanGoodzCommissionRule::CALC_FIXED,
            'fixed_amount_cents' => 999999, 'rate_percent' => null,
        ]);

        $result = $this->resolver->resolve($this->context(CommissionContext::TYPE_LOAD_BOARD, 5000));

        $this->assertSame(5000, $result->commissionAmountCents);
        $this->assertSame(0, $result->partnerNetCents);
        $this->assertTrue($result->balances());
    }

    public function test_rate_above_one_hundred_is_rejected(): void
    {
        $this->rule(['name' => 'bad', 'rate_percent' => '140.0000']);

        $this->expectException(CommissionConfigurationException::class);
        $this->resolver->resolve($this->context(CommissionContext::TYPE_MARKETPLACE_ORDER, 10000));
    }

    public function test_negative_rate_is_rejected(): void
    {
        $this->rule(['name' => 'bad', 'rate_percent' => '-5.0000']);

        $this->expectException(CommissionConfigurationException::class);
        $this->resolver->resolve($this->context(CommissionContext::TYPE_MARKETPLACE_ORDER, 10000));
    }

    /** No rule and no legacy source for a non-marketplace module must halt. */
    public function test_missing_configuration_fails_safe(): void
    {
        try {
            $this->resolver->resolve($this->context(CommissionContext::TYPE_LOAD_BOARD, 100000));
            $this->fail('Expected settlement to halt when no configuration exists.');
        } catch (CommissionConfigurationException $exception) {
            $this->assertSame('missing_configuration', $exception->reason);
            $this->assertStringContainsString('load_board', $exception->getMessage());
        }
    }

    // ---------- legacy bridge: nothing changes until rules exist ----------

    public function test_marketplace_falls_back_to_the_legacy_store_override(): void
    {
        $store = Store::withoutGlobalScopes()->whereNotNull('comission')->first();

        if ($store === null) {
            $this->markTestSkipped('No store carries a legacy commission override.');
        }

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => (int) $store->id]
        ));

        $this->assertSame(ResolvedCommission::SOURCE_LEGACY_STORE, $result->source);
        $this->assertSame((string) $store->comission, $result->ratePercent);
    }

    public function test_marketplace_falls_back_to_the_global_setting(): void
    {
        $global = BusinessSetting::where('key', 'admin_commission')->value('value');

        if ($global === null) {
            $this->markTestSkipped('No global admin_commission configured.');
        }

        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_MARKETPLACE_ORDER, 10000)
        );

        $this->assertSame(ResolvedCommission::SOURCE_LEGACY_GLOBAL, $result->source);
        $this->assertSame((string) $global, $result->ratePercent);
    }

    public function test_a_configured_rule_takes_precedence_over_the_legacy_store_override(): void
    {
        $store = Store::withoutGlobalScopes()->whereNotNull('comission')->first();

        if ($store === null) {
            $this->markTestSkipped('No store carries a legacy commission override.');
        }

        $this->rule([
            'name' => 'migrated', 'partner_type' => 'store', 'partner_id' => (int) $store->id,
            'rate_percent' => '7.0000',
        ]);

        $result = $this->resolver->resolve($this->context(
            CommissionContext::TYPE_MARKETPLACE_ORDER, 10000,
            ['partnerType' => 'store', 'partnerId' => (int) $store->id]
        ));

        $this->assertSame(ResolvedCommission::SOURCE_RULE, $result->source);
        $this->assertSame(700, $result->commissionAmountCents);
    }

    // ---------- rounding ----------

    public function test_rounding_is_half_up_to_the_cent(): void
    {
        $this->rule([
            'name' => 'odd', 'transaction_type' => CommissionContext::TYPE_SERVICE_BOOKING,
            'basis' => 'booking_subtotal', 'rate_percent' => '23.0000',
        ]);

        // 1799 cents * 23% = 413.77 → 414
        $result = $this->resolver->resolve(
            $this->context(CommissionContext::TYPE_SERVICE_BOOKING, 1799)
        );

        $this->assertSame(414, $result->commissionAmountCents);
        $this->assertSame(1385, $result->partnerNetCents);
        $this->assertTrue($result->balances());
    }
}
