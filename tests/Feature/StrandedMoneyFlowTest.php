<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedConsent;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedService;
use App\Models\UrbanGoodzStrandedVerification;
use App\Models\User;
use App\Domain\Stranded\Payments\UrbanGoodzStrandedPaymentService;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stranded money flows. Stripe itself is not reachable from tests, so the provider call
 * is replaced at its single seam (UrbanGoodzStrandedPaymentService::stripe); every other
 * line -- claim, ledger, escrow, status -- is the real code.
 *
 * Found during STRANDED certification:
 *  - A declined card followed by a good one charged the customer at Stripe and then
 *    crashed on the ledger's unique key, leaving the request unpaid.
 *  - The fee check was not atomic, so a double submit could charge twice.
 *  - The customer app closes a request with `completed`, which never released escrow.
 */
class StrandedMoneyFlowTest extends TestCase
{
    use DatabaseTransactions;

    private const LAT = 40.7580;
    private const LNG = -73.9855;

    protected function setUp(): void
    {
        parent::setUp();
        if (UrbanGoodzStrandedService::count() === 0) {
            $this->seed(\Database\Seeders\UrbanGoodzStrandedServiceSeeder::class);
        }
    }

    private function user(string $tag): User
    {
        return User::create([
            'f_name' => 'Money', 'l_name' => $tag, 'email' => "money-{$tag}-".Str::random(8).'@urbangoodz.test',
            'phone' => '1'.random_int(1000000000, 1999999999), 'password' => bcrypt('x'),
        ]);
    }

    private function request(User $user, array $overrides = []): UrbanGoodzStrandedRequest
    {
        $service = UrbanGoodzStrandedService::where('slug', 'dead-battery')->firstOrFail();

        return UrbanGoodzStrandedRequest::create(array_merge([
            'uuid' => (string) Str::uuid(), 'request_number' => 'ST-M-'.strtoupper(Str::random(6)),
            'user_id' => $user->id, 'service_id' => $service->id, 'service_slug' => $service->slug,
            'status' => 'draft', 'latitude' => self::LAT, 'longitude' => self::LNG, 'allow_samaritans' => true,
            'help_request_fee_minor' => 500, 'help_request_fee_status' => 'unpaid', 'currency' => 'USD',
            'broadcast_radius_miles' => 5,
        ], $overrides));
    }

    /** Payment service whose Stripe calls are scripted. */
    private function payments(array $script): object
    {
        $svc = new class(app(UrbanGoodzStrandedDispatcher::class), app(\App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier::class), app(\App\Domain\Stranded\Support\MoniqueOperationsAlertService::class)) extends UrbanGoodzStrandedPaymentService {
            public array $script = [];
            public array $calls = [];

            protected function stripe(string $method, string $path, array $fields = [], ?string $idempotencyKey = null): array
            {
                $this->calls[] = ['key' => $idempotencyKey, 'fields' => $fields];

                return array_shift($this->script) ?? ['status' => 'succeeded', 'id' => 'pi_default'];
            }
        };
        $svc->script = $script;
        return $svc;
    }

    public function test_a_declined_card_then_a_good_card_charges_once_and_marks_the_request_paid(): void
    {
        $request = $this->request($this->user('retry'));
        $svc = $this->payments([
            ['status' => 'requires_payment_method', 'id' => 'pi_declined', 'error' => ['message' => 'Your card was declined.']],
            ['status' => 'succeeded', 'id' => 'pi_ok'],
        ]);

        try {
            $svc->payFeeAndBroadcast($request, 'pm_bad');
            $this->fail('the declined card must surface as an error');
        } catch (\RuntimeException $e) {
            $this->assertSame('Your card was declined.', $e->getMessage());
        }
        $this->assertSame('unpaid', $request->fresh()->help_request_fee_status, 'a decline must reopen the fee for retry');

        $result = $svc->payFeeAndBroadcast($request->fresh(), 'pm_good');

        $this->assertTrue($result['paid']);
        $this->assertSame('paid', $request->fresh()->help_request_fee_status);
        $calls = $svc->calls;
        $this->assertCount(2, $calls);
        $this->assertNotSame($calls[0]['key'], $calls[1]['key'], 'each attempt needs its own provider idempotency key');
        $this->assertSame(2, UrbanGoodzPaymentTransaction::where('payable_id', $request->id)->where('transaction_type', 'fee')->count());
        $this->assertSame(1, UrbanGoodzPaymentTransaction::where('payable_id', $request->id)->where('internal_status', 'completed')->count());
    }

    public function test_a_second_submission_while_the_first_is_processing_never_reaches_the_provider(): void
    {
        $request = $this->request($this->user('double'), ['help_request_fee_status' => 'processing']);
        $svc = $this->payments([]);

        $this->expectException(\RuntimeException::class);
        try {
            $svc->payFeeAndBroadcast($request, 'pm_x');
        } finally {
            $this->assertSame([], $svc->calls, 'the card must not be charged while another attempt holds the claim');
            $this->assertSame('processing', $request->fresh()->help_request_fee_status);
        }
    }

    public function test_a_provider_outage_reopens_the_fee(): void
    {
        $request = $this->request($this->user('outage'));
        $svc = new class(app(UrbanGoodzStrandedDispatcher::class), app(\App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier::class), app(\App\Domain\Stranded\Support\MoniqueOperationsAlertService::class)) extends UrbanGoodzStrandedPaymentService {
            protected function stripe(string $method, string $path, array $fields = [], ?string $idempotencyKey = null): array
            {
                throw new \RuntimeException('Could not reach the payment provider.');
            }
        };

        try {
            $svc->payFeeAndBroadcast($request, 'pm_x');
            $this->fail('expected failure');
        } catch (\RuntimeException) {
        }

        $this->assertSame('unpaid', $request->fresh()->help_request_fee_status);
    }

    public function test_closing_with_completed_releases_escrow_exactly_once_and_counts_the_job_once(): void
    {
        $customer = $this->user('close');
        UrbanGoodzStrandedVerification::create(['user_id' => $customer->id, 'role' => 'customer', 'status' => 'approved', 'license_last_four' => '1111', 'license_expires_on' => now()->addYear()->toDateString(), 'full_name' => 'T']);
        foreach (UrbanGoodzStrandedSafety::requiredDocumentsFor('customer') as $doc) {
            UrbanGoodzStrandedConsent::create(['user_id' => $customer->id, 'role' => 'customer', 'document' => $doc, 'version' => UrbanGoodzStrandedSafety::VERSIONS[$doc], 'accepted_at' => now()]);
        }
        $helper = $this->user('helper');
        UrbanGoodzStrandedResponder::create([
            'user_id' => $helper->id, 'responder_type' => 'samaritan', 'is_online' => true, 'last_latitude' => self::LAT + 0.02,
            'last_longitude' => self::LNG, 'last_seen_at' => now(), 'max_travel_miles' => 25, 'rating' => 4.8, 'trust_score' => 90, 'completed_jobs' => 3,
        ]);
        $request = $this->request($customer, ['help_request_fee_status' => 'paid']);
        app(UrbanGoodzStrandedDispatcher::class)->broadcast($request);
        $offer = UrbanGoodzStrandedOffer::where('request_id', $request->id)->firstOrFail();
        $offer->update(['status' => 'accepted', 'response_mode' => 'paid', 'requested_amount_minor' => 3000, 'responded_at' => now()]);

        // Selecting a responder now authorises a real card hold (covered by the payment-service
        // tests). Here the hold is already in place, and the release is observed rather than run:
        // what this test guards is the CONTROLLER's close path, not Stripe.
        $request->update(['selected_offer_id' => $offer->id, 'status' => 'assigned', 'escrow_status' => 'held']);
        $releases = new class(app(UrbanGoodzStrandedDispatcher::class), app(\App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier::class), app(\App\Domain\Stranded\Support\MoniqueOperationsAlertService::class)) extends UrbanGoodzStrandedPaymentService {
            public int $count = 0;

            public function releaseEscrow(UrbanGoodzStrandedRequest $request): array
            {
                $this->count++;
                $request->update(['escrow_status' => 'released', 'escrow_released_at' => now()]);

                return ['released' => true, 'reason' => null, 'amount_minor' => 0];
            }
        };
        $this->app->instance(UrbanGoodzStrandedPaymentService::class, $releases);

        $this->actingAs($customer, 'api')->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'completed'])->assertOk();

        $done = $request->fresh();
        $this->assertSame('completed', $done->status);
        $this->assertSame('released', $done->escrow_status, 'the app closes with `completed`; the helper must still be paid');
        $this->assertNotNull($done->customer_confirmed_at);

        // A double tap / second device.
        $this->actingAs($customer, 'api')->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'completed'])->assertStatus(409);
        $this->actingAs($customer, 'api')->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'confirmed'])->assertStatus(409);

        $this->assertSame(1, $releases->count, 'escrow must be released exactly once');
        $this->assertSame(4, (int) UrbanGoodzStrandedResponder::where('user_id', $helper->id)->value('completed_jobs'), 'the job must be counted once');
        $this->assertNull(UrbanGoodzStrandedResponder::where('user_id', $helper->id)->value('active_request_id'));
    }

    public function test_active_request_lets_a_customer_resume_and_points_at_the_next_step(): void
    {
        $customer = $this->user('resume');
        $other = $this->user('resume-other');
        $this->actingAs($customer, 'api')->getJson('/api/v1/urban-goodz/stranded/requests/active')
            ->assertOk()->assertJsonPath('active', false);

        $unpaid = $this->request($customer);
        $this->request($other); // someone else's request must never surface

        $this->actingAs($customer, 'api')->getJson('/api/v1/urban-goodz/stranded/requests/active')
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('next_step', 'pay_fee')
            ->assertJsonPath('data.uuid', $unpaid->uuid);

        $unpaid->update(['help_request_fee_status' => 'paid']);
        $this->actingAs($customer, 'api')->getJson('/api/v1/urban-goodz/stranded/requests/active')
            ->assertJsonPath('next_step', 'broadcast');

        $unpaid->update(['status' => 'cancelled']);
        $this->actingAs($customer, 'api')->getJson('/api/v1/urban-goodz/stranded/requests/active')
            ->assertJsonPath('active', false);

        $this->actingAs($customer, 'api')->getJson('/api/v1/urban-goodz/stranded/requests')
            ->assertOk()->assertJsonPath('total_size', 1);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/urban-goodz/stranded/requests/active')->assertUnauthorized();
    }
}
