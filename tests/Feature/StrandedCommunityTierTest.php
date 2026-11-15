<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzNotificationPreference;
use App\Models\UrbanGoodzStrandedConsent;
use App\Models\UrbanGoodzStrandedNearbyPresence;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedService;
use App\Models\User;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSafety;
use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Stranded reaches any nearby user who opted in, with Goodz Samaritans first, and help is free to ask for. */
class StrandedCommunityTierTest extends TestCase
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
        config(['urban_goodz_stranded.community.enabled' => true]);
    }

    private function user(string $tag, bool $phoneVerified = true): User
    {
        $u = User::create([
            'f_name' => 'S', 'l_name' => ucfirst($tag), 'email' => "sc-{$tag}-".Str::random(8).'@urbangoodz.test',
            'phone' => '1'.random_int(1000000000, 1999999999), 'password' => bcrypt('x'), 'is_active' => 1,
        ]);
        \DB::table('users')->where('id', $u->id)->update(['is_phone_verified' => $phoneVerified ? 1 : 0]);

        return $u->fresh();
    }

    /** A neighbour who opted in, reported a position $miles north, and accepted the community terms. */
    private function neighbour(string $tag, float $miles, array $opts = []): User
    {
        $u = $this->user($tag, $opts['phone'] ?? true);
        if ($opts['optIn'] ?? true) {
            UrbanGoodzNotificationPreference::create(['recipient_type' => 'customer', 'recipient_id' => $u->id, 'nearby_help_opt_in' => true, 'nearby_help_opt_in_at' => now()]);
        }
        if ($opts['presence'] ?? true) {
            UrbanGoodzStrandedNearbyPresence::create([
                'user_id' => $u->id, 'latitude' => round(self::LAT + $miles / 69.0, 3), 'longitude' => round(self::LNG, 3),
                'reported_at' => now(), 'expires_at' => $opts['expires'] ?? now()->addHour(),
            ]);
        }
        if ($opts['consent'] ?? true) {
            foreach (UrbanGoodzStrandedSafety::requiredDocumentsFor(UrbanGoodzStrandedSafety::ROLE_COMMUNITY) as $doc) {
                UrbanGoodzStrandedConsent::create(['user_id' => $u->id, 'role' => UrbanGoodzStrandedSafety::ROLE_COMMUNITY, 'document' => $doc,
                    'version' => UrbanGoodzStrandedSafety::VERSIONS[$doc], 'accepted_at' => now()]);
            }
        }

        return $u;
    }

    private function samaritan(float $miles): UrbanGoodzStrandedResponder
    {
        return UrbanGoodzStrandedResponder::create([
            'user_id' => $this->user('sam')->id, 'responder_type' => 'samaritan', 'is_online' => true,
            'last_latitude' => self::LAT + $miles / 69.0, 'last_longitude' => self::LNG, 'last_seen_at' => now(), 'max_travel_miles' => 25,
            'rating' => 4.8, 'trust_score' => 90, 'completed_jobs' => 5,
        ]);
    }

    private function request(User $owner, array $over = []): UrbanGoodzStrandedRequest
    {
        $service = UrbanGoodzStrandedService::where('slug', 'dead-battery')->firstOrFail();

        return UrbanGoodzStrandedRequest::create(array_merge([
            'uuid' => (string) Str::uuid(), 'request_number' => 'ST-C-'.strtoupper(Str::random(6)), 'user_id' => $owner->id,
            'service_id' => $service->id, 'service_slug' => $service->slug, 'status' => 'draft', 'latitude' => self::LAT, 'longitude' => self::LNG,
            'allow_samaritans' => true, 'help_request_fee_minor' => 0, 'help_request_fee_status' => 'waived', 'currency' => 'USD',
            'broadcast_radius_miles' => UrbanGoodzStrandedSettings::radiusLadder()[0],
        ], $over));
    }

    private function offered(UrbanGoodzStrandedRequest $r): array
    {
        return UrbanGoodzStrandedOffer::where('request_id', $r->id)->orderBy('id')->get()->map(fn ($o) => [$o->responder_type, (int) $o->responder_id])->all();
    }

    private function dispatch(UrbanGoodzStrandedRequest $r)
    {
        return app(UrbanGoodzStrandedDispatcher::class)->broadcast($r->fresh());
    }

    public function test_with_no_samaritan_nearby_an_opted_in_neighbour_is_offered_the_request(): void
    {
        $owner = $this->user('owner');
        $n = $this->neighbour('n1', 0.5);
        $r = $this->request($owner);

        $this->dispatch($r);

        $this->assertSame([['community', $n->id]], $this->offered($r));
    }

    public function test_goodz_samaritans_get_it_first_and_neighbours_join_only_after_their_window(): void
    {
        $owner = $this->user('owner');
        $sam = $this->samaritan(0.4);
        $n = $this->neighbour('n1', 0.2); // closer than the Samaritan, still must wait
        $r = $this->request($owner);

        $this->dispatch($r);
        $this->assertSame([['samaritan', $sam->user_id]], $this->offered($r), 'round 1: Samaritans only');

        // The Samaritan window lapses with nobody answering: the tick widens, and now neighbours are offered too.
        app(UrbanGoodzStrandedDispatcher::class)->widen($r->fresh());
        $offered = $this->offered($r);
        $this->assertContains(['community', $n->id], $offered);
    }

    public function test_nobody_is_reached_who_has_not_opted_in_or_has_no_fresh_position(): void
    {
        $owner = $this->user('owner');
        $this->neighbour('optedout', 0.3, ['optIn' => false]);
        $this->neighbour('nopresence', 0.3, ['presence' => false]);
        $this->neighbour('expired', 0.3, ['expires' => now()->subMinute()]);
        $this->neighbour('faraway', 80);
        $r = $this->request($owner);

        $this->assertSame(0, $this->dispatch($r));
        $this->assertSame([], $this->offered($r));
    }

    public function test_the_requester_is_never_offered_their_own_request(): void
    {
        $owner = $this->neighbour('owner', 0.0);
        $r = $this->request($owner);

        $this->assertSame(0, $this->dispatch($r));
    }

    public function test_users_who_have_not_cleared_the_community_gate_are_skipped(): void
    {
        $owner = $this->user('owner');
        $this->neighbour('nophone', 0.3, ['phone' => false]);
        $this->neighbour('noconsent', 0.3, ['consent' => false]);
        $r = $this->request($owner);

        $this->assertSame(0, $this->dispatch($r));
    }

    public function test_a_professional_only_service_is_never_offered_to_neighbours(): void
    {
        $owner = $this->user('owner');
        $this->neighbour('n1', 0.3);
        $tow = UrbanGoodzStrandedService::where('slug', 'tow-truck')->firstOrFail();
        $r = $this->request($owner, ['service_id' => $tow->id, 'service_slug' => $tow->slug, 'allow_samaritans' => false]);

        $this->assertSame(0, $this->dispatch($r));
    }

    public function test_an_ordinary_user_must_clear_the_gate_to_accept(): void
    {
        $owner = $this->user('owner');
        $n = $this->neighbour('n1', 0.3);
        $r = $this->request($owner);
        $this->dispatch($r);
        $offer = UrbanGoodzStrandedOffer::where('request_id', $r->id)->firstOrFail();

        // Terms withdrawn after the offer went out: accepting is refused.
        UrbanGoodzStrandedConsent::where('user_id', $n->id)->delete();
        $this->actingAs($n, 'api')->postJson("/api/v1/urban-goodz/stranded/responder/offers/{$offer->id}/accept", ['response_mode' => 'volunteer'])
            ->assertStatus(403)->assertJsonPath('code', 'consent_required');
        $this->assertSame('offered', $offer->fresh()->status);

        $this->actingAs($n, 'api')->postJson('/api/v1/urban-goodz/stranded/community/acknowledge')->assertOk();
        $this->actingAs($n, 'api')->postJson("/api/v1/urban-goodz/stranded/responder/offers/{$offer->id}/accept", ['response_mode' => 'volunteer'])->assertOk();
        $this->assertSame('accepted', $offer->fresh()->status);
    }

    public function test_presence_needs_the_opt_in_rounds_the_position_and_is_deleted_on_opt_out(): void
    {
        $u = $this->user('p1');

        $this->actingAs($u, 'api')->postJson('/api/v1/urban-goodz/stranded/community/presence', ['latitude' => 40.75812345, 'longitude' => -73.98551234])
            ->assertStatus(403)->assertJsonPath('code', 'nearby_help_off');

        $this->actingAs($u, 'api')->putJson('/api/v1/urban-goodz/notification-preferences', ['nearby_help_opt_in' => true])->assertOk()->assertJsonPath('data.nearby_help_opt_in', true);
        $this->actingAs($u, 'api')->postJson('/api/v1/urban-goodz/stranded/community/presence', ['latitude' => 40.75812345, 'longitude' => -73.98551234])->assertOk();
        $row = UrbanGoodzStrandedNearbyPresence::where('user_id', $u->id)->firstOrFail();
        $this->assertEquals(40.758, (float) $row->latitude, 'stored to about 100 m, not exact');
        $this->assertEquals(-73.986, (float) $row->longitude);

        $this->actingAs($u, 'api')->putJson('/api/v1/urban-goodz/notification-preferences', ['nearby_help_opt_in' => false])->assertOk();
        $this->assertNull(UrbanGoodzStrandedNearbyPresence::where('user_id', $u->id)->first(), 'withdrawing consent deletes the position at once');
    }

    public function test_expired_positions_are_pruned(): void
    {
        $u = $this->user('p2');
        UrbanGoodzStrandedNearbyPresence::create(['user_id' => $u->id, 'latitude' => 1, 'longitude' => 1, 'reported_at' => now()->subHours(5), 'expires_at' => now()->subHours(3)]);

        \Artisan::call('stranded:prune-presence');

        $this->assertSame(0, UrbanGoodzStrandedNearbyPresence::count());
    }

    public function test_helpers_are_told_when_the_person_asking_is_not_id_verified_yet(): void
    {
        $owner = $this->user('unverifiedowner'); // asked for help with just a phone and the terms
        $n = $this->neighbour('n1', 0.3);
        $r = $this->request($owner);
        $this->dispatch($r);

        $this->actingAs($n, 'api')->getJson('/api/v1/urban-goodz/stranded/responder/offers')
            ->assertOk()->assertJsonPath('offers.0.requester_verified', false);
    }

    public function test_the_safety_status_lets_a_phone_verified_requester_through_and_says_they_are_not_id_verified(): void
    {
        $u = $this->user('statususer');
        foreach (UrbanGoodzStrandedSafety::requiredDocumentsFor('customer') as $doc) {
            UrbanGoodzStrandedConsent::create(['user_id' => $u->id, 'role' => 'customer', 'document' => $doc,
                'version' => UrbanGoodzStrandedSafety::VERSIONS[$doc], 'accepted_at' => now()]);
        }

        $this->actingAs($u, 'api')->getJson('/api/v1/urban-goodz/stranded/safety/status?role=customer')
            ->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('id_verified', false);

        // A Samaritan still needs the full check.
        $this->actingAs($u, 'api')->getJson('/api/v1/urban-goodz/stranded/safety/status?role=samaritan')
            ->assertOk()->assertJsonPath('allowed', false);
    }
}
