<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AiChiefOfStaffController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/** The chief-of-staff page used to answer with invented figures (42 orders, 99.8% uptime...). It must not. */
class ChiefOfStaffRealNumbersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_query_never_returns_the_old_invented_figures(): void
    {
        $controller = app(AiChiefOfStaffController::class);
        foreach (['how many orders are active', 'release update status', 'fashion fit sizing today', 'how are we doing'] as $q) {
            $reply = $controller->query(Request::create('/x', 'POST', ['prompt' => $q]))->getData(true)['reply'];
            foreach (['42 active orders', '24.5 minutes', '31 photo sizing', '99.8%', 'v1.3.0'] as $fake) {
                $this->assertStringNotContainsString($fake, $reply, "invented text came back for: {$q}");
            }
        }
    }
}
