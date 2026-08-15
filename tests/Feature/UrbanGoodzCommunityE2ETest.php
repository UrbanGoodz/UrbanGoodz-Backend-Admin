<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Zone;
use App\Models\UrbanGoodzCommunityMarketplaceItem;
use App\Models\UrbanGoodzCommunityPost;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class UrbanGoodzCommunityE2ETest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $tag): User
    {
        return User::create([
            'f_name' => 'Community',
            'l_name' => ucfirst($tag),
            'email' => 'community-' . $tag . '-' . Str::random(8) . '@urbangoodz.test',
            'phone' => '1' . random_int(1000000000, 1999999999),
            'password' => bcrypt('not-a-production-password'),
        ]);
    }

    private function zone(): Zone
    {
        return Zone::create(['name' => 'Houston Test Zone ' . Str::random(6), 'status' => 1]);
    }

    public function test_groups_endpoint_lists_real_zones_plus_nationwide_and_worldwide(): void
    {
        $zone = $this->zone();

        $response = $this->getJson('/api/v1/urban-goodz/community/groups');

        $response->assertStatus(200)->assertJsonStructure([
            'status',
            'groups' => [['id', 'zone_id', 'zone_name', 'group_name', 'category', 'is_nationwide', 'is_worldwide', 'member_count', 'post_count', 'marketplace_item_count']],
        ]);

        $ids = collect($response->json('groups'))->pluck('id');
        $this->assertTrue($ids->contains('zone:' . $zone->id));
        $this->assertTrue($ids->contains('nationwide'));
        $this->assertTrue($ids->contains('worldwide'));
    }

    public function test_creating_a_post_requires_authentication(): void
    {
        $zone = $this->zone();

        $response = $this->postJson('/api/v1/urban-goodz/community/posts?scope=zone&zone_id=' . $zone->id, [
            'title' => 'Best taco truck on 5th?',
            'body' => 'Looking for recommendations near downtown.',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_post_and_read_it_back_in_the_group_feed(): void
    {
        $user = $this->user('poster');
        $zone = $this->zone();

        $create = $this->actingAs($user, 'api')
            ->postJson('/api/v1/urban-goodz/community/posts?scope=zone&zone_id=' . $zone->id, [
                'title' => 'Best taco truck on 5th?',
                'body' => 'Looking for recommendations near downtown.',
            ]);

        $create->assertStatus(201)->assertJsonPath('post.title', 'Best taco truck on 5th?');
        $postId = $create->json('post.id');

        $feed = $this->getJson('/api/v1/urban-goodz/community/posts?scope=zone&zone_id=' . $zone->id);
        $feed->assertStatus(200);
        $this->assertTrue(collect($feed->json('posts'))->pluck('id')->contains($postId));

        $detail = $this->getJson('/api/v1/urban-goodz/community/posts/' . $postId);
        $detail->assertStatus(200)->assertJsonPath('post.id', $postId);
    }

    public function test_comment_requires_authentication_and_then_appears_on_the_post(): void
    {
        $author = $this->user('author');
        $zone = $this->zone();

        $post = UrbanGoodzCommunityPost::create([
            'title' => 'Anyone know a good electrician?',
            'body' => 'Circuit breaker keeps tripping.',
            'author_name' => $author->full_name,
            'author_email' => $author->email,
            'user_id' => $author->id,
            'zone_id' => $zone->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $unauth = $this->postJson("/api/v1/urban-goodz/community/posts/{$post->id}/comments", ['body' => 'Try Ace Electric.']);
        $unauth->assertStatus(401);

        $commenter = $this->user('commenter');
        $commented = $this->actingAs($commenter, 'api')
            ->postJson("/api/v1/urban-goodz/community/posts/{$post->id}/comments", ['body' => 'Try Ace Electric.']);
        $commented->assertStatus(201)->assertJsonPath('comment.body', 'Try Ace Electric.');

        $detail = $this->getJson('/api/v1/urban-goodz/community/posts/' . $post->id);
        $this->assertTrue(collect($detail->json('comments'))->pluck('body')->contains('Try Ace Electric.'));
    }

    public function test_marketplace_items_are_scoped_by_zone_and_do_not_leak_across_zones(): void
    {
        $seller = $this->user('seller');
        $zoneA = $this->zone();
        $zoneB = $this->zone();

        UrbanGoodzCommunityMarketplaceItem::create([
            'title' => 'Kids bike, barely used',
            'price' => 40,
            'currency' => 'USD',
            'seller_name' => $seller->full_name,
            'is_active' => true,
            'zone_id' => $zoneA->id,
        ]);

        $inZoneA = $this->getJson('/api/v1/urban-goodz/community/marketplace?scope=zone&zone_id=' . $zoneA->id);
        $inZoneA->assertStatus(200);
        $this->assertCount(1, $inZoneA->json('items'));

        $inZoneB = $this->getJson('/api/v1/urban-goodz/community/marketplace?scope=zone&zone_id=' . $zoneB->id);
        $inZoneB->assertStatus(200);
        $this->assertCount(0, $inZoneB->json('items'));
    }

    public function test_worldwide_scope_is_rejected_for_marketplace(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/community/marketplace?scope=worldwide');
        $response->assertStatus(422);
    }

    public function test_invalid_zone_id_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/community/posts?scope=zone&zone_id=999999999');
        $response->assertStatus(422);
    }
}
