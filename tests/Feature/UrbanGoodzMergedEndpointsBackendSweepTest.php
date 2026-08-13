<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCreatorCampaign;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lane 1 Backend/API sweep: validation/error-handling coverage for a sample
 * of the most consequential routes merged in tonight's Creator Space /
 * Events Marketplace consolidation
 * (20c30d2 "merge(creator,notifications): add Creator Space/Events/Sourcing
 * and FCM v1/APNs from shopper-ai-notifications lane").
 *
 * The pre-existing tests for these controllers (CreatorCampaignTest,
 * EventMarketplaceTest, EventSourcingTest, ReelSocialTest, CreatorEarningsTest,
 * CreatorReelTest, SourcingPipelineTest) are placeholder stubs that only
 * assert `assertTrue(true)` - zero real coverage. This file replaces that
 * with real assertions against actual DB state and HTTP status/response
 * shape, per Lane 1 certification rules (no fake passes).
 */
class UrbanGoodzMergedEndpointsBackendSweepTest extends TestCase
{
    use RefreshDatabase;

    private function actingCreator(): array
    {
        $user = User::factory()->create();
        $profile = UrbanGoodzCreatorProfile::create([
            'user_id' => $user->id,
            'handle' => 'creator-'.$user->id,
            'display_name' => 'Test Creator',
            'verification_status' => 'unverified',
        ]);

        return [$user, $profile];
    }

    // ---- EventMarketplaceController@store -------------------------------

    public function test_event_store_rejects_missing_required_fields_with_422_not_500(): void
    {
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/events-marketplace', []);

        $response->assertStatus(422);
        $this->assertArrayHasKey('title', $response->json('errors'));
        $this->assertArrayHasKey('starts_at', $response->json('errors'));
    }

    public function test_event_store_rejects_end_before_start(): void
    {
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/events-marketplace', [
            'title' => 'Bad Timing Event',
            'description' => 'desc',
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('ends_at', $response->json('errors'));
    }

    public function test_event_store_with_valid_payload_creates_correct_db_state(): void
    {
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/events-marketplace', [
            'title' => 'Rooftop Market',
            'description' => 'A pop-up market',
            'starts_at' => now()->addDays(3)->toDateTimeString(),
            'ends_at' => now()->addDays(3)->addHours(4)->toDateTimeString(),
            'category' => 'Market',
            'city' => 'Atlanta',
        ]);

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('urban_goodz_events', [
            'id' => $id,
            'title' => 'Rooftop Market',
            'organiser_user_id' => $user->id,
            'organiser_type' => 'user',
        ]);
    }

    // ---- EventMarketplaceController@update -------------------------------
    // Regression coverage for a real mass-assignment bug found and fixed
    // during this sweep: update() used to do $event->update($request->all()),
    // letting any authenticated owner overwrite admin-only moderation
    // columns (approval_state, visibility_state, status, admin_notes,
    // moderation_notes, featured_at) and even hijack organiser_user_id,
    // simply by including them in the PUT body. Fixed to a validated
    // whitelist matching the creator-facing fields store() accepts.

    public function test_owner_can_update_safe_creator_facing_fields(): void
    {
        [$user] = $this->actingCreator();
        $event = UrbanGoodzEvent::create([
            'title' => 'Original Title',
            'description' => 'Original',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'organiser_user_id' => $user->id,
            'organiser_type' => 'user',
        ]);

        $response = $this->actingAs($user, 'api')->putJson("/api/v1/urban-goodz/events-marketplace/{$event->id}", [
            'title' => 'Updated Title',
            'city' => 'Miami',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('urban_goodz_events', [
            'id' => $event->id,
            'title' => 'Updated Title',
            'city' => 'Miami',
        ]);
    }

    public function test_owner_cannot_escalate_moderation_only_fields_via_update(): void
    {
        [$user] = $this->actingCreator();
        $event = UrbanGoodzEvent::create([
            'title' => 'Original Title',
            'description' => 'Original',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'organiser_user_id' => $user->id,
            'organiser_type' => 'user',
            'approval_state' => 'pending',
            'visibility_state' => 'hidden',
            'status' => 'draft',
        ]);
        $otherUserId = User::factory()->create()->id;

        $response = $this->actingAs($user, 'api')->putJson("/api/v1/urban-goodz/events-marketplace/{$event->id}", [
            'title' => 'Still Mine',
            'approval_state' => 'approved',
            'visibility_state' => 'visible',
            'status' => 'active',
            'admin_notes' => 'I approved myself',
            'organiser_user_id' => $otherUserId,
            'featured_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(200);
        $event->refresh();
        $this->assertSame('pending', $event->approval_state, 'A non-admin owner must not be able to self-approve an event.');
        $this->assertSame('hidden', $event->visibility_state);
        $this->assertSame('draft', $event->status);
        $this->assertNull($event->admin_notes);
        $this->assertNull($event->featured_at);
        $this->assertSame($user->id, $event->organiser_user_id, 'organiser_user_id must not be hijackable via the update payload.');
    }

    public function test_non_owner_cannot_update_event(): void
    {
        [$owner] = $this->actingCreator();
        [$intruder] = $this->actingCreator();
        $event = UrbanGoodzEvent::create([
            'title' => 'Owned Event',
            'description' => 'Original',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'organiser_user_id' => $owner->id,
            'organiser_type' => 'user',
        ]);

        $response = $this->actingAs($intruder, 'api')->putJson("/api/v1/urban-goodz/events-marketplace/{$event->id}", [
            'title' => 'Hijacked',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('urban_goodz_events', ['id' => $event->id, 'title' => 'Owned Event']);
    }

    // ---- CreatorSpaceController@applyCampaign ----------------------------
    // Regression coverage for a real bug found and fixed during this sweep:
    // applying to a nonexistent campaign_id previously skipped straight to
    // the insert and tripped the campaign_id foreign key constraint,
    // surfacing as a raw 500 (with APP_DEBUG on by default, a full SQL
    // exception + stack trace in the JSON body) instead of a clean 404.

    public function test_apply_to_real_campaign_creates_pending_assignment(): void
    {
        [$user, $profile] = $this->actingCreator();
        $campaign = UrbanGoodzCreatorCampaign::create([
            'title' => 'Spring Promo',
            'type' => 'ugc',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'api')->postJson("/api/v1/urban-goodz/creator-space/campaigns/{$campaign->id}/apply", []);

        $response->assertStatus(201);
        $this->assertDatabaseHas('urban_goodz_creator_campaign_assignments', [
            'creator_profile_id' => $profile->id,
            'campaign_id' => $campaign->id,
            'approval_status' => 'pending',
        ]);
    }

    public function test_apply_to_nonexistent_campaign_returns_404_not_raw_500(): void
    {
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/creator-space/campaigns/999999/apply', []);

        $response->assertStatus(404);
        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body, 'A missing-campaign apply must not leak a raw SQL exception.');
        $this->assertStringNotContainsString('Illuminate\\Database', $body, 'A missing-campaign apply must not leak a stack trace.');
        $this->assertSame(
            0,
            DB::table('urban_goodz_creator_campaign_assignments')->where('campaign_id', 999999)->count()
        );
    }

    // ---- Formerly a known blocker, now real coverage ----
    // The migration comment in
    // database/migrations/2026_07_30_200000_expand_events_creators_sourcing.php
    // (and this repo's Lane 1 handoff notes) used to flag that
    // App\Models\UrbanGoodzReel did not exist, despite being referenced by
    // CreatorSpaceController, ReelSocialController, and
    // UrbanGoodzCreatorProfile::reels(). That gap is closed: UrbanGoodzReel
    // now backs the real Modules\ReelsModule `reels` table (see f5b0250 and
    // the schema-baseline commit that fixed its comments() LSP violation).
    // These were characterization tests asserting the old 500s; they now
    // assert the real, working behavior instead.
    public function test_upload_reel_endpoint_creates_a_reel(): void
    {
        Storage::fake('public');
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->post('/api/v1/urban-goodz/creator-space/reels', [
            'video' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg'),
            'caption' => 'test',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Reel uploaded')
            ->assertJsonPath('data.description', 'test');
        $this->assertSame(1, DB::table('reels')->count());
    }

    public function test_list_my_reels_endpoint_returns_the_creators_reels(): void
    {
        [$user, $profile] = $this->actingCreator();
        DB::table('reels')->insert([
            'store_id' => null,
            'module_id' => \Modules\ReelsModule\Support\ReelModuleConfig::defaultModuleId(),
            'module_type' => \Modules\ReelsModule\Support\ReelModuleConfig::defaultModuleType(),
            'creator_profile_id' => $profile->id,
            'description' => 'existing reel',
            'video' => 'reels/videos/existing.mp4',
            'thumbnail' => 'reels/thumbnails/existing.jpg',
            'is_always_visible' => true,
            'status' => true,
            'publication_status' => 'draft',
            'moderation_status' => 'pending',
            'created_by_id' => $user->id,
            'created_by_type' => User::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v1/urban-goodz/creator-space/reels');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('total'));
        $this->assertSame('existing reel', $response->json('data.0.description'));
    }

    public function test_reel_comment_endpoint_404s_for_a_nonexistent_reel(): void
    {
        [$user] = $this->actingCreator();

        // Reel id=1 does not exist in this test's isolated transaction: the
        // endpoint correctly answers 404 rather than fatally erroring.
        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/reels/1/comments', [
            'content' => 'nice reel',
        ]);

        $response->assertStatus(404);
    }
}
