<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OrderAnywhereRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression coverage for the Order Anywhere Admin request detail page.
 *
 * Production returned HTTP 500 on /admin/urban-goodz/order-anywhere/2 with
 * `Attempt to read property "issued_at" on null`. The deployed Blade had the
 * card "Issued"/"Receipt" blocks sitting inside the
 * `@if(!$cardRequest || ...)` branch, so they dereferenced $cardRequest in
 * exactly the case where it is null - any request with no card yet issued.
 *
 * These tests lock the contract: a request without a card request renders 200,
 * a missing record returns a controlled 404 rather than 500, and the detail
 * never leaks raw exception output.
 */
class AdminOrderAnywhereDetailTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::firstOrCreate(
            ['email' => 'order-anywhere-test-admin@urbangoodz.com'],
            [
                'f_name' => 'Order Anywhere',
                'l_name' => 'Test Admin',
                'phone' => '1230000097',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->admin->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();
    }

    private function makeRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-TEST-'.uniqid(),
            'customer_name' => 'Test Customer',
            'customer_phone' => '5550000000',
            'store_vendor_name' => 'Test Store',
            'request_details' => 'Two boxes of nitrile gloves, size large.',
            'quantity' => 2,
            'status' => 'shopping',
        ], $overrides));
    }

    /**
     * The exact production scenario: a request whose card has never been
     * issued, so the controller passes $cardRequest = null.
     */
    public function test_detail_renders_when_no_card_request_exists(): void
    {
        $record = $this->makeRequest();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $record->id));

        $response->assertOk();
        $response->assertSee($record->request_number, false);
    }

    public function test_detail_renders_for_captured_payment_state(): void
    {
        $record = $this->makeRequest([
            'status' => 'shopping',
            'captured_amount' => 125.50,
            'authorized_amount' => 125.50,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $record->id))
            ->assertOk();
    }

    public function test_detail_renders_for_pending_review_state(): void
    {
        $record = $this->makeRequest(['status' => 'pending_review']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $record->id))
            ->assertOk();
    }

    public function test_detail_renders_with_missing_optional_relationships(): void
    {
        // No vendor, no driver, no order, no customer id - all optional.
        $record = $this->makeRequest([
            'customer_id' => null,
            'order_id' => null,
            'store_vendor_name' => null,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $record->id))
            ->assertOk();
    }

    public function test_missing_record_returns_404_not_500(): void
    {
        $missingId = (int) (OrderAnywhereRequest::max('id') ?? 0) + 99999;

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $missingId))
            ->assertNotFound();
    }

    public function test_detail_never_leaks_raw_exception_output(): void
    {
        $record = $this->makeRequest();

        $content = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $record->id))
            ->assertOk()
            ->getContent();

        foreach ([
            'SQLSTATE', 'Whoops', 'ErrorException', 'Stack trace',
            'Attempt to read property', 'Trying to access', '<?php', '@endif',
        ] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Detail leaked '{$marker}'.");
        }
    }

    public function test_list_renders_and_every_visible_detail_link_resolves(): void
    {
        $this->makeRequest();
        $this->makeRequest(['status' => 'pending_review']);

        $list = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.index'));
        $list->assertOk();

        // Every request currently visible must open without a server error.
        foreach (OrderAnywhereRequest::query()->latest()->limit(25)->get() as $record) {
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.urban-goodz.order-anywhere.show', $record->id))
                ->assertOk();
        }
    }

    public function test_public_request_number_maps_to_the_correct_record(): void
    {
        $a = $this->makeRequest();
        $b = $this->makeRequest();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.order-anywhere.show', $a->id))
            ->assertOk()
            ->assertSee($a->request_number, false)
            ->assertDontSee($b->request_number, false);
    }

    /**
     * Structural guard for the exact production defect: once the Blade enters
     * the `@if(!$cardRequest || ...)` branch, $cardRequest may be null, so
     * nothing inside that branch may dereference it except through the
     * short-circuited `!$cardRequest ||` guard itself.
     */
    public function test_blade_never_dereferences_card_request_in_the_null_branch(): void
    {
        $view = dirname(__DIR__, 2).'/resources/views/admin-views/urban-goodz/order-anywhere/show.blade.php';
        $lines = file($view, FILE_IGNORE_NEW_LINES);

        $branchStart = null;
        foreach ($lines as $i => $line) {
            if (str_contains($line, '@if(!$cardRequest')) {
                $branchStart = $i;
                break;
            }
        }
        $this->assertNotNull($branchStart, 'Expected a !$cardRequest branch in the Order Anywhere detail view.');

        $offenders = [];
        foreach (array_slice($lines, $branchStart + 1) as $offset => $line) {
            if (str_contains($line, '$cardRequest->')) {
                $offenders[] = ($branchStart + 2 + $offset).': '.trim($line);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Unguarded \$cardRequest dereference inside the null branch:\n".implode("\n", $offenders)
        );
    }

    public function test_unauthenticated_actor_is_denied(): void
    {
        $record = $this->makeRequest();

        $this->get(route('admin.urban-goodz.order-anywhere.show', $record->id))
            ->assertRedirect();
    }
}
