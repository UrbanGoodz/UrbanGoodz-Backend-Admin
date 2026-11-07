<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Admin portal page sweep (opt-in: UG_ADMIN_SWEEP=1).
 *
 * Logs in as the primary administrator and requests every parameter-free
 * admin GET page, recording status codes and server errors to
 * UG_ADMIN_SWEEP_OUT (default storage/logs/admin-sweep.json). Writes are rolled
 * back by DatabaseTransactions and state-changing looking URLs are skipped.
 *
 * The assertion is on server faults only: an admin page must never answer 5xx.
 * Known faults are listed in tests/Fixtures/known-admin-page-faults.txt so the
 * list can only shrink (same ratchet pattern as known-broken-routes.txt).
 */
class AdminPortalPageSweepTest extends TestCase
{
    use DatabaseTransactions;

    private const SKIP = '/logout|clean|clear|reset|delete|destroy|migrate|sync|restart|install|download|export|import|backup|webhook/i';

    public function test_no_parameter_free_admin_page_returns_a_server_error(): void
    {
        if (getenv('UG_ADMIN_SWEEP') !== '1') {
            $this->markTestSkipped('Opt-in sweep: set UG_ADMIN_SWEEP=1.');
        }

        $admin = Admin::create([
            'f_name' => 'Sweep', 'l_name' => 'Admin',
            'email' => 'sweep-admin-'.uniqid().'@urban-goodz.test',
            'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1,
            'is_logged_in' => 1, 'login_remember_token' => 'sweep-token',
        ]);

        // Module-scoped pages (items, POS, categories, banners...) need a
        // selected module; the sweep database starts with none.
        $module = \App\Models\Module::create([
            'module_name' => 'Sweep Ecommerce', 'module_type' => 'ecommerce',
            'status' => 1, 'theme_id' => 1, 'stores_count' => 0, 'all_zone_service' => 1,
        ]);

        $this->actingAs($admin, 'admin')->withSession([
            'login_remember_token' => 'sweep-token',
            'current_module' => $module->id,
        ]);

        $results = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'admin') || str_contains($uri, '{')
                || ! in_array('GET', $route->methods(), true) || preg_match(self::SKIP, $uri)) {
                continue;
            }

            try {
                $response = $this->get('/'.$uri);
                $status = $response->getStatusCode();
                $detail = $status >= 500 ? mb_substr((string) ($response->exception?->getMessage() ?? ''), 0, 200) : '';
            } catch (\Throwable $e) {
                $status = 599;
                $detail = mb_substr(get_class($e).': '.$e->getMessage(), 0, 200);
            }
            $results[] = ['uri' => $uri, 'status' => $status, 'detail' => $detail];
        }

        $out = getenv('UG_ADMIN_SWEEP_OUT') ?: storage_path('logs/admin-sweep.json');
        file_put_contents($out, json_encode($results, JSON_PRETTY_PRINT));

        $known = array_filter(array_map('trim', file(base_path('tests/Fixtures/known-admin-page-faults.txt'), FILE_IGNORE_NEW_LINES) ?: []));
        $faults = array_values(array_filter($results, fn ($r) => $r['status'] >= 500 && ! in_array($r['uri'], $known, true)));

        $this->assertSame([], array_column($faults, 'uri'), 'Admin pages answering 5xx (fix them or, only if unavoidable, list them in tests/Fixtures/known-admin-page-faults.txt): '.json_encode($faults));
    }
}
