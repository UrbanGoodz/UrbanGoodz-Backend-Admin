<?php

namespace Tests\Feature;

use App\Models\CreatorReelTag;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzReel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end coverage for the Creator Space social-reel workflow, following
 * the 2026-08-12 product directive: Creator Space is a social-first content
 * platform ("Creator -> Reel -> Social Engagement"), not a traditional
 * storefront. A creator posts without needing a store, product, or
 * inventory; commerce linkage (CreatorReelTag) is strictly optional.
 *
 * This is the regression suite for the App\Models\UrbanGoodzReel fix: the
 * model now binds to the real `reels` table
 * (Modules\ReelsModule\Entities\Reel, already extended with
 * creator_profile_id/publication_status/moderation_status) instead of a
 * nonexistent class, and store_id/module_id are no longer required for a
 * creator-authored reel.
 */
class CreatorSpaceReelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actingCreator(array $overrides = []): array
    {
        $user = User::factory()->create();
        $profile = UrbanGoodzCreatorProfile::create(array_merge([
            'user_id' => $user->id,
            'handle' => 'creator-'.$user->id,
            'display_name' => 'Test Creator',
            'verification_status' => 'unverified',
            'status' => 'approved',
            'is_approved' => true,
            'approval_state' => 'approved',
            'visibility_state' => 'visible',
        ], $overrides));

        return [$user, $profile];
    }

    private function uploadPayload(string $caption = 'Houston, y\'all have to see this!'): array
    {
        return [
            'video' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg'),
            'caption' => $caption,
        ];
    }

    /** Simulates the admin moderation decision (Modules\ReelsModule\Http\Controllers\Api\V1\Admin\CreatorModerationController::moderate). */
    private function approveAndPublish(UrbanGoodzReel $reel): UrbanGoodzReel
    {
        $reel->update([
            'status' => true,
            'publication_status' => 'published',
            'moderation_status' => 'approved',
            'published_at' => now(),
        ]);

        return $reel->fresh();
    }

    // ---- Creator: upload / store --------------------------------------

    public function test_creator_can_upload_a_reel_with_no_store_or_product(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->post(
            '/api/v1/urban-goodz/creator-space/reels',
            $this->uploadPayload()
        );

        $response->assertStatus(201);
        $reelId = $response->json('data.id');

        $this->assertDatabaseHas('reels', [
            'id' => $reelId,
            'creator_profile_id' => $profile->id,
            'store_id' => null,
            'publication_status' => 'draft',
            'moderation_status' => 'pending',
            'description' => 'Houston, y\'all have to see this!',
        ]);
    }

    public function test_upload_reel_rejects_missing_media_with_422_not_500(): void
    {
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/creator-space/reels', [
            'caption' => 'no media attached',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('video', $response->json('errors'));
        $this->assertArrayHasKey('thumbnail', $response->json('errors'));
    }

    public function test_upload_reel_rejects_non_video_file_for_video_field(): void
    {
        Storage::fake('public');
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->post('/api/v1/urban-goodz/creator-space/reels', [
            'video' => UploadedFile::fake()->create('not-a-video.txt', 10, 'text/plain'),
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg'),
            'caption' => 'bad media',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('video', $response->json('errors'));
    }

    public function test_upload_reel_requires_authentication(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/v1/urban-goodz/creator-space/reels', $this->uploadPayload());

        $response->assertStatus(401);
    }

    // ---- Creator: list own / retrieve creator space --------------------

    public function test_creator_can_retrieve_their_own_reels_including_drafts(): void
    {
        Storage::fake('public');
        [$user] = $this->actingCreator();
        $this->actingAs($user, 'api')->post('/api/v1/urban-goodz/creator-space/reels', $this->uploadPayload());

        $response = $this->actingAs($user, 'api')->getJson('/api/v1/urban-goodz/creator-space/reels');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('total'));
    }

    public function test_creator_space_profile_reflects_reel_count(): void
    {
        Storage::fake('public');
        [$user] = $this->actingCreator();
        $this->actingAs($user, 'api')->post('/api/v1/urban-goodz/creator-space/reels', $this->uploadPayload());

        $response = $this->actingAs($user, 'api')->getJson('/api/v1/urban-goodz/creator-space/profile');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.reel_count'));
    }

    // ---- Creator: update / delete own reel ------------------------------

    public function test_creator_can_update_their_own_reel_caption(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->actingCreator();
        $reel = UrbanGoodzReel::create([
            'store_id' => null,
            'module_id' => 0,
            'module_type' => 'default',
            'creator_profile_id' => $profile->id,
            'description' => 'original',
            'is_always_visible' => true,
            'status' => true,
            'publication_status' => 'draft',
            'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'api')->putJson("/api/v1/urban-goodz/creator-space/reels/{$reel->id}", [
            'caption' => 'updated caption',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reels', ['id' => $reel->id, 'description' => 'updated caption']);
    }

    public function test_creator_can_delete_their_own_reel(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->actingCreator();
        $reel = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'to delete',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/v1/urban-goodz/creator-space/reels/{$reel->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reels', ['id' => $reel->id]);
    }

    // ---- Security: cross-creator isolation ------------------------------

    public function test_creator_a_cannot_update_creator_bs_reel(): void
    {
        [$userA] = $this->actingCreator();
        [, $profileB] = $this->actingCreator();
        $reelB = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profileB->id, 'description' => 'not yours',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($userA, 'api')->putJson("/api/v1/urban-goodz/creator-space/reels/{$reelB->id}", [
            'caption' => 'hijacked',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('reels', ['id' => $reelB->id, 'description' => 'not yours']);
    }

    public function test_creator_a_cannot_delete_creator_bs_reel(): void
    {
        [$userA] = $this->actingCreator();
        [, $profileB] = $this->actingCreator();
        $reelB = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profileB->id, 'description' => 'not yours',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($userA, 'api')->deleteJson("/api/v1/urban-goodz/creator-space/reels/{$reelB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('reels', ['id' => $reelB->id]);
    }

    // ---- Public: discover / browse ---------------------------------------

    public function test_draft_reels_do_not_appear_in_public_creator_reel_listing(): void
    {
        [$user, $profile] = $this->actingCreator();
        UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'still a draft',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'api')->getJson("/api/v1/urban-goodz/creators/{$profile->handle}/reels");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_published_and_approved_reels_appear_in_public_creator_reel_listing(): void
    {
        [$user, $profile] = $this->actingCreator();
        $reel = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);
        $this->approveAndPublish($reel);

        $response = $this->actingAs($user, 'api')->getJson("/api/v1/urban-goodz/creators/{$profile->handle}/reels");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($reel->id, $response->json('data.0.id'));
    }

    // ---- Public: comments / engagement -----------------------------------

    public function test_comments_endpoint_rejects_a_still_moderating_reel_with_404_not_500(): void
    {
        [$user, $profile] = $this->actingCreator();
        $reel = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'pending review',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'api')->getJson("/api/v1/urban-goodz/reels/{$reel->id}/comments");

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_comment_on_a_published_reel(): void
    {
        [$author, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));
        $commenter = User::factory()->create();

        $response = $this->actingAs($commenter, 'api')->postJson("/api/v1/urban-goodz/reels/{$reel->id}/comments", [
            'content' => 'love this!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('urban_goodz_reel_comments', [
            'reel_id' => $reel->id,
            'user_id' => $commenter->id,
            'content' => 'love this!',
        ]);

        // The comment must actually be readable back through the same
        // reel_id relationship that GET .../comments queries - this is the
        // regression check for the UrbanGoodzReel::comments() override
        // (previously silently pointed at the wrong comments table).
        $listResponse = $this->getJson("/api/v1/urban-goodz/reels/{$reel->id}/comments");
        $listResponse->assertStatus(200);
        $this->assertSame(1, $listResponse->json('total'));
    }

    public function test_comment_requires_authentication(): void
    {
        [, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));

        $response = $this->postJson("/api/v1/urban-goodz/reels/{$reel->id}/comments", ['content' => 'anon']);

        $response->assertStatus(401);
    }

    public function test_comment_rejects_empty_content_with_422_not_500(): void
    {
        [$user, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));

        $response = $this->actingAs($user, 'api')->postJson("/api/v1/urban-goodz/reels/{$reel->id}/comments", []);

        $response->assertStatus(422);
    }

    public function test_comment_on_nonexistent_reel_returns_404_not_500(): void
    {
        [$user] = $this->actingCreator();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/reels/999999/comments', [
            'content' => 'hello?',
        ]);

        $response->assertStatus(404);
    }

    public function test_reply_to_a_comment_is_nested_correctly(): void
    {
        [$user, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));
        $commentId = DB::table('urban_goodz_reel_comments')->insertGetId([
            'reel_id' => $reel->id, 'user_id' => $user->id, 'content' => 'top level',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'api')->postJson(
            "/api/v1/urban-goodz/reels/{$reel->id}/comments/{$commentId}/reply",
            ['content' => 'a reply']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('urban_goodz_reel_comments', [
            'reel_id' => $reel->id,
            'parent_id' => $commentId,
            'content' => 'a reply',
        ]);
    }

    // ---- Security: comment ownership -------------------------------------

    public function test_user_cannot_delete_another_users_comment(): void
    {
        [$author, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));
        $owner = User::factory()->create();
        $commentId = DB::table('urban_goodz_reel_comments')->insertGetId([
            'reel_id' => $reel->id, 'user_id' => $owner->id, 'content' => 'mine',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder, 'api')->deleteJson("/api/v1/urban-goodz/reels/comments/{$commentId}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('urban_goodz_reel_comments', ['id' => $commentId]);
    }

    public function test_user_can_delete_their_own_comment(): void
    {
        [, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'live post',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));
        $owner = User::factory()->create();
        $commentId = DB::table('urban_goodz_reel_comments')->insertGetId([
            'reel_id' => $reel->id, 'user_id' => $owner->id, 'content' => 'mine',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner, 'api')->deleteJson("/api/v1/urban-goodz/reels/comments/{$commentId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('urban_goodz_reel_comments', ['id' => $commentId]);
    }

    // ---- Optional commerce connection (directive #6) ----------------------

    public function test_reel_can_optionally_be_tagged_to_a_store_listing(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->actingCreator();
        $vendorId = DB::table('vendors')->insertGetId([
            'f_name' => 'V', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'vendor-'.uniqid().'@example.test', 'password' => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $storeId = DB::table('stores')->insertGetId([
            'name' => 'Test Store', 'phone' => '+1555'.random_int(1000000, 9999999),
            'vendor_id' => $vendorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $reel = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'check this deal out',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'api')->postJson("/api/v1/urban-goodz/creator-space/reels/{$reel->id}/tags", [
            'store_id' => $storeId,
            'taggable_type' => 'item',
            'taggable_id' => 42,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('creator_reel_tags', [
            'reel_id' => $reel->id, 'store_id' => $storeId, 'taggable_id' => 42,
        ]);
    }

    public function test_reel_tag_without_store_id_is_rejected_with_422_not_500(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->actingCreator();
        $reel = UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'no commerce link',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'api')->postJson("/api/v1/urban-goodz/creator-space/reels/{$reel->id}/tags", [
            'taggable_type' => 'item',
            'taggable_id' => 42,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, CreatorReelTag::where('reel_id', $reel->id)->count());
    }

    public function test_reel_with_no_commerce_tag_remains_fully_valid_content(): void
    {
        [, $profile] = $this->actingCreator();
        $reel = $this->approveAndPublish(UrbanGoodzReel::create([
            'store_id' => null, 'module_id' => 0, 'module_type' => 'default',
            'creator_profile_id' => $profile->id, 'description' => 'purely social, no product attached',
            'is_always_visible' => true, 'status' => true,
            'publication_status' => 'draft', 'moderation_status' => 'pending',
        ]));

        // A shopper browsing the creator's public reels, not the creator
        // themselves - this endpoint sits behind auth:api like the rest of
        // Creator Discovery.
        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer, 'api')->getJson("/api/v1/urban-goodz/creators/{$profile->handle}/reels");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(0, CreatorReelTag::where('reel_id', $reel->id)->count());
    }
}
