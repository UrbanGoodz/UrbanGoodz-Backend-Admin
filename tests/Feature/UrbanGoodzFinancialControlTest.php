<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzFinancialRule;
use App\Services\UrbanGoodz\FinancialControl\FinancialControlService;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\TestCase;

class UrbanGoodzFinancialControlTest extends TestCase
{
    private FinancialControlService $financialControl;

    private string $root;

    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
        $this->loadFinancialControlSources();

        $container = new Container;
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        $this->capsule = new Capsule($container);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $this->capsule->setEventDispatcher(new Dispatcher($container));
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        Model::clearBootedModels();
        $container->instance('db', $this->capsule->getDatabaseManager());
        $container->instance('db.schema', $this->capsule->getConnection()->getSchemaBuilder());
        $container->instance(AuthFactory::class, $this->authFactory());

        $this->runFinancialControlMigration();
        $this->financialControl = new FinancialControlService;
    }

    protected function tearDown(): void
    {
        $this->capsule->getDatabaseManager()->disconnect();
        Model::unsetEventDispatcher();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_commission_is_deducted_from_provider_and_driver_pay_is_separate(): void
    {
        $this->rule('business_commission', 'percentage', rateBasisPoints: 1000);
        $this->rule('driver_compensation', 'per_mile', amountCents: 100);
        $this->rule('driver_compensation', 'per_package', amountCents: 50);
        $this->rule('driver_compensation', 'per_stop', amountCents: 75);
        $this->rule('driver_compensation', 'per_route', amountCents: 300);
        $this->rule('driver_compensation', 'hourly', amountCents: 120);
        $this->rule('driver_premium', 'flat', amountCents: 250);
        $this->rule('driver_admin_fee', 'percentage', rateBasisPoints: 1000);

        $result = $this->financialControl->simulate($this->context());

        self::assertSame(12000, $result['shopper_total_cents']);
        self::assertSame(1000, $result['business_commission_cents']);
        self::assertSame(9000, $result['provider_proceeds_cents']);
        self::assertSame(1555, $result['driver_compensation_cents']);
        self::assertSame(156, $result['driver_admin_fee_cents']);
        self::assertSame(1399, $result['driver_net_cents']);
        self::assertSame(445, $result['platform_delivery_margin_cents']);
        self::assertSame(1601, $result['platform_net_cents']);
        self::assertSame(
            $result['shopper_total_cents'],
            $result['provider_proceeds_cents'] + $result['driver_net_cents'] + $result['platform_net_cents']
        );
    }

    public function test_fixed_commission_does_not_raise_shopper_merchandise_price(): void
    {
        $this->rule('business_commission', 'fixed', amountCents: 375);

        $result = $this->financialControl->simulate($this->context([
            'merchandise_subtotal_cents' => 5000,
            'delivery_charge_cents' => 0,
        ]));

        self::assertSame(5000, $result['shopper_total_cents']);
        self::assertSame(375, $result['business_commission_cents']);
        self::assertSame(4625, $result['provider_proceeds_cents']);
    }

    public function test_rule_hierarchy_honors_effective_dates_priority_scope_and_service(): void
    {
        $at = CarbonImmutable::parse('2026-07-30 12:00:00', 'UTC');
        $this->rule('business_commission', 'percentage', rateBasisPoints: 500, priority: 100);
        $businessRule = $this->rule(
            'business_commission',
            'percentage',
            rateBasisPoints: 1000,
            scopeType: 'business',
            scopeKey: '20',
            priority: 100
        );
        $providerRule = $this->rule(
            'business_commission',
            'percentage',
            rateBasisPoints: 1500,
            scopeType: 'provider',
            scopeKey: '30',
            serviceType: 'marketplace_delivery',
            priority: 200,
            effectiveFrom: '2026-07-01 00:00:00',
            effectiveTo: '2026-08-01 00:00:00'
        );
        $this->rule(
            'business_commission',
            'percentage',
            rateBasisPoints: 9900,
            scopeType: 'provider',
            scopeKey: '30',
            priority: 1000,
            effectiveTo: '2026-07-01 00:00:00'
        );

        $result = $this->financialControl->simulate($this->context(), $at);
        self::assertSame(1500, $result['business_commission_cents']);
        self::assertSame($providerRule->id, $result['rules']['business_commission']['id']);

        $differentProvider = $this->financialControl->simulate($this->context(['provider_id' => 31]), $at);
        self::assertSame(1000, $differentProvider['business_commission_cents']);
        self::assertSame($businessRule->id, $differentProvider['rules']['business_commission']['id']);
    }

    public function test_settlement_snapshot_is_idempotent_balanced_and_preserves_rule_history(): void
    {
        $rule = $this->rule('business_commission', 'percentage', rateBasisPoints: 1000);
        $first = $this->financialControl->settle('order', 501, $this->context(), 'order:501:settle');
        $second = $this->financialControl->settle('order', 501, $this->context(), 'order:501:settle');

        self::assertSame($first->id, $second->id);
        self::assertSame('balanced', $first->reconciliation_status);
        self::assertSame(1, $first->reconciliationRuns()->count());
        self::assertSame(
            $first->ledgerEntries()->where('direction', 'debit')->sum('amount_cents'),
            $first->ledgerEntries()->where('direction', 'credit')->sum('amount_cents')
        );
        self::assertSame($rule->id, $first->rule_snapshot['business_commission']['id']);

        $rule->update(['is_active' => false]);
        self::assertSame($rule->id, $first->fresh()->rule_snapshot['business_commission']['id']);
    }

    public function test_refunds_and_reversals_are_idempotent_append_only_and_reconciled(): void
    {
        $this->rule('business_commission', 'percentage', rateBasisPoints: 1000);
        $this->rule('driver_compensation', 'per_mile', amountCents: 100);
        $snapshot = $this->financialControl->settle('route', 701, $this->context(), 'route:701:settle');

        $refunded = $this->financialControl->refund($snapshot, 3000, 'Partial customer refund', 'route:701:refund:1');
        $duplicate = $this->financialControl->refund($snapshot, 3000, 'Partial customer refund', 'route:701:refund:1');
        self::assertSame(3000, $refunded->refunded_cents);
        self::assertSame(3000, $duplicate->refunded_cents);
        self::assertSame('partially_refunded', $refunded->status);
        self::assertSame('balanced', $refunded->reconciliation_status);

        $reversed = $this->financialControl->reverse(
            $refunded,
            'Reverse remaining settlement',
            'route:701:reversal'
        );
        self::assertSame(12000, $reversed->refunded_cents);
        self::assertSame('reversed', $reversed->status);
        self::assertSame('balanced', $reversed->reconciliation_status);
        self::assertSame(
            $reversed->ledgerEntries()->where('direction', 'debit')->sum('amount_cents'),
            $reversed->ledgerEntries()->where('direction', 'credit')->sum('amount_cents')
        );
    }

    public function test_economic_snapshot_ledger_and_reconciliation_records_are_immutable(): void
    {
        $snapshot = $this->financialControl->settle('booking', 801, $this->context(), 'booking:801:settle');

        $snapshot->merchandise_subtotal_cents = 1;
        try {
            $snapshot->save();
            self::fail('Economic snapshot values must not be editable.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('immutable', $exception->getMessage());
        }

        $entry = $snapshot->ledgerEntries()->firstOrFail();
        $entry->amount_cents++;
        try {
            $entry->save();
            self::fail('Ledger entries must be append-only.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('append-only', $exception->getMessage());
        }

        $run = $snapshot->reconciliationRuns()->firstOrFail();
        $run->status = 'out_of_balance';
        try {
            $run->save();
            self::fail('Reconciliation runs must be immutable.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('immutable', $exception->getMessage());
        }
    }

    public function test_settlement_visibility_is_isolated_by_role_and_party(): void
    {
        $first = $this->financialControl->settle('order', 901, $this->context(), 'order:901:settle');
        $second = $this->financialControl->settle('order', 902, $this->context([
            'customer_id' => 111,
            'business_id' => 222,
            'provider_id' => 333,
            'driver_id' => 444,
        ]), 'order:902:settle');

        self::assertSame([$first->id], $this->financialControl->visibleSettlements('business', 20)->pluck('id')->all());
        self::assertSame([$second->id], $this->financialControl->visibleSettlements('provider', 333)->pluck('id')->all());
        self::assertSame([$first->id], $this->financialControl->visibleSettlements('driver', 40)->pluck('id')->all());
        self::assertSame([$second->id], $this->financialControl->visibleSettlements('shopper', 111)->pluck('id')->all());
        self::assertCount(2, $this->financialControl->visibleSettlements('master_admin')->get());
        self::assertCount(0, $this->financialControl->visibleSettlements('unknown', 10)->get());
    }

    public function test_rule_revision_source_locks_exact_record_and_advances_max_version(): void
    {
        $source = file_get_contents(
            $this->root.'/app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzFinancialControlController.php'
        );

        self::assertStringContainsString('->whereKey($financialRule->getKey())', $source);
        self::assertStringContainsString('->lockForUpdate()', $source);
        self::assertStringContainsString("->max('version') + 1", $source);
        self::assertStringContainsString('Only the current active rule version can be updated.', $source);
    }

    public function test_financial_mutations_require_the_dedicated_manage_permission(): void
    {
        $controller = file_get_contents(
            $this->root.'/app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzFinancialControlController.php'
        );
        $manageMethod = substr($controller, strpos($controller, 'private function authorizeManage'));

        self::assertStringContainsString(
            "module_permission_check('urban_goodz_financial_control_manage')",
            $manageMethod
        );
        self::assertStringNotContainsString(
            "module_permission_check('urban_goodz_payments_manage')",
            $manageMethod
        );
    }

    private function context(array $overrides = []): array
    {
        return array_replace([
            'currency' => 'USD',
            'customer_id' => 10,
            'business_id' => 20,
            'provider_id' => 30,
            'driver_id' => 40,
            'zone_id' => 50,
            'service_type' => 'marketplace_delivery',
            'merchandise_subtotal_cents' => 10000,
            'delivery_charge_cents' => 2000,
            'miles_milli' => 5000,
            'package_count' => 2,
            'stop_count' => 3,
            'route_count' => 1,
            'hours_minutes' => 90,
            'return_count' => 1,
            'exception_count' => 1,
        ], $overrides);
    }

    private function rule(
        string $family,
        string $calculationType,
        int $amountCents = 0,
        int $rateBasisPoints = 0,
        string $scopeType = 'platform',
        ?string $scopeKey = null,
        ?string $serviceType = null,
        int $priority = 100,
        ?string $effectiveFrom = null,
        ?string $effectiveTo = null
    ): UrbanGoodzFinancialRule {
        return UrbanGoodzFinancialRule::create([
            'rule_key' => (string) Str::uuid(),
            'version' => 1,
            'name' => "{$family} {$calculationType}",
            'rule_family' => $family,
            'calculation_type' => $calculationType,
            'amount_cents' => $amountCents,
            'rate_basis_points' => $rateBasisPoints,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'service_type' => $serviceType,
            'priority' => $priority,
            'visibility_roles' => ['master_admin', 'admin'],
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'is_active' => true,
            'change_reason' => 'Test rule',
        ]);
    }

    private function runFinancialControlMigration(): void
    {
        /** @var object{up: callable} $migration */
        $migration = require $this->root
            .'/database/migrations/2026_07_30_210200_create_urban_goodz_financial_control_tables.php';
        $migration->up();

        self::assertTrue(Schema::hasTable('urban_goodz_financial_rules'));
    }

    private function loadFinancialControlSources(): void
    {
        foreach ([
            'app/Models/UrbanGoodzFinancialRule.php',
            'app/Models/UrbanGoodzFinancialLedgerEntry.php',
            'app/Models/UrbanGoodzReconciliationRun.php',
            'app/Models/UrbanGoodzFinancialSettlementSnapshot.php',
            'app/Services/UrbanGoodz/FinancialControl/FinancialControlService.php',
        ] as $source) {
            require_once $this->root.'/'.$source;
        }
    }

    private function authFactory(): AuthFactory
    {
        return new class implements AuthFactory
        {
            public function guard($name = null)
            {
                return new class implements Guard
                {
                    public function check()
                    {
                        return false;
                    }

                    public function guest()
                    {
                        return true;
                    }

                    public function user()
                    {
                        return null;
                    }

                    public function id()
                    {
                        return null;
                    }

                    public function validate(array $credentials = [])
                    {
                        return false;
                    }

                    public function hasUser()
                    {
                        return false;
                    }

                    public function setUser(Authenticatable $user)
                    {
                        return $this;
                    }
                };
            }

            public function shouldUse($name) {}
        };
    }
}
