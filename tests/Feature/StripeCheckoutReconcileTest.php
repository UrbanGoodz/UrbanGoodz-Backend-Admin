<?php

namespace Tests\Feature;

use App\Http\Controllers\StripePaymentController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

if (! function_exists('ug_test_reconcile_success_hook')) {
    function ug_test_reconcile_success_hook($data): void
    {
        $GLOBALS['ug_reconcile_hook_calls'][] = $data->id;
    }
}

class StripeCheckoutReconcileTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['ug_reconcile_hook_calls'] = [];
    }

    private function payment(array $extra = ['stripe_session_id' => 'cs_test_1'], int $isPaid = 0): string
    {
        $id = (string) Str::uuid();
        DB::table('payment_requests')->insert([
            'id' => $id, 'payment_amount' => 12.5, 'currency_code' => 'USD', 'is_paid' => $isPaid,
            'success_hook' => 'Tests\\Feature\\ug_test_reconcile_success_hook', 'additional_data' => json_encode($extra),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function fakeStripe(string $status, string $paymentStatus = 'paid'): void
    {
        $session = (object) ['status' => $status, 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_test_1'];
        $fake = new class(new \App\Models\PaymentRequest, $session) extends StripePaymentController {
            private $fakeSession;

            public function __construct(\App\Models\PaymentRequest $payment, $session)
            {
                parent::__construct($payment);
                $this->fakeSession = $session;
            }

            public function retrieveSession(string $sessionId)
            {
                return $this->fakeSession;
            }
        };
        $this->app->instance(StripePaymentController::class, $fake);
    }

    public function test_a_paid_session_the_customer_never_returned_for_is_completed_once(): void
    {
        $id = $this->payment();
        $this->fakeStripe('complete');

        $this->artisan('payments:reconcile-stripe-checkout')->assertExitCode(0);
        $this->artisan('payments:reconcile-stripe-checkout')->assertExitCode(0); // second run must change nothing

        $row = DB::table('payment_requests')->where('id', $id)->first();
        $this->assertSame(1, (int) $row->is_paid);
        $this->assertSame('pi_test_1', $row->transaction_id);
        $this->assertCount(1, $GLOBALS['ug_reconcile_hook_calls'], 'the success hook runs exactly once');
    }

    public function test_an_unpaid_session_is_left_alone(): void
    {
        $id = $this->payment();
        $this->fakeStripe('open', 'unpaid');

        $this->artisan('payments:reconcile-stripe-checkout')->assertExitCode(0);

        $this->assertSame(0, (int) DB::table('payment_requests')->where('id', $id)->value('is_paid'));
        $this->assertSame([], $GLOBALS['ug_reconcile_hook_calls']);
    }

    public function test_completing_an_already_paid_payment_does_not_rerun_the_hook(): void
    {
        $id = $this->payment(['stripe_session_id' => 'cs_test_2'], 1);
        $controller = app(StripePaymentController::class);

        $this->assertFalse($controller->completePaidSession($id, (object) ['payment_intent' => 'pi_x']));
        $this->assertSame([], $GLOBALS['ug_reconcile_hook_calls']);
    }

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $id = $this->payment();
        $this->fakeStripe('complete');

        $this->artisan('payments:reconcile-stripe-checkout', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, (int) DB::table('payment_requests')->where('id', $id)->value('is_paid'));
    }
}
