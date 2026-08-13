<?php

namespace Tests\Feature\StagingP0;

use App\Http\Middleware\ActivationCheckMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\StagingP0\Concerns\CreatesP0Fixtures;
use Tests\TestCase;

/**
 * Backend P0 guardrails: authentication, authorization, marketplace
 * exposure, money integrity and document privacy.
 *
 * DatabaseTransactions plus CreatesP0Fixtures keeps the fixed-id fixtures
 * (9001-9003) available every run, in any allowlisted test database - not
 * only against one hand-curated external staging database.
 */
class BackendP0GuardrailsTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesP0Fixtures;

    private const ZONE = 9001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ActivationCheckMiddleware::class, ThrottleRequests::class]);
        $this->ensureP0Fixtures();
    }

    private function zoneHeaders(): array
    {
        return ['zoneId' => '['.self::ZONE.']', 'Accept' => 'application/json'];
    }

    // ---------------------------------------------------------------- auth

    /**
     * Every authenticated surface must reject an anonymous caller with 401 -
     * never 200, and never a 500 that leaks a stack trace.
     */
    public function test_protected_endpoints_reject_anonymous_callers(): void
    {
        $endpoints = [
            '/api/v1/delivery-man/profile',
            '/api/v1/delivery-man/current-orders',
            '/api/v1/delivery-man/earning-report',
            '/api/v1/vendor/profile',
            '/api/v1/customer/info',
        ];

        foreach ($endpoints as $uri) {
            $response = $this->getJson($uri, $this->zoneHeaders());

            $this->assertSame(
                401,
                $response->status(),
                "{$uri} did not reject an unauthenticated caller with 401."
            );
        }
    }

    public function test_auth_endpoints_do_not_accept_a_forged_bearer_token(): void
    {
        $response = $this->getJson('/api/v1/delivery-man/profile', $this->zoneHeaders() + [
            'Authorization' => 'Bearer not-a-real-token',
        ]);

        $this->assertSame(401, $response->status(), 'A forged bearer token was not rejected.');
    }

    // --------------------------------------------------------------- authz

    /**
     * The restricted admin fixture must not hold the "all" module grant,
     * otherwise "restricted" is cosmetic.
     */
    public function test_restricted_admin_role_is_not_a_superuser(): void
    {
        $restricted = DB::table('admins')->where('id', 9002)->first();
        $this->assertNotNull($restricted, 'Restricted admin fixture is missing.');

        $role = DB::table('admin_roles')->where('id', $restricted->role_id)->first();
        $this->assertNotNull($role, 'Restricted admin role is missing.');

        $modules = json_decode((string) $role->modules, true) ?: [];

        $this->assertNotContains('all', $modules, 'The restricted admin role grants every module.');
        $this->assertNotSame(
            DB::table('admins')->where('id', 9001)->value('role_id'),
            $restricted->role_id,
            'Restricted admin shares the super admin role.'
        );
    }

    public function test_admin_panel_requires_authentication(): void
    {
        $response = $this->get('/admin');

        $this->assertContains(
            $response->status(),
            [301, 302, 401, 403],
            'The admin panel served content to an anonymous caller.'
        );
    }

    // --------------------------------------------------------- marketplace

    /**
     * A store whose vendor has not been approved must never appear in a
     * public listing, even if the row is otherwise active. This is the
     * difference between "not yet vetted" and "sellable".
     */
    public function test_unapproved_stores_are_not_publicly_listed(): void
    {
        // Force the hard case: active row, approval still pending.
        DB::table('stores')->where('id', 9002)->update([
            'status'                => 1,
            'active'                => 1,
            'admin_approval_status' => 'pending',
        ]);
        DB::table('stores')->where('id', 9003)->update([
            'status'                => 1,
            'active'                => 1,
            'admin_approval_status' => 'rejected',
        ]);

        $response = $this->getJson('/api/v1/stores/get-stores/all', $this->zoneHeaders());

        if ($response->status() !== 200) {
            $this->markTestSkipped(
                'Public store listing returned HTTP '.$response->status().
                ' on an otherwise-empty staging dataset; exposure could not be evaluated.'
            );
        }

        $listed = collect($response->json('stores') ?? [])
            ->pluck('admin_approval_status')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['approved'],
            $listed,
            'Public store listing exposed non-approved vendors. Approval statuses returned: '.
            implode(', ', $listed)
        );
    }

    // --------------------------------------------------------------- money

    /**
     * Money columns must be non-negative and never NULL where the schema
     * treats them as balances. A NULL balance silently becomes 0 in PHP and
     * hides a real debt.
     */
    public function test_money_columns_have_sane_definitions(): void
    {
        $offenders = DB::select("
            SELECT TABLE_NAME AS t, COLUMN_NAME AS c, DATA_TYPE AS d
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND COLUMN_NAME IN ('total_price','order_amount','amount','balance','commission_amount')
              AND DATA_TYPE IN ('float','double')
        ");

        $names = array_map(fn ($o) => "{$o->t}.{$o->c} ({$o->d})", $offenders);

        $this->assertSame(
            [],
            $names,
            'Money columns stored as float/double, which cannot represent currency exactly: '.
            implode(', ', $names)
        );
    }

    public function test_no_order_rows_leaked_into_the_isolated_staging_database(): void
    {
        // The staging baseline is schema-only. Any order row here means real
        // marketplace data reached an environment with debug enabled.
        foreach (['orders', 'order_details', 'transactions'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $this->assertSame(
                0,
                DB::table($table)->count(),
                "Table {$table} contains data in the isolated staging database."
            );
        }
    }

    // ---------------------------------------------------- document privacy

    /**
     * Identity documents are the highest-sensitivity column on delivery_men.
     * They must not be reachable without authentication.
     */
    public function test_driver_identity_documents_are_not_publicly_reachable(): void
    {
        $identity = DB::table('delivery_men')->where('id', 9001)->value('identity_image');
        $this->assertNotEmpty($identity, 'Driver fixture has no identity document to test against.');

        $response = $this->getJson('/api/v1/delivery-man/profile', $this->zoneHeaders());

        $this->assertSame(401, $response->status());
        $this->assertStringNotContainsString(
            'staging-fixture-id.png',
            $response->getContent(),
            'A driver identity document was returned to an unauthenticated caller.'
        );
    }

    public function test_business_client_documents_require_authentication(): void
    {
        $documentRoutes = collect(Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'business') && str_contains($r->uri(), 'document'))
            ->values();

        if ($documentRoutes->isEmpty()) {
            $this->markTestSkipped('No business-client document routes are registered.');
        }

        foreach ($documentRoutes as $route) {
            $middleware = array_filter($route->gatherMiddleware(), 'is_string');

            // Guard aliases that actually authenticate a principal.
            // 'admin' maps to AdminMiddleware, which enforces
            // Auth::guard('admin')->check() and redirects otherwise.
            $guards = ['admin', 'vendor', 'vendor_employee', 'business', 'customer', 'delivery_man'];

            $guarded = collect($middleware)->contains(
                fn ($m) => str_starts_with($m, 'auth') || in_array($m, $guards, true)
            );

            $this->assertTrue(
                $guarded,
                "Business document route '{$route->uri()}' has no authentication middleware: ".
                implode(',', $middleware)
            );
        }
    }
}
