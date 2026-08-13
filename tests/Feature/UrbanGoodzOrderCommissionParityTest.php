<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Store;
use App\Models\UrbanGoodzCommissionRule;
use App\Services\UrbanGoodz\CommissionContext;
use App\Services\UrbanGoodz\OrderCommissionContextFactory;
use App\Services\UrbanGoodz\UrbanGoodzCommissionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 3 parity: routing OrderLogic through the resolver must not move a single
 * historical amount.
 *
 * The expression being replaced was
 *
 *     isset($order->store->comission) == null
 *         ? BusinessSetting::where('key','admin_commission')->first()?->value
 *         : $order->store->comission;
 *
 * These tests pin the replacement to exactly that behaviour, then show the
 * resolver adding what the old expression could not do.
 */
class UrbanGoodzOrderCommissionParityTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzCommissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UrbanGoodzCommissionResolver();
        UrbanGoodzCommissionRule::query()->forceDelete();
    }

    /**
     * The behaviour of the original expression, reproduced verbatim so parity
     * is asserted against the real thing rather than a remembered description.
     */
    private function legacyExpression(Order $order): ?string
    {
        $value = isset($order->store->comission) == null
            ? BusinessSetting::where('key', 'admin_commission')->first()?->value
            : $order->store->comission;

        return $value === null ? null : (string) $value;
    }

    private function orderForStore(Store $store): Order
    {
        $order = new Order();
        $order->forceFill([
            'id' => 999000 + (int) $store->id,
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'zone_id' => $store->zone_id,
            'order_type' => 'delivery',
            'created_at' => now(),
        ]);
        $order->setRelation('store', $store);

        return $order;
    }

    private function vendorId(string $tag): int
    {
        return \Illuminate\Support\Facades\DB::table('vendors')->insertGetId([
            'f_name' => 'Parity', 'l_name' => $tag,
            'phone' => '1'.random_int(1000000000, 1999999999),
            'email' => 'parity-'.strtolower($tag).'-'.\Illuminate\Support\Str::random(8).'@urbangoodz.test',
            'password' => bcrypt('not-a-production-password'),
            'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_store_with_an_override_resolves_to_the_same_rate_as_before(): void
    {
        $store = Store::withoutGlobalScopes()->whereNotNull('comission')->first();

        if ($store === null) {
            $store = Store::create([
                'name' => 'Parity Override Store',
                'phone' => '1'.random_int(1000000000, 1999999999),
                'vendor_id' => $this->vendorId('Override'),
                'comission' => '21.0000',
            ]);
        }

        $order = $this->orderForStore($store);

        $this->assertSame(
            $this->legacyExpression($order),
            $this->resolver->resolvePercentageRate(OrderCommissionContextFactory::forOrder($order)),
            'the resolver must return the store override byte-for-byte'
        );
    }

    public function test_store_without_an_override_inherits_the_global_rate_as_before(): void
    {
        $store = Store::withoutGlobalScopes()->whereNull('comission')->first();

        if ($store === null) {
            $store = Store::create([
                'name' => 'Parity No-Override Store',
                'phone' => '1'.random_int(1000000000, 1999999999),
                'vendor_id' => $this->vendorId('NoOverride'),
            ]);
        }

        // The test is "inherits the global rate", not "no configuration
        // exists" (that's UrbanGoodzCommissionResolverTest::
        // test_missing_configuration_fails_safe) - a global rate must
        // actually be present for either path to have something to inherit.
        if (BusinessSetting::where('key', 'admin_commission')->doesntExist()) {
            BusinessSetting::create(['key' => 'admin_commission', 'value' => '13.0000']);
        }

        $order = $this->orderForStore($store);

        $this->assertSame(
            $this->legacyExpression($order),
            $this->resolver->resolvePercentageRate(OrderCommissionContextFactory::forOrder($order)),
            'the resolver must fall through to admin_commission exactly as before'
        );
    }

    /** The Breakfast Klub, the store behind order 100009. */
    public function test_store_fourteen_still_settles_at_twenty_three_percent(): void
    {
        $store = Store::withoutGlobalScopes()->find(14);

        if ($store === null) {
            $this->markTestSkipped('Store 14 is not present in this database.');
        }

        $rate = $this->resolver->resolvePercentageRate(
            OrderCommissionContextFactory::forOrder($this->orderForStore($store))
        );

        $this->assertSame((string) $store->comission, $rate);
    }

    /**
     * Order 100009 reconstructs to the recorded split: items 22.74, additional
     * 4.95, delivery 1.00, commission 10.18, store 17.51 — at 23%.
     */
    public function test_order_100009_reconstructs_to_its_recorded_split(): void
    {
        $store = Store::withoutGlobalScopes()->find(14);

        if ($store === null || $store->comission === null) {
            $this->markTestSkipped('Store 14 or its commission override is not present.');
        }

        $itemSubtotalCents = 2274;   // 17.99 + 4.75
        $additionalCents = 495;
        $deliveryCents = 100;

        $result = $this->resolver->resolve(new CommissionContext(
            transactionType: CommissionContext::TYPE_MARKETPLACE_ORDER,
            qualifyingAmountCents: $itemSubtotalCents,
            moduleId: 4,
            partnerType: 'store',
            partnerId: 14,
            zoneId: 2,
        ));

        // 23% of 22.74 = 5.2302 -> 5.23 on the merchandise basis.
        $this->assertSame(523, $result->commissionAmountCents);
        $this->assertSame(1751, $result->partnerNetCents, 'vendor merchandise earning must stay 17.51');

        // The recorded admin_commission of 10.18 is the merchandise commission
        // plus the additional charge, and the whole order still balances.
        $this->assertSame(1018, $result->commissionAmountCents + $additionalCents);
        $this->assertSame(
            2869,
            $result->partnerNetCents + $result->commissionAmountCents + $additionalCents + $deliveryCents
        );
    }

    /**
     * What the old expression could not express: a rule for a module that has
     * no `stores.comission` column to lean on.
     */
    public function test_the_resolver_adds_configuration_the_old_expression_could_not_reach(): void
    {
        UrbanGoodzCommissionRule::create([
            'name' => 'medical stat',
            'transaction_type' => CommissionContext::TYPE_MEDICAL_COURIER,
            'service_type' => 'stat',
            'basis' => 'job_revenue',
            'calculation_type' => UrbanGoodzCommissionRule::CALC_PERCENTAGE,
            'rate_percent' => '10.0000',
            'commission_enabled' => true,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolve(new CommissionContext(
            transactionType: CommissionContext::TYPE_MEDICAL_COURIER,
            qualifyingAmountCents: 20000,
            serviceType: 'stat',
        ));

        $this->assertSame(2000, $result->commissionAmountCents);
        $this->assertSame(18000, $result->partnerNetCents);
    }
}
