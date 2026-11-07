<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzBusinessClientUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Logistics-company (tenant) isolation: business portal, AI logistics, Order Anywhere
 * dispatch and the dispatcher portal.
 *
 * Company B signs in normally and then attacks company A's records by id. Each attack
 * must change nothing of A's (row snapshot) and must not disclose A's data. Owner
 * controls prove the same operations work for A, so "reject everyone" cannot pass.
 * Also covers the driver-pool rule: a dispatcher may only assign its own fleet,
 * Urban Goodz-owned drivers and marketplace-shared drivers.
 */
class LogisticsTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private object $a;
    private object $b;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->withoutMiddleware(\App\Http\Middleware\ActivationCheckMiddleware::class);

        $this->a = $this->company('a');
        $this->b = $this->company('b');
        $a = $this->a->client;

        $this->ids = [
            'route' => $this->insertRow('urban_goodz_dedicated_routes', [
                'business_client_id' => $a, 'route_name' => 'A-SECRET-ROUTE', 'status' => 'planned', 'total_packages' => 1,
                'scheduled_date' => now()->toDateString(),
            ]),
            'package' => $this->insertRow('urban_goodz_route_packages', [
                'business_client_id' => $a, 'dedicated_route_id' => null, 'tracking_id' => 'A-SECRET-TRK', 'barcode' => 'A-SECRET-BAR',
                'dropoff_name' => 'A-SECRET-NAME', 'dropoff_address' => '1 Secret Way', 'status' => 'exception',
            ]),
            'location' => $this->insertRow('urban_goodz_business_client_locations', [
                'business_client_id' => $a, 'name' => 'A-SECRET-LOCATION', 'is_active' => 1,
            ]),
            'user' => $this->insertRow('urban_goodz_business_client_users', [
                'business_client_id' => $a, 'first_name' => 'A-SECRET-USER', 'last_name' => 'x',
                'email' => 'a-staff-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('x'), 'role' => 'dispatcher',
                'is_active' => 1, 'status' => 'active',
            ]),
            'document' => $this->insertRow('urban_goodz_business_client_documents', [
                'business_client_id' => $a, 'title' => 'A-SECRET-DOC', 'document_type' => 'other', 'file_path' => 'x/a.pdf',
            ]),
            'invoice' => $this->insertRow('urban_goodz_client_invoices', [
                'business_client_id' => $a, 'invoice_number' => 'A-SECRET-INV', 'status' => 'issued',
            ]),
            'manifest' => $this->insertRow('urban_goodz_manifests', [
                'business_client_id' => $a, 'manifest_number' => 'A-SECRET-MAN', 'status' => 'open',
            ]),
            'load' => $this->insertRow('urban_goodz_load_board_loads', [
                'business_client_id' => $a, 'dispatch_company_id' => $a, 'load_number' => 'A-SECRET-LOAD', 'status' => 'available',
                'dispatch_status' => 'unassigned', 'pickup_state' => 'TX', 'delivery_state' => 'TX',
            ]),
            'dispatch' => $this->insertRow('ai_dispatches', [
                'business_client_id' => $a, 'status' => 'sent', 'source_type' => 'order_anywhere',
            ]),
            'copilot' => $this->insertRow('ai_copilot_recommendations', [
                'business_client_id' => $a, 'status' => 'pending', 'recommendation_type' => 'x', 'title' => 'A-SECRET-REC',
            ]),
        ];
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ----------------------------------------------------------------- fixtures

    private function company(string $tag): object
    {
        $client = $this->insertRow('urban_goodz_business_clients', [
            'company_name' => 'Company '.$tag, 'account_type' => 'dispatch_company', 'status' => 'approved',
            'email' => "co-{$tag}-".uniqid().'@urbangoodz.test', 'territory_states' => json_encode(['TX']),
        ]);
        $user = fn (string $role, string $name) => UrbanGoodzBusinessClientUser::find($this->insertRow('urban_goodz_business_client_users', [
            'business_client_id' => $client, 'first_name' => $name, 'last_name' => $tag,
            'email' => "u-{$tag}-{$name}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('password'),
            'role' => $role, 'is_active' => 1, 'status' => 'active',
        ]));

        return (object) [
            'client' => $client,
            'portal' => $user('owner_admin', 'portal'),
            'dispatch' => $user('dispatch_owner', 'dispatch'),
        ];
    }

    private function driver(string $tag, array $over = []): int
    {
        return $this->insertRow('delivery_men', array_merge([
            'f_name' => 'PICK'.$tag, 'l_name' => 'x', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "drv-{$tag}-".uniqid().'@urbangoodz.test', 'password' => 'x',
            'status' => 1, 'active' => 1, 'application_status' => 'approved', 'type' => 'zone_wise',
            'ownership_type' => 'urban_goodz', 'available_for_marketplace' => 0,
        ], $over));
    }

    private function snapshot(): array
    {
        $snap = [];
        foreach ([
            'urban_goodz_dedicated_routes' => 'route', 'urban_goodz_route_packages' => 'package',
            'urban_goodz_business_client_locations' => 'location', 'urban_goodz_business_client_users' => 'user',
            'urban_goodz_business_client_documents' => 'document', 'urban_goodz_client_invoices' => 'invoice',
            'urban_goodz_manifests' => 'manifest', 'urban_goodz_load_board_loads' => 'load',
            'ai_dispatches' => 'dispatch', 'ai_copilot_recommendations' => 'copilot',
        ] as $table => $key) {
            $row = DB::table($table)->where('id', $this->ids[$key])->first();
            $snap[$table] = $row ? (array) $row : null;
            if (is_array($snap[$table])) {
                unset($snap[$table]['updated_at']);
            }
        }

        return $snap;
    }

    private function actAs(object $user)
    {
        return $this->actingAs($user, 'business');
    }

    // ------------------------------------------------------------------ attacks

    private function writeAttacks(): array
    {
        $i = $this->ids;

        return [
            'update route' => ['POST', "/business/routes/{$i['route']}/update", ['route_name' => 'HACKED']],
            'delete route' => ['POST', "/business/routes/{$i['route']}/delete", []],
            'route manual order' => ['POST', "/business/routes/{$i['route']}/manual-order", ['order' => [$i['package']]]],
            'route restore original' => ['POST', "/business/routes/{$i['route']}/restore-original", []],
            'assign package to route' => ['POST', "/business/packages/{$i['package']}/assign", ['route_id' => $i['route']]],
            'update location' => ['POST', "/business/locations/{$i['location']}/update", ['name' => 'HACKED']],
            'deactivate location' => ['POST', "/business/locations/{$i['location']}/deactivate", []],
            'update user' => ['POST', "/business/users/{$i['user']}/update", ['first_name' => 'HACKED', 'last_name' => 'x', 'role' => 'owner_admin']],
            'deactivate user' => ['POST', "/business/users/{$i['user']}/deactivate", []],
            'delete document' => ['POST', "/business/documents/{$i['document']}/delete", []],
            'cancel load' => ['POST', "/business/load-board/{$i['load']}/cancel", []],
            'cancel ai dispatch' => ['POST', "/business/ai-logistics/dispatches/{$i['dispatch']}/cancel", []],
            'resend ai dispatch' => ['POST', "/business/ai-logistics/dispatches/{$i['dispatch']}/resend", []],
            'resolve exception' => ['POST', "/business/ai-logistics/exceptions/{$i['package']}/resolve", ['resolution' => 'resolved', 'notes' => 'HACKED']],
            'accept copilot rec' => ['POST', "/business/ai-logistics/copilot/{$i['copilot']}/accept", []],
            'dismiss copilot rec' => ['POST', "/business/ai-logistics/copilot/{$i['copilot']}/dismiss", []],
            'snooze copilot rec' => ['POST', "/business/ai-logistics/copilot/{$i['copilot']}/snooze", ['hours' => 4]],
        ];
    }

    public function test_company_b_cannot_modify_company_a_records_in_the_business_portal(): void
    {
        $before = $this->snapshot();
        $changed = [];

        $this->actAs($this->b->portal);
        foreach ($this->writeAttacks() as $label => [$method, $uri, $body]) {
            $response = $this->call($method, $uri, $body);
            $after = $this->snapshot();
            if ($after !== $before) {
                $diff = array_keys(array_filter($after, fn ($v, $k) => $v !== $before[$k], ARRAY_FILTER_USE_BOTH));
                $changed[] = "{$label} -> HTTP {$response->getStatusCode()} changed ".implode(',', $diff);
                $before = $after;
            }
        }

        $this->assertSame([], $changed, "Company B changed company A's data:\n".implode("\n", $changed));
    }

    public function test_company_b_cannot_read_company_a_records_in_the_business_portal(): void
    {
        $i = $this->ids;
        $reads = [
            "/business/routes/{$i['route']}", "/business/routes/{$i['route']}/edit", "/business/routes/{$i['route']}/packages",
            "/business/locations/{$i['location']}/edit", "/business/users/{$i['user']}/edit",
            "/business/documents/{$i['document']}/download", "/business/invoices/{$i['invoice']}",
            "/business/manifests/{$i['manifest']}", "/business/manifests/{$i['manifest']}/scan", "/business/manifests/{$i['manifest']}/packages",
            "/business/load-board/{$i['load']}", "/business/ai-logistics/load-board/{$i['load']}",
            "/business/ai-logistics/dispatches/{$i['dispatch']}",
        ];
        $markers = ['A-SECRET-ROUTE', 'A-SECRET-TRK', 'A-SECRET-NAME', 'A-SECRET-LOCATION', 'A-SECRET-USER', 'A-SECRET-DOC', 'A-SECRET-INV', 'A-SECRET-MAN', 'A-SECRET-LOAD'];

        $this->actAs($this->b->portal);
        $leaks = [];
        foreach ($reads as $uri) {
            $response = $this->get($uri);
            $body = $response->getContent();
            foreach ($markers as $marker) {
                if (str_contains($body, $marker)) {
                    $leaks[] = "{$uri} -> HTTP {$response->getStatusCode()} leaked {$marker}";
                }
            }
            if ($response->getStatusCode() === 200 && $uri !== '') {
                $leaks[] = "{$uri} -> HTTP 200 for a record the company does not own";
            }
        }

        $this->assertSame([], $leaks, "Company B read company A's data:\n".implode("\n", array_unique($leaks)));
    }

    public function test_company_a_can_still_use_its_own_records(): void
    {
        $i = $this->ids;
        $this->actAs($this->a->portal);

        foreach ([
            "/business/routes/{$i['route']}", "/business/locations/{$i['location']}/edit", "/business/users/{$i['user']}/edit",
            "/business/invoices/{$i['invoice']}", "/business/manifests/{$i['manifest']}", "/business/load-board/{$i['load']}",
            "/business/ai-logistics/dispatches/{$i['dispatch']}",
        ] as $uri) {
            $status = $this->get($uri)->getStatusCode();
            $this->assertContains($status, [200], "Owner could not open {$uri} (HTTP {$status})");
        }

        $this->call('POST', "/business/ai-logistics/copilot/{$i['copilot']}/dismiss");
        $this->assertNotSame('pending', DB::table('ai_copilot_recommendations')->where('id', $i['copilot'])->value('status'));

        $this->call('POST', "/business/locations/{$i['location']}/deactivate");
        $this->assertSame(0, (int) DB::table('urban_goodz_business_client_locations')->where('id', $i['location'])->value('is_active'));
    }

    // -------------------------------------------------------- order anywhere dispatch

    public function test_order_anywhere_dispatch_is_limited_to_the_companys_own_dispatches(): void
    {
        $other = $this->insertRow('ai_dispatches', ['business_client_id' => $this->b->client, 'status' => 'sent', 'source_type' => 'order_anywhere']);
        $unowned = $this->insertRow('ai_dispatches', ['business_client_id' => null, 'status' => 'sent', 'source_type' => 'order_anywhere']);
        $mine = $this->ids['dispatch'];

        $this->actAs($this->b->portal);
        $listB = $this->getJson('/business/order-anywhere-dispatch/dispatches')->assertOk()->json('data.data') ?? [];
        $idsB = collect($listB)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertContains($other, $idsB);
        $this->assertNotContains($mine, $idsB, "company A's dispatch is listed to company B");
        $this->assertNotContains($unowned, $idsB, 'an unowned dispatch is listed to a company');

        $this->getJson("/business/order-anywhere-dispatch/dispatches/{$mine}")->assertNotFound();
        $this->getJson("/business/order-anywhere-dispatch/dispatches/{$unowned}")->assertNotFound();
        $this->getJson("/business/order-anywhere-dispatch/dispatches/{$other}")->assertOk();
    }

    public function test_a_company_also_sees_dispatches_run_by_its_own_drivers(): void
    {
        $driver = $this->driver('own', ['business_client_id' => $this->b->client, 'ownership_type' => 'business_owned']);
        $run = $this->insertRow('ai_dispatches', ['business_client_id' => null, 'driver_id' => $driver, 'delivery_man_id' => $driver, 'status' => 'sent', 'source_type' => 'order_anywhere']);

        $this->actAs($this->b->portal);
        $this->getJson("/business/order-anywhere-dispatch/dispatches/{$run}")->assertOk();
        $this->actAs($this->a->portal);
        $this->getJson("/business/order-anywhere-dispatch/dispatches/{$run}")->assertNotFound();
    }

    // -------------------------------------------------------------- dispatcher portal

    public function test_company_b_dispatcher_cannot_touch_company_a_loads_routes_or_users(): void
    {
        $i = $this->ids;
        $before = $this->snapshot();
        $this->actAs($this->b->dispatch);

        $this->get("/business/dispatcher/loads/{$i['load']}")->assertNotFound();
        $this->get("/business/dispatcher/routes/{$i['route']}")->assertNotFound();
        $this->get("/business/dispatcher/users/{$i['user']}/edit")->assertNotFound();
        $this->call('PATCH', "/business/dispatcher/loads/{$i['load']}/status", ['status' => 'cancelled', 'dispatch_status' => 'cancelled']);
        $this->call('POST', "/business/dispatcher/loads/{$i['load']}/assign-driver", ['driver_id' => $this->driver('x')]);
        $this->call('POST', "/business/dispatcher/users/{$i['user']}/update", [
            'first_name' => 'HACKED', 'last_name' => 'x', 'email' => 'h@urbangoodz.test', 'role' => 'dispatch_manager', 'password' => 'hacked-password-1',
        ]);
        $this->call('POST', "/business/dispatcher/users/{$i['user']}/deactivate");

        $this->assertSame($before, $this->snapshot(), "Company B's dispatcher changed company A's data");
    }

    // ------------------------------------------------------------------ driver pool

    public function test_dispatcher_can_only_assign_drivers_the_company_may_use(): void
    {
        $own = $this->driver('own', ['business_client_id' => $this->a->client, 'ownership_type' => 'business_owned']);
        $ug = $this->driver('ug', ['ownership_type' => 'urban_goodz']);
        $shared = $this->driver('shared', ['ownership_type' => 'vendor_owned', 'vendor_id' => 77, 'available_for_marketplace' => 1]);
        $otherCompany = $this->driver('othercompany', ['business_client_id' => $this->b->client, 'ownership_type' => 'business_owned']);
        $vendorOwned = $this->driver('vendorowned', ['ownership_type' => 'vendor_owned', 'vendor_id' => 77, 'available_for_marketplace' => 0]);

        $this->actAs($this->a->dispatch);
        $page = $this->get("/business/dispatcher/loads/{$this->ids['load']}")->assertOk()->getContent();
        foreach (['PICKown', 'PICKug', 'PICKshared'] as $ok) {
            $this->assertStringContainsString($ok, $page, "the picker should offer {$ok}");
        }
        foreach (['PICKothercompany', 'PICKvendorowned'] as $leak) {
            $this->assertStringNotContainsString($leak, $page, "the picker exposes {$leak}");
        }

        // Forbidden drivers cannot be assigned even by posting their id directly.
        foreach ([$otherCompany, $vendorOwned] as $forbidden) {
            $this->call('POST', "/business/dispatcher/loads/{$this->ids['load']}/assign-driver", ['driver_id' => $forbidden]);
            $this->assertNotSame($forbidden, (int) DB::table('urban_goodz_load_board_loads')->where('id', $this->ids['load'])->value('assigned_driver_id'), 'assigned a driver the company must not use');
        }
    }

    public function test_dispatcher_user_password_reset_requires_a_minimum_length(): void
    {
        $this->actAs($this->a->dispatch);
        $before = DB::table('urban_goodz_business_client_users')->where('id', $this->ids['user'])->value('password');

        $this->call('POST', "/business/dispatcher/users/{$this->ids['user']}/update", [
            'first_name' => 'S', 'last_name' => 'x', 'email' => 's@urbangoodz.test', 'role' => 'dispatcher', 'password' => '1',
        ]);

        $this->assertSame($before, DB::table('urban_goodz_business_client_users')->where('id', $this->ids['user'])->value('password'), 'a 1-character password was accepted');
    }
}
