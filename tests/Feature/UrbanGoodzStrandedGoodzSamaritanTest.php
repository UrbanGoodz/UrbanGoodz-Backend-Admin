<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UrbanGoodzStrandedConsent;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRating;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedSafetyReport;
use App\Models\UrbanGoodzStrandedService;
use App\Models\UrbanGoodzStrandedVerification;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSafety;
use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stranded is "Find a Goodz Samaritan" -- a community help network, not a
 * priced roadside-services marketplace. This covers what that principle
 * requires beyond the base lifecycle: no Urban Goodz pricing on the public
 * catalogue, the help-code arrival ritual, mutual identity exposure that is
 * withheld before selection and released after it, safety reporting, and
 * post-assist ratings feeding back into a responder's public trust score.
 */
class UrbanGoodzStrandedGoodzSamaritanTest extends TestCase
{
    use DatabaseTransactions;

    private const SAMARITAN_SERVICE = 'dead-battery';
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
            'f_name' => 'Goodz',
            'l_name' => ucfirst($tag),
            'email' => 'goodz-' . $tag . '-' . Str::random(8) . '@urbangoodz.test',
            'phone' => '1' . random_int(1000000000, 1999999999),
            'password' => bcrypt('not-a-production-password'),
        ]);
    }

    private function verified(string $tag, string $role = UrbanGoodzStrandedVerification::ROLE_CUSTOMER): User
    {
        $user = $this->user($tag);

        UrbanGoodzStrandedVerification::create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
            'license_last_four' => '4321',
            'license_expires_on' => now()->addYears(3)->toDateString(),
            'full_name' => 'Goodz Tester',
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
        return UrbanGoodzStrandedService::where('slug', $slug)->firstOrFail();
    }

    private function responder(User $user, float $miles, array $overrides = []): UrbanGoodzStrandedResponder
    {
        return UrbanGoodzStrandedResponder::create(array_merge([
            'user_id' => $user->id,
            'responder_type' => 'samaritan',
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

    private function request(User $user, array $overrides = []): UrbanGoodzStrandedRequest
    {
        $service = $this->service(self::SAMARITAN_SERVICE);

        return UrbanGoodzStrandedRequest::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'request_number' => 'ST-TEST-' . strtoupper(Str::random(6)),
            'help_code' => '4827',
            'user_id' => $user->id,
            'service_id' => $service->id,
            'service_slug' => $service->slug,
            'status' => 'draft',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Camry',
            'vehicle_plate' => 'CUST42',
            'notes' => 'Battery is completely dead.',
            'allow_samaritans' => true,
            'help_request_fee_minor' => 500,
            'help_request_fee_status' => 'paid',
            'currency' => 'USD',
            'broadcast_radius_miles' => UrbanGoodzStrandedSettings::radiusLadder()[0],
        ], $overrides));
    }

    /** A request already assigned to a specific responder user, ready for lifecycle events. */
    private function assignedRequest(User $customer, User $responderUser): array
    {
        $request = $this->request($customer);
        $this->responder($responderUser, 2.0);

        app(UrbanGoodzStrandedDispatcher::class)->broadcast($request);

        $offer = UrbanGoodzStrandedOffer::where('request_id', $request->id)->firstOrFail();
        $offer->update(['status' => 'accepted', 'response_mode' => UrbanGoodzStrandedOffer::MODE_VOLUNTEER, 'responded_at' => now()]);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200);

        return [$request->fresh(), $offer->fresh()];
    }

    // --------------------------------------------------------- no UG pricing

    public function test_the_public_catalogue_never_exposes_a_price(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/stranded/services');

        $response->assertStatus(200);
        foreach ($response->json('services') as $service) {
            $this->assertArrayNotHasKey('price_min_minor', $service);
            $this->assertArrayNotHasKey('price_max_minor', $service);
            $this->assertArrayNotHasKey('pricing_note', $service);
            $this->assertArrayHasKey('samaritan_eligible', $service);
        }
    }

    // --------------------------------------------------------------- help code

    public function test_arrival_requires_the_correct_help_code(): void
    {
        $customer = $this->verified('code-customer');
        $responderUser = $this->verified('code-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $wrong = $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", [
                'event' => 'arrived',
                'help_code' => '0000',
            ]);
        $wrong->assertStatus(422)->assertJsonPath('code', 'help_code_mismatch');
        $this->assertSame('assigned', $request->fresh()->status);

        $right = $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", [
                'event' => 'arrived',
                'help_code' => $request->help_code,
            ]);
        $right->assertStatus(200)->assertJsonPath('data.status', 'on_scene');
    }

    public function test_the_help_code_is_never_sent_to_the_responder(): void
    {
        $customer = $this->verified('hide-customer');
        $responderUser = $this->verified('hide-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $assignment = $this->actingAs($responderUser, 'api')
            ->getJson('/api/v1/urban-goodz/stranded/responder/assignment');

        $assignment->assertStatus(200)->assertJsonPath('has_assignment', true);
        $this->assertStringNotContainsString($request->help_code, $assignment->getContent());
    }

    // ---------------------------------------------------- mutual info sharing

    public function test_pre_selection_offer_never_carries_customer_identity(): void
    {
        $customer = $this->verified('preselect-customer');
        $responderUser = $this->verified('preselect-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        $request = $this->request($customer);
        $this->responder($responderUser, 2.0);

        app(UrbanGoodzStrandedDispatcher::class)->broadcast($request);

        $offers = $this->actingAs($responderUser, 'api')
            ->getJson('/api/v1/urban-goodz/stranded/responder/offers');

        $offers->assertStatus(200)->assertJsonPath('total_size', 1);
        $body = $offers->json('offers.0');
        // Whitelist, not a blacklist: any new field added to this payload
        // later must be deliberately safe, not merely "not on a guessed list".
        $allowed = ['offer_id', 'request_uuid', 'request_number', 'service', 'distance_miles', 'eta_minutes', 'reward_offer_minor', 'currency', 'is_emergency', 'safety_status', 'vehicle', 'notes', 'expires_at'];
        $this->assertEmpty(array_diff(array_keys($body), $allowed), 'Pre-selection offer payload leaked an unexpected field: ' . implode(',', array_diff(array_keys($body), $allowed)));
        $this->assertArrayNotHasKey('latitude', $body);
        $this->assertArrayNotHasKey('longitude', $body);
    }

    public function test_responder_has_no_assignment_before_being_selected(): void
    {
        $customer = $this->verified('noassign-customer');
        $responderUser = $this->verified('noassign-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        $request = $this->request($customer);
        $this->responder($responderUser, 2.0);

        app(UrbanGoodzStrandedDispatcher::class)->broadcast($request);

        $response = $this->actingAs($responderUser, 'api')
            ->getJson('/api/v1/urban-goodz/stranded/responder/assignment');

        $response->assertStatus(200)->assertJsonPath('has_assignment', false);
    }

    public function test_after_selection_the_responder_sees_exact_location_and_customer_identity(): void
    {
        $customer = $this->verified('post-customer');
        $responderUser = $this->verified('post-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $response = $this->actingAs($responderUser, 'api')
            ->getJson('/api/v1/urban-goodz/stranded/responder/assignment');

        $response->assertStatus(200)
            ->assertJsonPath('has_assignment', true)
            ->assertJsonPath('location.latitude', self::LAT)
            ->assertJsonPath('location.longitude', self::LNG)
            ->assertJsonPath('customer.name', 'Goodz')
            ->assertJsonPath('vehicle_plate', $request->vehicle_plate);
    }

    public function test_after_selection_the_customer_sees_responder_vehicle_and_real_verification_status(): void
    {
        $customer = $this->verified('track-customer');
        $responderUser = $this->verified('track-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        UrbanGoodzStrandedResponder::where('user_id', $responderUser->id)->update([
            'vehicle_make' => 'Honda',
            'vehicle_model' => 'Civic',
            'vehicle_color' => 'Blue',
            'vehicle_plate' => 'GOODZ1',
        ]);

        $response = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/track");

        $response->assertStatus(200)
            ->assertJsonPath('responder.verified', true)
            ->assertJsonPath('responder.vehicle.plate', 'GOODZ1')
            ->assertJsonPath('responder.vehicle.color', 'Blue');
    }

    public function test_an_unverified_responders_track_view_reports_not_verified(): void
    {
        $customer = $this->verified('unver-customer');
        // Not verified as a samaritan -- but selection itself does not check
        // this, so it can still exercise the track() honesty check.
        $responderUser = $this->user('unver-responder');
        $request = $this->request($customer);
        $this->responder($responderUser, 2.0);

        app(UrbanGoodzStrandedDispatcher::class)->broadcast($request);
        $offer = UrbanGoodzStrandedOffer::where('request_id', $request->id)->firstOrFail();
        $offer->update(['status' => 'accepted', 'response_mode' => UrbanGoodzStrandedOffer::MODE_VOLUNTEER, 'responded_at' => now()]);
        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/offers/{$offer->id}/select")
            ->assertStatus(200);

        $response = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/track");

        $response->assertStatus(200)->assertJsonPath('responder.verified', false);
    }

    // --------------------------------------------------------- responder self-service

    public function test_a_samaritan_can_set_their_vehicle_and_capabilities(): void
    {
        $user = $this->verified('profile', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/urban-goodz/stranded/responder/profile', [
                'vehicle_make' => 'Ford',
                'vehicle_model' => 'F-150',
                'vehicle_color' => 'Black',
                'vehicle_plate' => 'HELP99',
                'capabilities' => ['battery', 'tire'],
            ]);

        $response->assertStatus(200);
        $responder = UrbanGoodzStrandedResponder::where('user_id', $user->id)->where('responder_type', 'samaritan')->first();
        $this->assertSame('Ford', $responder->vehicle_make);
        $this->assertSame(['battery', 'tire'], $responder->capabilities);
    }

    public function test_a_samaritan_can_acknowledge_the_safety_pledge(): void
    {
        $user = $this->verified('ack', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/urban-goodz/stranded/responder/safety-acknowledge');

        $response->assertStatus(200);
        $responder = UrbanGoodzStrandedResponder::where('user_id', $user->id)->where('responder_type', 'samaritan')->first();
        $this->assertNotNull($responder->safety_ack_at);
    }

    // ----------------------------------------------------------------- reports

    public function test_either_side_can_file_a_safety_report(): void
    {
        $customer = $this->verified('report-customer');
        $responderUser = $this->verified('report-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $fromCustomer = $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/report", [
                'reason_code' => 'not_who_claimed',
                'details' => 'This does not look like the person from the app.',
            ]);
        $fromCustomer->assertStatus(201);

        $fromResponder = $this->actingAs($responderUser, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/report", [
                'reason_code' => 'location_incorrect',
            ]);
        $fromResponder->assertStatus(201);

        $this->assertSame(2, UrbanGoodzStrandedSafetyReport::where('request_id', $request->id)->count());
        $this->assertSame('customer', UrbanGoodzStrandedSafetyReport::where('reporter_user_id', $customer->id)->first()->reporter_role);
    }

    public function test_a_stranger_cannot_file_a_report_on_someone_elses_request(): void
    {
        $customer = $this->verified('stranger-customer');
        $responderUser = $this->verified('stranger-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $stranger = $this->verified('stranger');

        $response = $this->actingAs($stranger, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/report", ['reason_code' => 'other']);

        $response->assertStatus(404);
    }

    // ----------------------------------------------------------------- ratings

    public function test_the_customer_rating_the_samaritan_updates_the_samaritans_public_rating(): void
    {
        $customer = $this->verified('rate-customer');
        $responderUser = $this->verified('rate-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $response = $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/rate", [
                'stars' => 5,
                'comment' => 'Showed up fast and was very kind.',
            ]);

        $response->assertStatus(201);

        $responder = UrbanGoodzStrandedResponder::where('user_id', $responderUser->id)->first();
        $this->assertSame(5.0, $responder->rating);
    }

    public function test_a_side_cannot_rate_the_same_assist_twice(): void
    {
        $customer = $this->verified('twice-customer');
        $responderUser = $this->verified('twice-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/rate", ['stars' => 4])
            ->assertStatus(201);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/rate", ['stars' => 2])
            ->assertStatus(409);

        $this->assertSame(1, UrbanGoodzStrandedRating::where('request_id', $request->id)->count());
    }

    public function test_the_responder_can_also_rate_the_customer(): void
    {
        $customer = $this->verified('resprate-customer');
        $responderUser = $this->verified('resprate-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $response = $this->actingAs($responderUser, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/rate", ['stars' => 5]);

        $response->assertStatus(201);
        $rating = UrbanGoodzStrandedRating::where('request_id', $request->id)->where('rater_role', 'responder')->first();
        $this->assertSame($customer->id, $rating->ratee_user_id);
    }

    // ---------------------------------------------- a samaritan becomes free again

    public function test_completing_a_job_frees_the_samaritan_for_the_next_one(): void
    {
        $customer = $this->verified('free-customer');
        $responderUser = $this->verified('free-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $this->assertNotNull(UrbanGoodzStrandedResponder::where('user_id', $responderUser->id)->first()->active_request_id);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'en_route'])
            ->assertStatus(200);
        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'arrived', 'help_code' => $request->help_code])
            ->assertStatus(200);
        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/status", ['event' => 'completed'])
            ->assertStatus(200);

        $responder = UrbanGoodzStrandedResponder::where('user_id', $responderUser->id)->first();
        $this->assertNull($responder->active_request_id);
        $this->assertSame(13, $responder->completed_jobs);
    }

    public function test_cancelling_an_assigned_request_frees_the_samaritan(): void
    {
        $customer = $this->verified('cancelfree-customer');
        $responderUser = $this->verified('cancelfree-responder', UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
        [$request] = $this->assignedRequest($customer, $responderUser);

        $this->actingAs($customer, 'api')
            ->postJson("/api/v1/urban-goodz/stranded/requests/{$request->uuid}/cancel")
            ->assertStatus(200);

        $responder = UrbanGoodzStrandedResponder::where('user_id', $responderUser->id)->first();
        $this->assertNull($responder->active_request_id);
    }
}
