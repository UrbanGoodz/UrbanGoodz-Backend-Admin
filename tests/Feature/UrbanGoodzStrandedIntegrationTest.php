<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedConsent;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedService;
use App\Models\UrbanGoodzStrandedVerification;
use App\Services\UrbanGoodz\UrbanGoodzStrandedPaymentService;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSafety;
use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end behaviour for Urban Goodz Stranded.
 *
 * The companion E2E test proves the routes exist and answer. This one proves
 * they do the right thing: that the safety gate actually blocks, that a tow
 * never reaches a community member, that selecting a responder twice cannot
 * assign two people to one rescue, and that confirming a job twice does not
 * pay twice.
 *
 * NOTHING HERE TOUCHES STRIPE. The addon_settings row on a developer machine
 * can be live-mode with real credentials, so a test that reached the network
 * could move real money. Every payment assertion below exercises a path that
 * returns before the HTTP call: a waived (zero) fee, an already-paid request,
 * or escrow release, which is a ledger movement rather than a charge.
 */
class UrbanGoodzStrandedIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    /** A service community members may answer. */
    private const SAMARITAN_SERVICE = 'dead-battery';

    /** A service they may never answer, no matter what the client sends. */
    private const PROFESSIONAL_SERVICE = 'tow-truck';

    /** Times Square. Chosen only because it is unambiguous. */
    private const LAT = 40.7580;
    private const LNG = -73.9855;

    // ------------------------------------------------------------------ setup

    private function user(string $tag): User
    {
        return User::create([
            'f_name' => 'Stranded',
            'l_name' => ucfirst($tag),
            'email' => 'stranded-' . $tag . '-' . Str::random(8) . '@urbangoodz.test',
            'phone' => '1' . random_int(1000000000, 1999999999),
            'password' => bcrypt('not-a-production-password'),
        ]);
    }

    /** A user who has cleared the safety gate for the given role. */
    private function verified(string $tag, string $role = UrbanGoodzStrandedVerification::ROLE_CUSTOMER): User
    {
        $user = $this->user($tag);

        UrbanGoodzStrandedVerification::create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
            'license_last_four' => '4321',
            'license_expires_on' => now()->addYears(3)->toDateString(),
            'full_name' => 'Stranded Tester',
        ]);

        foreach (UrbanGoodzStrandedSafety::requiredDocumentsFor($role) as $document) {
            UrbanGoodzStrandedConsent::create([
                'user_id' => $user->id,
                'role' => $role,
                'document' => $document,
                'version' => UrbanGoodzStrandedSafety::VERSIONS[$document],
                'accepted_at' => now(),
            ]);
        }

        return $user;
    }

    private function service(string $slug): UrbanGoodzStrandedService
    {
        $service = UrbanGoodzStrandedService::where('slug', $slug)->first();
        $this->assertNotNull($service, "Stranded service '{$slug}' is not seeded.");

        return $service;
    }

    /**
     * An online responder sitting $miles due north of the request.
     * A degree of latitude is ~69 miles, which is close enough to place a
     * responder inside or outside a radius deliberately.
     */
    private function responder(string $type, float $miles, array $overrides = []): UrbanGoodzStrandedResponder
    {
        return UrbanGoodzStrandedResponder::create(array_merge([
            'user_id' => $this->user('responder')->id,
            'responder_type' => $type,
            'is_online' => true,
            'last_latitude' => self::LAT + ($miles / 69.0),
            'last_longitude' => self::LNG,
            'last_seen_at' => now(),
            'max_travel_miles' => 25,
            'rating' => 4.8,
            'trust_score' => 90,
            'completed_jobs' => 12,
        ], $overrides));
    }

    /** A request already past the gate, sitting ready to broadcast. */
    private function request(User $user, string $slug = self::SAMARITAN_SERVICE, array $overrides = []): UrbanGoodzStrandedRequest
    {
        $service = $this->service($slug);

        return UrbanGoodzStrandedRequest::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'request_number' => 'ST-TEST-' . strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'service_id' => $service->id,
            'service_slug' => $service->slug,
            'status' => 'draft',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'allow_samaritans' => (bool) $service->samaritan_eligible,
            'help_request_fee_minor' => 500,
            'help_request_fee_status' => 'paid',
            'currency' => 'USD',
            'broadcast_radius_miles' => UrbanGoodzStrandedSettings::radiusLadder()[0],
        ], $overrides));
    }

    private function dispatcher(): UrbanGoodzStrandedDispatcher
    {
        return app(UrbanGoodzStrandedDispatcher::class);
    }

    // ------------------------------------------- 1. customer flow & validation

    public function test_raising_a_request_requires_a_cleared_safety_gate(): void
    {
        $response = $this->actingAs($this->user('unverified'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ]);

        $response->assertStatus(403)->assertJsonPath('code', 'verification_required');
    }

    public function test_a_pending_licence_does_not_clear_the_gate(): void
    {
        $user = $this->user('pending');

        UrbanGoodzStrandedVerification::create([
            'user_id' => $user->id,
            'role' => UrbanGoodzStrandedVerification::ROLE_CUSTOMER,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'verification_pending');
    }

    public function test_an_approved_but_expired_licence_does_not_clear_the_gate(): void
    {
        $user = $this->user('expired');

        UrbanGoodzStrandedVerification::create([
            'user_id' => $user->id,
            'role' => UrbanGoodzStrandedVerification::ROLE_CUSTOMER,
            'status' => 'approved',
            'license_expires_on' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'verification_expired');
    }

    public function test_consent_is_required_even_with_an_approved_licence(): void
    {
        $user = $this->user('noconsent');

        UrbanGoodzStrandedVerification::create([
            'user_id' => $user->id,
            'role' => UrbanGoodzStrandedVerification::ROLE_CUSTOMER,
            'status' => 'approved',
            'license_expires_on' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'consent_required');
    }

    public function test_request_payload_attributes_are_validated(): void
    {
        $this->actingAs($this->verified('badpayload'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => 999,
                'longitude' => -73.9855,
                'passenger_count' => 900,
                'safety_status' => 'on_fire',
                'photos' => ['a', 'b', 'c', 'd', 'e', 'f', 'g'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'passenger_count', 'safety_status', 'photos'], 'errors');
    }

    public function test_a_verified_customer_can_raise_a_request_carrying_its_full_detail(): void
    {
        $response = $this->actingAs($this->verified('happy'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'address' => '1 Test Way',
                'vehicle_make' => 'Rivian',
                'vehicle_model' => 'R1T',
                'passenger_count' => 2,
                'safety_status' => 'unsafe_location',
                'is_emergency' => true,
                'notes' => 'Hazards on, parked on the shoulder.',
            ]);

        $response->assertStatus(201)
            // Created as a draft on purpose: the fee is what buys the
            // broadcast, so nothing goes out to responders yet.
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_emergency', true)
            ->assertJsonPath('data.safety_status', 'unsafe_location');

        $stranded = UrbanGoodzStrandedRequest::where('uuid', $response->json('data.uuid'))->first();

        $this->assertNotNull($stranded);
        $this->assertSame('Rivian', $stranded->vehicle_make);
        $this->assertSame(2, $stranded->passenger_count);
        $this->assertNull($stranded->broadcast_at, 'A draft request must not have been broadcast.');
    }

    public function test_a_tow_can_never_be_opened_to_samaritans_even_when_the_client_asks(): void
    {
        $response = $this->actingAs($this->verified('tow'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::PROFESSIONAL_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                // The client explicitly asks for community help. The service
                // is not eligible, so the server must overrule it.
                'allow_samaritans' => true,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.allow_samaritans', false);
    }

    public function test_an_unknown_service_is_rejected(): void
    {
        $this->actingAs($this->verified('unknown'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => 'teleportation',
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ])
            ->assertStatus(404);
    }

    public function test_the_fee_recorded_on_the_request_is_the_fee_configured_at_the_time(): void
    {
        UrbanGoodzStrandedSettings::put(UrbanGoodzStrandedSettings::KEY_FEE_ENABLED, '1');
        UrbanGoodzStrandedSettings::put(UrbanGoodzStrandedSettings::KEY_FEE_MINOR, '750');

        $response = $this->actingAs($this->verified('fee'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.help_request_fee_minor', 750)
            ->assertJsonPath('data.help_request_fee_status', 'unpaid');
    }

    public function test_switching_the_fee_off_waives_it_rather_than_leaving_it_unpaid(): void
    {
        // A request left `unpaid` with a zero fee could never broadcast, which
        // would strand every request the moment an admin switched the fee off.
        UrbanGoodzStrandedSettings::put(UrbanGoodzStrandedSettings::KEY_FEE_ENABLED, '0');

        $this->actingAs($this->verified('nofee'), 'api')
            ->postJson('/api/v1/urban-goodz/stranded/requests', [
                'service_slug' => self::SAMARITAN_SERVICE,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.help_request_fee_minor', 0)
            ->assertJsonPath('data.help_request_fee_status', 'waived');
    }

    // ------------------------------------------------- 2. responder dispatching

    public function test_broadcast_reaches_a_nearby_samaritan_with_a_ttl_and_an_eta(): void
    {
        $request = $this->request($this->verified('near'));
        $responder = $this->responder('samaritan', 2.0);

        $notified = $this->dispatcher()->broadcast($request);

        $this->assertSame(1, $notified);

        $offer = UrbanGoodzStrandedOffer::where('request_id', $request->id)->first();

        $this->assertNotNull($offer);
        $this->assertSame($responder->user_id, (int) $offer->responder_id);
        $this->assertSame('offered', $offer->status);
        $this->assertNotNull($offer->expires_at, 'An offer with no expiry can never time out.');
        $this->assertTrue($offer->expires_at->isFuture());
        $this->assertGreaterThan(0, $offer->eta_minutes);
        $this->assertSame('broadcasting', $request->fresh()->status);
    }

    public function test_broadcast_ignores_responders_who_cannot_actually_take_the_job(): void
    {
        $request = $this->request($this->verified('filtered'));

        // Outside the opening radius of the ladder.
        $this->responder('samaritan', 500.0);
        // Signed off.
        $this->responder('samaritan', 1.0, ['is_online' => false]);
        // Already mid-rescue.
        $this->responder('samaritan', 1.0, ['active_request_id' => $request->id]);
        // App killed without going offline: still flagged online, but stale.
        $this->responder('samaritan', 1.0, ['last_seen_at' => now()->subHours(3)]);
        // Willing to travel only a mile, sitting five away.
        $this->responder('samaritan', 5.0, ['max_travel_miles' => 1]);
        // No fix at all.
        $this->responder('samaritan', 1.0, ['last_latitude' => null, 'last_longitude' => null]);

        $this->assertSame(0, $this->dispatcher()->broadcast($request));
        $this->assertSame(0, UrbanGoodzStrandedOffer::where('request_id', $request->id)->count());
    }

    public function test_a_tow_is_never_offered_to_a_community_member(): void
    {
        $request = $this->request($this->verified('towdispatch'), self::PROFESSIONAL_SERVICE);

        $samaritan = $this->responder('samaritan', 1.0);
        $professional = $this->responder('tow', 3.0);

        $notified = $this->dispatcher()->broadcast($request);

        $this->assertSame(1, $notified, 'Only the professional should have been offered the tow.');

        $offers = UrbanGoodzStrandedOffer::where('request_id', $request->id)->get();

        $this->assertSame([$professional->user_id], $offers->pluck('responder_id')->map('intval')->all());
        $this->assertNotContains($samaritan->user_id, $offers->pluck('responder_id')->map('intval')->all());
    }

    public function test_a_second_broadcast_in_the_same_round_does_not_double_offer(): void
    {
        $request = $this->request($this->verified('dedupe'));
        $this->responder('samaritan', 2.0);

        $this->assertSame(1, $this->dispatcher()->broadcast($request));
        $this->assertSame(0, $this->dispatcher()->broadcast($request->fresh()));
        $this->assertSame(1, UrbanGoodzStrandedOffer::where('request_id', $request->id)->count());
    }

    public function test_widening_climbs_the_radius_ladder_and_reports_when_it_is_exhausted(): void
    {
        $ladder = UrbanGoodzStrandedSettings::radiusLadder();
        $request = $this->request($this->verified('widen'));

        $this->assertTrue($this->dispatcher()->widen($request));

        $widened = $request->fresh();
        $this->assertSame($ladder[1], $widened->broadcast_radius_miles);
        $this->assertSame(1, $widened->broadcast_round, 'Widening must open a new round.');

        // At the top of the ladder there is nowhere further to look, and the
        // caller needs to be told so rather than looping.
        $widened->update(['broadcast_radius_miles' => end($ladder)]);
        $this->assertFalse($this->dispatcher()->widen($widened->fresh()));
    }

    public function test_widening_reaches_a_responder_the_opening_radius_missed(): void
    {
        $ladder = UrbanGoodzStrandedSettings::radiusLadder();
        $request = $this->request($this->verified('widenhit'));

        // Sitting between the first and second rungs.
        $this->responder('samaritan', $ladder[0] + 2.0);

        $this->assertSame(0, $this->dispatcher()->broadcast($request));
        $this->assertTrue($this->dispatcher()->widen($request->fresh()));
        $this->assertSame(1, UrbanGoodzStrandedOffer::where('request_id', $request->id)->count());
    }

    public function test_escalation_drops_samaritans_and_reaches_professionals(): void
    {
        $request = $this->request($this->verified('escalate'));

        $this->responder('samaritan', 1.0);
        $professional = $this->responder('mobile_mechanic', 4.0);

        $notified = $this->dispatcher()->escalateToProfessionals($request);

        $escalated = $request->fresh();

        $this->assertSame(1, $notified);
        $this->assertFalse((bool) $escalated->allow_samaritans);
        $this->assertSame('escalated_professional', $escalated->status);
        $this->assertNotNull($escalated->escalated_at);

        $this->assertSame(
            [$professional->user_id],
            UrbanGoodzStrandedOffer::where('request_id', $request->id)->pluck('responder_id')->map('intval')->all()
        );
    }

    public function test_a_terminal_or_already_assigned_request_is_never_broadcast(): void
    {
        $cancelled = $this->request($this->verified('terminal'), self::SAMARITAN_SERVICE, ['status' => 'cancelled']);
        $this->responder('samaritan', 1.0);

        $this->assertSame(0, $this->dispatcher()->broadcast($cancelled));

        $assigned = $this->request($this->verified('assigned'), self::SAMARITAN_SERVICE, ['selected_offer_id' => 999999]);
        $this->assertSame(0, $this->dispatcher()->broadcast($assigned));
    }

    // ------------------------------------------------ 3. selection & lifecycle

    /** Broadcast, then have the responder accept, leaving a selectable offer. */
    private function acceptedOffer(UrbanGoodzStrandedRequest $request, string $mode = UrbanGoodzStrandedOffer::MODE_VOLUNTEER, int $amountMinor = 0): UrbanGoodzStrandedOffer
    {
        $this->dispatcher()->broadcast($request);

        $offer = UrbanGoodzStrandedOffer::where('request_id', $request->id)->firstOrFail();
        $offer->update([
            'status' => 'accepted',
            'response_mode' => $mode,
            'requested_amount_minor' => $amountMinor,
            'responded_at' => now(),
        ]);

        return $offer->fresh();
    }

    public function test_selecting_a_responder_assigns_the_job_and_marks_them_busy(): void
    {
        $customer = $this->verified('select');
        $request = $this->request($customer);
        $this->responder('samaritan', 2.0);

        $offer = $this->acceptedOffer($request);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('selected_offer_id', $offer->id);

        $assigned = $request->fresh();
        $this->assertSame($offer->id, (int) $assigned->selected_offer_id);
        $this->assertSame((int) $offer->responder_id, (int) $assigned->assigned_responder_id);
        $this->assertNotNull($assigned->assigned_at);
        $this->assertSame('selected', $offer->fresh()->status);

        $this->assertSame(
            $request->id,
            (int) UrbanGoodzStrandedResponder::where('user_id', $offer->responder_id)->first()->active_request_id,
            'A selected responder must be marked busy so dispatch stops offering them other rescues.'
        );
    }

    public function test_everyone_else_on_the_shortlist_is_passed_over_not_declined(): void
    {
        $customer = $this->verified('shortlist');
        $request = $this->request($customer);

        $this->responder('samaritan', 1.0);
        $this->responder('samaritan', 2.0);

        $this->dispatcher()->broadcast($request);

        $offers = UrbanGoodzStrandedOffer::where('request_id', $request->id)->get();
        $this->assertCount(2, $offers, 'Both nearby responders should have been offered the job.');

        $offers->each(fn ($o) => $o->update(['status' => 'accepted', 'response_mode' => UrbanGoodzStrandedOffer::MODE_VOLUNTEER]));

        $chosen = $offers->first();

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$chosen->id}/select")
            ->assertStatus(200);

        $this->assertSame('selected', $chosen->fresh()->status);
        $this->assertSame('passed_over', $offers->last()->fresh()->status);
    }

    public function test_a_second_selection_is_refused_so_one_rescue_never_gets_two_responders(): void
    {
        $customer = $this->verified('double');
        $request = $this->request($customer);

        $this->responder('samaritan', 1.0);
        $this->responder('samaritan', 2.0);

        $this->dispatcher()->broadcast($request);

        $offers = UrbanGoodzStrandedOffer::where('request_id', $request->id)->get();
        $offers->each(fn ($o) => $o->update(['status' => 'accepted', 'response_mode' => UrbanGoodzStrandedOffer::MODE_VOLUNTEER]));

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offers->first()->id}/select")
            ->assertStatus(200);

        // A double tap, or a second device on the same account.
        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offers->last()->id}/select")
            ->assertStatus(409);

        $this->assertSame($offers->first()->id, (int) $request->fresh()->selected_offer_id);
    }

    public function test_an_offer_that_was_only_broadcast_cannot_be_selected(): void
    {
        $customer = $this->verified('unaccepted');
        $request = $this->request($customer);
        $this->responder('samaritan', 1.0);

        $this->dispatcher()->broadcast($request);

        // Still `offered`. Accepting is what shortlists a responder; without it
        // there is nothing for the customer to choose.
        $offer = UrbanGoodzStrandedOffer::where('request_id', $request->id)->firstOrFail();

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(410);

        $this->assertNull($request->fresh()->selected_offer_id);
    }

    public function test_the_offers_screen_shows_only_the_shortlist(): void
    {
        $customer = $this->verified('offerlist');
        $request = $this->request($customer);

        $this->responder('samaritan', 1.0);
        $this->responder('samaritan', 2.0);

        $this->dispatcher()->broadcast($request);

        $offers = UrbanGoodzStrandedOffer::where('request_id', $request->id)->orderBy('id')->get();
        $offers->first()->update(['status' => 'accepted', 'response_mode' => UrbanGoodzStrandedOffer::MODE_TIPS_ONLY]);
        // The second is left `offered` and must not appear.

        $response = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers");

        $response->assertStatus(200)
            ->assertJsonPath('total_size', 1)
            ->assertJsonPath('offers.0.id', $offers->first()->id)
            ->assertJsonPath('offers.0.status', 'accepted');
    }

    public function test_the_lifecycle_runs_from_en_route_through_to_confirmation(): void
    {
        $customer = $this->verified('lifecycle');
        $request = $this->request($customer);
        $this->responder('samaritan', 2.0);

        $offer = $this->acceptedOffer($request);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200);

        foreach (['en_route' => 'en_route', 'arrived' => 'on_scene', 'completed' => 'completed'] as $event => $expected) {
            $this->actingAs($customer, 'api')
                ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => $event])
                ->assertStatus(200)
                ->assertJsonPath('data.status', $expected);
        }

        $done = $request->fresh();
        $this->assertNotNull($done->en_route_at);
        $this->assertNotNull($done->arrived_at);
        $this->assertNotNull($done->completed_at);
    }

    public function test_a_status_event_before_a_responder_is_selected_is_refused(): void
    {
        $customer = $this->verified('nostatus');
        $request = $this->request($customer);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'en_route'])
            ->assertStatus(409);
    }

    public function test_an_unknown_lifecycle_event_is_rejected(): void
    {
        $customer = $this->verified('badevent');
        $request = $this->request($customer);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'teleported'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event'], 'errors');
    }

    public function test_cancelling_before_broadcast_leaves_the_fee_refundable(): void
    {
        $customer = $this->verified('cancelearly');
        $request = $this->request($customer);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/cancel", ['reason' => 'Sorted it myself.'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $cancelled = $request->fresh();
        $this->assertSame('refundable', $cancelled->help_request_fee_status);
        $this->assertSame('Sorted it myself.', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancelling_after_broadcast_keeps_the_fee_because_it_bought_the_broadcast(): void
    {
        $customer = $this->verified('cancellate');
        $request = $this->request($customer);
        $this->responder('samaritan', 2.0);

        $this->dispatcher()->broadcast($request);
        $this->assertNotNull($request->fresh()->broadcast_at);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/cancel")
            ->assertStatus(200);

        $this->assertSame('paid', $request->fresh()->help_request_fee_status);
    }

    public function test_cancelling_releases_held_escrow_back_to_the_customer(): void
    {
        $customer = $this->verified('cancelescrow');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, ['escrow_status' => 'held']);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/cancel")
            ->assertStatus(200);

        $this->assertSame('refunded', $request->fresh()->escrow_status);
    }

    public function test_a_cancelled_request_refuses_every_further_action(): void
    {
        $customer = $this->verified('afterdeath');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, ['status' => 'cancelled']);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/cancel")
            ->assertStatus(409);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'en_route'])
            ->assertStatus(409);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/broadcast")
            ->assertStatus(409);
    }

    public function test_broadcast_is_refused_while_the_fee_is_outstanding(): void
    {
        $customer = $this->verified('unpaid');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, [
            'help_request_fee_status' => 'unpaid',
            'help_request_fee_minor' => 500,
        ]);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/broadcast")
            ->assertStatus(402)
            ->assertJsonPath('code', 'fee_outstanding');
    }

    // -------------------------------------------------------- 4. authorization

    public function test_stranded_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/urban-goodz/stranded/requests', [
            'service_slug' => self::SAMARITAN_SERVICE,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertStatus(401);
    }

    public function test_a_customer_cannot_read_or_touch_another_customers_request(): void
    {
        $owner = $this->verified('owner');
        $intruder = $this->verified('intruder');
        $request = $this->request($owner);

        // Not 403: an intruder should not even learn that the request exists.
        $this->actingAs($intruder, 'api')
            ->getJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}")
            ->assertStatus(404);

        $this->actingAs($intruder, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/cancel")
            ->assertStatus(404);

        $this->actingAs($intruder, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'en_route'])
            ->assertStatus(404);

        $this->assertSame('draft', $request->fresh()->status);
    }

    public function test_the_owner_can_read_their_own_request_by_uuid_or_number(): void
    {
        $customer = $this->verified('reader');
        $request = $this->request($customer);

        $this->actingAs($customer, 'api')
            ->getJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $request->uuid);

        $this->actingAs($customer, 'api')
            ->getJson("/api/v1/urban-goodz/stranded/requests/{$request->request_number}")
            ->assertStatus(200)
            ->assertJsonPath('data.request_number', $request->request_number);
    }

    public function test_responder_coordinates_are_never_serialised_to_a_customer(): void
    {
        // A leaked coordinate is a safety problem, not a privacy nicety: it
        // exposes where a lone responder physically is.
        $responder = $this->responder('samaritan', 2.0);
        $encoded = $responder->fresh()->toArray();

        $this->assertArrayNotHasKey('last_latitude', $encoded);
        $this->assertArrayNotHasKey('last_longitude', $encoded);
    }

    public function test_a_licence_number_is_encrypted_at_rest_and_never_serialised(): void
    {
        $user = $this->user('licence');

        $verification = new UrbanGoodzStrandedVerification([
            'user_id' => $user->id,
            'role' => UrbanGoodzStrandedVerification::ROLE_CUSTOMER,
            'status' => 'approved',
        ]);
        $verification->setLicenseNumber('D1234567890');
        $verification->save();

        $raw = DB::table('urban_goodz_stranded_verifications')
            ->where('id', $verification->id)
            ->value('license_number_encrypted');

        $this->assertNotSame('D1234567890', $raw, 'The licence number is sitting in the database in the clear.');
        $this->assertStringNotContainsString('D1234567890', (string) $raw);
        $this->assertSame('7890', $verification->fresh()->license_last_four);
        $this->assertArrayNotHasKey('license_number_encrypted', $verification->fresh()->toArray());
    }

    // ------------------------------------------------- 5. payments and escrow

    public function test_a_waived_fee_broadcasts_without_ever_reaching_the_payment_provider(): void
    {
        $customer = $this->verified('waived');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, [
            'help_request_fee_minor' => 0,
            'help_request_fee_status' => 'unpaid',
        ]);
        $this->responder('samaritan', 2.0);

        // A zero charge would be rejected by the provider and would strand the
        // request at the gate, so this path must skip the provider entirely.
        $result = app(UrbanGoodzStrandedPaymentService::class)
            ->payFeeAndBroadcast($request, 'pm_should_never_be_used');

        $this->assertTrue($result['paid']);
        $this->assertTrue($result['broadcast']);
        $this->assertSame(1, $result['responders_notified']);
        $this->assertSame('waived', $request->fresh()->help_request_fee_status);

        $this->assertSame(0, UrbanGoodzPaymentTransaction::where('payable_type', UrbanGoodzStrandedRequest::class)
            ->where('payable_id', $request->id)
            ->count(), 'A waived fee must not write a charge to the ledger.');
    }

    public function test_paying_an_already_paid_fee_is_refused_before_any_charge(): void
    {
        $customer = $this->verified('paidtwice');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, ['help_request_fee_status' => 'paid']);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/pay-fee", [
                'payment_method' => 'pm_should_never_be_used',
            ])
            ->assertStatus(402)
            ->assertJsonPath('code', 'payment_failed');
    }

    public function test_paying_a_fee_requires_a_payment_method(): void
    {
        $customer = $this->verified('nomethod');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, ['help_request_fee_status' => 'unpaid']);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/pay-fee", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'], 'errors');
    }

    public function test_confirming_the_job_releases_escrow_and_ledgers_it(): void
    {
        $customer = $this->verified('escrow');
        $request = $this->request($customer);
        $this->responder('samaritan', 2.0);

        $offer = $this->acceptedOffer($request, UrbanGoodzStrandedOffer::MODE_PAID, 4500);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200);

        // Money owed to a responder is held, not paid, until the work is
        // confirmed.
        $this->assertSame('held', $request->fresh()->escrow_status);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'confirmed'])
            ->assertStatus(200);

        $confirmed = $request->fresh();
        $this->assertSame('released', $confirmed->escrow_status);
        $this->assertNotNull($confirmed->escrow_released_at);
        $this->assertNotNull($confirmed->customer_confirmed_at);

        $ledger = UrbanGoodzPaymentTransaction::where('payable_type', UrbanGoodzStrandedRequest::class)
            ->where('payable_id', $request->id)
            ->where('transaction_type', 'escrow_release')
            ->get();

        $this->assertCount(1, $ledger, 'The release must leave exactly one ledger row.');
        $this->assertSame(4500, (int) $ledger->first()->amount_minor);
        $this->assertSame('completed', $ledger->first()->internal_status);
        $this->assertSame($request->request_number, $ledger->first()->merchant_reference);
    }

    public function test_releasing_escrow_twice_never_pays_a_responder_twice(): void
    {
        $customer = $this->verified('idempotent');
        $request = $this->request($customer);
        $this->responder('samaritan', 2.0);

        $offer = $this->acceptedOffer($request, UrbanGoodzStrandedOffer::MODE_PAID, 2500);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200);

        $payments = app(UrbanGoodzStrandedPaymentService::class);

        $first = $payments->releaseEscrow($request->fresh());
        $this->assertTrue($first['released']);
        $this->assertSame(2500, $first['amount_minor']);

        // A retried request, or a second tap on Confirm.
        $second = $payments->releaseEscrow($request->fresh());
        $this->assertFalse($second['released']);
        $this->assertSame('already_released', $second['reason']);

        $this->assertSame(1, UrbanGoodzPaymentTransaction::where('payable_type', UrbanGoodzStrandedRequest::class)
            ->where('payable_id', $request->id)
            ->where('transaction_type', 'escrow_release')
            ->count(), 'A second confirmation wrote a second payment row.');
    }

    public function test_releasing_escrow_when_nothing_is_held_is_a_no_op(): void
    {
        $request = $this->request($this->verified('nothingheld'), self::SAMARITAN_SERVICE, ['escrow_status' => 'none']);

        $result = app(UrbanGoodzStrandedPaymentService::class)->releaseEscrow($request);

        $this->assertFalse($result['released']);
        $this->assertSame('nothing_held', $result['reason']);
        $this->assertSame(0, UrbanGoodzPaymentTransaction::where('payable_id', $request->id)
            ->where('transaction_type', 'escrow_release')->count());
    }

    public function test_a_volunteer_rescue_holds_no_escrow_at_all(): void
    {
        $customer = $this->verified('volunteer');
        $request = $this->request($customer);
        $this->responder('samaritan', 2.0);

        $offer = $this->acceptedOffer($request, UrbanGoodzStrandedOffer::MODE_VOLUNTEER, 0);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200);

        $this->assertSame('none', $request->fresh()->escrow_status);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'confirmed'])
            ->assertStatus(200);

        $this->assertSame(0, UrbanGoodzPaymentTransaction::where('payable_id', $request->id)
            ->where('transaction_type', 'escrow_release')->count());
    }

    // -------------------------------------------------- 6. live feed scoping

    private function admin(): Admin
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin-stranded-integration@urbangoodz.com'],
            [
                'f_name' => 'Admin',
                'l_name' => 'Integration',
                'phone' => '1234567892',
                'password' => bcrypt('not-a-production-password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );

        $admin->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();

        return $admin;
    }

    public function test_the_admin_live_feed_reports_active_rescues_and_emergencies(): void
    {
        $customer = $this->verified('adminfeed');
        $request = $this->request($customer, self::SAMARITAN_SERVICE, [
            'status' => 'broadcasting',
            'is_emergency' => true,
        ]);
        $this->responder('samaritan', 1.0);

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson(route('admin.urban-goodz.stranded.live-feed'));

        $response->assertStatus(200);

        $numbers = collect($response->json('requests'))->pluck('number');
        $this->assertContains($request->request_number, $numbers->all());

        $row = collect($response->json('requests'))->firstWhere('number', $request->request_number);
        $this->assertTrue($row['emergency']);
        $this->assertSame('broadcasting', $row['status']);

        $this->assertGreaterThanOrEqual(1, $response->json('summary.active_requests'));
        $this->assertGreaterThanOrEqual(1, $response->json('summary.emergencies'));
        $this->assertGreaterThanOrEqual(1, $response->json('summary.awaiting_responder'));
    }

    public function test_the_admin_live_feed_flags_a_responder_whose_signal_has_dropped(): void
    {
        // Online but stale: counting them as available help would mislead an
        // operator, so they must be surfaced rather than hidden.
        $stale = $this->responder('samaritan', 1.0, ['last_seen_at' => now()->subHour()]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson(route('admin.urban-goodz.stranded.live-feed'));

        $response->assertStatus(200);

        $row = collect($response->json('responders'))->firstWhere('user_id', $stale->user_id);

        $this->assertNotNull($row, 'A stale responder must still appear in the feed.');
        $this->assertTrue($row['stale']);
        $this->assertGreaterThanOrEqual(1, $response->json('summary.stale_fixes'));
    }

    private function vendor(?int $zoneId = null): Vendor
    {
        $token = 'test_token_' . Str::random(16);

        $vendor = Vendor::create([
            'f_name' => 'Vendor',
            'l_name' => 'Integration',
            'email' => 'vendor-stranded-integration-' . Str::random(8) . '@urbangoodz.test',
            'phone' => '1' . random_int(1000000000, 1999999999),
            'password' => bcrypt('not-a-production-password'),
            'status' => 1,
        ]);

        $vendor->forceFill(['login_remember_token' => $token])->save();

        if ($zoneId !== null) {
            Store::create([
                'name' => 'Stranded Integration Store',
                'phone' => '1234567893',
                'vendor_id' => $vendor->id,
                'module_id' => DB::table('modules')->value('id'),
                'zone_id' => $zoneId,
                'latitude' => (string) self::LAT,
                'longitude' => (string) self::LNG,
                'status' => 1,
                'active' => 1,
            ]);
        }

        return $vendor->fresh();
    }

    private function vendorFeed(Vendor $vendor): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['login_remember_token' => $vendor->login_remember_token])
            ->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.urban-goodz.stranded.live-feed'));
    }

    public function test_the_vendor_live_feed_is_scoped_to_the_stores_zone(): void
    {
        // Synthetic ids rather than seeded rows: zone_id carries no foreign key
        // on either table, and a test that proves scoping should not also
        // depend on how a given database happens to be seeded.
        $zones = [900001, 900002];

        $customer = $this->verified('zoned');

        $inZone = $this->request($customer, self::SAMARITAN_SERVICE, [
            'status' => 'broadcasting',
            'zone_id' => $zones[0],
        ]);

        $outOfZone = $this->request($customer, self::SAMARITAN_SERVICE, [
            'status' => 'broadcasting',
            'zone_id' => $zones[1],
        ]);

        $response = $this->vendorFeed($this->vendor($zones[0]));

        $response->assertStatus(200);

        $numbers = collect($response->json('requests'))->pluck('number')->all();

        $this->assertContains($inZone->request_number, $numbers);
        $this->assertNotContains(
            $outOfZone->request_number,
            $numbers,
            'The vendor feed leaked a rescue from another zone.'
        );
    }

    public function test_the_vendor_live_feed_survives_a_vendor_with_no_stores(): void
    {
        // A vendor who has registered but not yet opened a store has no zone to
        // scope by. That must answer, not fail.
        $response = $this->vendorFeed($this->vendor(null));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'generated_at',
                'summary' => ['active_requests', 'emergencies', 'stale_fixes'],
                'requests',
                'responders',
            ]);
    }

    public function test_the_admin_live_page_renders(): void
    {
        // The feed being healthy says nothing about the page that polls it. A
        // Blade that references a missing layout or variable is a 500 that no
        // JSON test would ever catch.
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.urban-goodz.stranded.live'))
            ->assertStatus(200)
            ->assertSee('Stranded', false);
    }

    public function test_the_vendor_live_page_renders(): void
    {
        // With a store, because the shared vendor layout indexes into the
        // vendor's stores unguarded. A storeless vendor cannot render ANY
        // vendor page, which is a platform-wide layout problem rather than a
        // Stranded one -- the feed below covers that case where it belongs.
        $vendor = $this->vendor(900003);

        $this->withSession(['login_remember_token' => $vendor->login_remember_token])
            ->actingAs($vendor, 'vendor')
            ->get(route('vendor.urban-goodz.stranded.live'))
            ->assertStatus(200)
            ->assertSee('Stranded', false);
    }

    public function test_the_vendor_feed_matches_the_admin_feed_in_shape(): void
    {
        // The business portal reuses the admin feed's shape deliberately, so
        // the two cannot drift into separate implementations.
        $admin = $this->actingAs($this->admin(), 'admin')
            ->getJson(route('admin.urban-goodz.stranded.live-feed'))
            ->assertStatus(200);

        $vendor = $this->vendorFeed($this->vendor(null))->assertStatus(200);

        $this->assertSame(
            array_keys($admin->json('summary')),
            array_keys($vendor->json('summary'))
        );
    }
}
