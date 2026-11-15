<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\ReelsModule\Entities\Reel;
use Modules\ReelsModule\Services\ReelApiService;
use Modules\ReelsModule\Services\ReelRankingService;
use Tests\TestCase;

/** The reels feed is ranked ("For You"), not just newest-first, and stays fair, stable and safe. */
class ReelFeedRankingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // A clean slate for the pool (the transaction rolls this back).
        DB::table('reels')->delete();
        config(['urban_goodz_feed.pool_size' => 300, 'urban_goodz_feed.max_per_author_per_page' => 2]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function profile(string $tag, ?int $userId = null): int
    {
        $userId ??= $this->user($tag)->id;

        return DB::table('urban_goodz_creator_profiles')->insertGetId(array_filter([
            'user_id' => $userId, 'display_name' => "Creator {$tag}", 'handle' => 'h'.$tag.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ], fn ($v) => $v !== null));
    }

    private function user(string $tag): User
    {
        return User::create(['f_name' => 'F', 'l_name' => ucfirst($tag), 'email' => "feed-{$tag}-".uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1]);
    }

    private function reel(array $over = []): int
    {
        return DB::table('reels')->insertGetId(array_merge([
            'description' => 'r', 'thumbnail' => 't.jpg', 'video' => 'v.mp4', 'status' => 1, 'publication_status' => 'published',
            'moderation_status' => 'approved', 'is_always_visible' => 1, 'total_views' => 0, 'total_likes' => 0, 'total_comments' => 0,
            'total_shares' => 0, 'total_store_visits' => 0, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ], $over));
    }

    private function feed(?int $userId = null, ?string $guest = null, int $limit = 50, int $page = 1, ?string $sort = 'for_you'): array
    {
        return app(ReelApiService::class)->getReels(null, null, $limit, $page, null, $sort, $userId, $guest)->items();
    }

    private function ids(array $reels): array
    {
        return array_map(fn ($r) => (int) $r->id, $reels);
    }

    public function test_a_reel_people_react_to_beats_a_newer_one_nobody_has_watched_much(): void
    {
        $a = $this->profile('a'); $b = $this->profile('b');
        $loved = $this->reel(['creator_profile_id' => $a, 'total_views' => 4000, 'total_likes' => 700, 'total_comments' => 120, 'total_shares' => 90, 'published_at' => now()->subHours(20)]);
        $quiet = $this->reel(['creator_profile_id' => $b, 'total_views' => 4000, 'total_likes' => 12, 'total_comments' => 0, 'published_at' => now()->subHours(1)]);

        $ids = $this->ids($this->feed());

        $this->assertSame($loved, $ids[0], 'engagement outranks a slightly newer reel with little reaction');
        $this->assertSame([$quiet], array_slice($ids, 1));
    }

    public function test_tiny_samples_do_not_look_like_hits_two_likes_on_two_views_is_weak_evidence(): void
    {
        $a = $this->profile('a'); $b = $this->profile('b');
        $lucky = Reel::find($this->reel(['creator_profile_id' => $a, 'total_views' => 2, 'total_likes' => 2]));
        $proven = Reel::find($this->reel(['creator_profile_id' => $b, 'total_views' => 5000, 'total_likes' => 600]));
        $svc = app(ReelRankingService::class);
        $ctx = ['followed_profiles' => [], 'liked_profiles' => [], 'liked_tags' => [], 'tags_by_reel' => [], 'seen' => []];

        $this->assertLessThan($svc->features($proven, $ctx)['engagement'], $svc->features($lucky, $ctx)['engagement'], 'a 100% like rate on 2 views is smoothed down, not trusted');
        // It still gets its fair look through the separate exploration boost, which is the point of that dial.
        $this->assertGreaterThan(0.9, $svc->features($lucky, $ctx)['exploration']);
    }

    public function test_fresh_reels_with_little_data_still_get_a_fair_look(): void
    {
        $a = $this->profile('a'); $b = $this->profile('b');
        $old = $this->reel(['creator_profile_id' => $a, 'total_views' => 900, 'total_likes' => 70, 'published_at' => now()->subDays(6)]);
        $new = $this->reel(['creator_profile_id' => $b, 'total_views' => 0, 'published_at' => now()->subMinutes(10)]);

        $this->assertSame($new, $this->ids($this->feed())[0]);
    }

    public function test_creators_you_follow_and_have_liked_rise_and_watched_reels_sink(): void
    {
        $me = $this->user('viewer');
        $followed = $this->profile('followed'); $other = $this->profile('other'); $seenOne = $this->profile('seen');
        $f = $this->reel(['creator_profile_id' => $followed, 'total_views' => 500, 'total_likes' => 40]);
        $o = $this->reel(['creator_profile_id' => $other, 'total_views' => 500, 'total_likes' => 40]);
        $s = $this->reel(['creator_profile_id' => $seenOne, 'total_views' => 500, 'total_likes' => 40]);
        DB::table('urban_goodz_creator_followers')->insert(['user_id' => $me->id, 'creator_profile_id' => $followed, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('reel_engagements')->insert(['reel_id' => $s, 'user_id' => $me->id, 'type' => 'view', 'created_at' => now(), 'updated_at' => now()]);

        $ids = $this->ids($this->feed($me->id));

        $this->assertSame($f, $ids[0], 'the creator I follow comes first');
        $this->assertSame($s, end($ids), 'what I already watched goes to the bottom, not out');
    }

    public function test_blocked_creators_and_reported_reels_never_appear(): void
    {
        $me = $this->user('viewer');
        $blockedUser = $this->user('blockedcreator');
        $blocked = $this->profile('blocked', $blockedUser->id); $fine = $this->profile('fine');
        $this->reel(['creator_profile_id' => $blocked]);
        $reported = $this->reel(['creator_profile_id' => $fine]);
        $ok = $this->reel(['creator_profile_id' => $fine]);
        DB::table('urban_goodz_creator_blocks')->insert(['blocker_user_id' => $me->id, 'blocked_user_id' => $blockedUser->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('creator_reel_reports')->insert(['reel_id' => $reported, 'user_id' => $me->id, 'reason' => 'spam', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([$ok], $this->ids($this->feed($me->id)));
        $this->assertCount(3, $this->feed(null), 'a different viewer is unaffected by my block and report');
    }

    public function test_one_prolific_creator_cannot_fill_the_screen(): void
    {
        $spammer = $this->profile('spammer'); $b = $this->profile('b'); $c = $this->profile('c');
        for ($i = 0; $i < 6; $i++) {
            $this->reel(['creator_profile_id' => $spammer, 'total_views' => 100, 'total_likes' => 30, 'published_at' => now()->subMinutes($i)]);
        }
        $this->reel(['creator_profile_id' => $b, 'total_views' => 100, 'total_likes' => 5, 'published_at' => now()->subHours(5)]);
        $this->reel(['creator_profile_id' => $c, 'total_views' => 100, 'total_likes' => 5, 'published_at' => now()->subHours(6)]);

        $page1 = $this->feed(null, null, 4, 1);
        $authors = array_map(fn ($r) => (int) $r->creator_profile_id, $page1);

        $this->assertLessThanOrEqual(2, count(array_keys($authors, $spammer, true)), 'at most 2 per page');
        for ($i = 1; $i < count($authors); $i++) {
            $this->assertNotSame($authors[$i - 1], $authors[$i], 'never back to back');
        }
    }

    public function test_paging_never_repeats_or_skips_a_reel(): void
    {
        $profiles = array_map(fn ($t) => $this->profile((string) $t), range(1, 6));
        foreach (range(1, 25) as $i) {
            $this->reel(['creator_profile_id' => $profiles[$i % 6], 'total_views' => $i * 40, 'total_likes' => $i * 3, 'published_at' => now()->subHours($i)]);
        }
        $all = [];
        foreach ([1, 2, 3, 4, 5] as $p) {
            $all = array_merge($all, $this->ids($this->feed(null, 'guest-1', 6, $p)));
        }

        $this->assertCount(25, $all);
        $this->assertSame($all, array_values(array_unique($all)), 'no reel appears twice across pages');
        $this->assertSame($all, $this->ids(array_merge(...array_map(fn ($p) => $this->feed(null, 'guest-1', 6, $p), [1, 2, 3, 4, 5]))), 'same viewer, same day: same order');
    }

    public function test_sort_latest_keeps_the_old_newest_first_order(): void
    {
        $a = $this->profile('a');
        $old = $this->reel(['creator_profile_id' => $a, 'total_views' => 9000, 'total_likes' => 4000]);
        $new = $this->reel(['creator_profile_id' => $a, 'total_views' => 0]);

        $this->assertSame([$new, $old], $this->ids($this->feed(null, null, 50, 1, 'latest')));
    }

    public function test_the_scoring_formula_is_a_plain_weighted_sum(): void
    {
        $w = ['engagement' => 1.0, 'recency' => 0.8, 'follow' => 0.7, 'affinity' => 0.5, 'exploration' => 0.35, 'seen' => -1.2];

        $this->assertEqualsWithDelta(1.0 + 0.8, ReelRankingService::combine(['engagement' => 1, 'recency' => 1], $w), 0.0001);
        $this->assertEqualsWithDelta(0.7 - 1.2, ReelRankingService::combine(['follow' => 1, 'seen' => 1], $w), 0.0001);
    }
}
