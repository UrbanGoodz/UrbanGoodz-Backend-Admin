<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzBusinessClientUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Every parameter-free business-portal and dispatcher-portal page must render for a
 * brand-new dispatch company: approved, territory configured, and NO data yet (no
 * locations, routes, drivers or loads). That first-run state is exactly where the
 * route-creation page and the dispatcher territory filter used to answer HTTP 500.
 */
class LogisticsPortalPagesRenderTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_no_business_or_dispatcher_page_errors_for_a_new_company(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->withoutMiddleware(\App\Http\Middleware\ActivationCheckMiddleware::class);

        $client = $this->insertRow('urban_goodz_business_clients', [
            'company_name' => 'Brand New Co', 'account_type' => 'dispatch_company', 'status' => 'approved',
            'email' => 'new-'.uniqid().'@urbangoodz.test', 'territory_states' => json_encode(['TX', 'OK']),
        ]);
        $user = fn (string $role) => UrbanGoodzBusinessClientUser::find($this->insertRow('urban_goodz_business_client_users', [
            'business_client_id' => $client, 'first_name' => 'N', 'last_name' => $role,
            'email' => $role.'-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'role' => $role, 'is_active' => 1, 'status' => 'active',
        ]));
        $portal = $user('owner_admin');
        $dispatcher = $user('dispatch_owner');

        $errors = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'business') || str_contains($uri, '{')
                || ! in_array('GET', $route->methods(), true)
                || preg_match('/logout|download|export|login|forgot|reset/', $uri)) {
                continue;
            }

            $this->actingAs(str_contains($uri, 'dispatcher') ? $dispatcher : $portal, 'business');
            $checked++;
            try {
                $response = $this->get('/'.$uri);
                if ($response->getStatusCode() >= 500) {
                    $errors[] = "{$uri} -> HTTP {$response->getStatusCode()}: ".mb_substr((string) $response->exception?->getMessage(), 0, 140);
                }
            } catch (\Throwable $e) {
                $errors[] = "{$uri} -> ".get_class($e).': '.mb_substr($e->getMessage(), 0, 140);
            }
        }

        $this->assertGreaterThan(30, $checked, 'Page discovery found too few business/dispatcher routes.');
        $this->assertSame([], $errors, "Pages that fail for a new company:\n".implode("\n", $errors));
    }
}
