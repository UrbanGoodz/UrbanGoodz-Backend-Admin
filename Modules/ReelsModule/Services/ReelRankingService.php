<?php

namespace Modules\ReelsModule\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders a pool of reels into a "For You" feed.
 *
 * Score = engagement (smoothed) + freshness + who the viewer follows + what they have liked before
 *       + a fair look for new reels - a nudge down for ones they have already watched,
 * then a diversity pass so one prolific creator cannot fill the screen. Everything is tunable in
 * config/urban_goodz_feed.php. The same viewer sees a stable order for a whole day (a seeded, tiny jitter breaks
 * ties), so paging never repeats or skips a reel.
 */
class ReelRankingService
{
    /**
     * @param  Collection<int, \Modules\ReelsModule\Entities\Reel>  $reels  with creatorProfile loaded
     * @return Collection<int, \Modules\ReelsModule\Entities\Reel>
     */
    public function rank(Collection $reels, ?int $userId, ?string $guestId, int $limit): Collection
    {
        if ($reels->isEmpty()) {
            return $reels;
        }

        $ctx = $this->viewerContext($reels, $userId, $guestId);

        // Hidden entirely: creators the viewer blocked, reels they reported.
        $reels = $reels->reject(function ($reel) use ($ctx) {
            $creatorUser = $reel->creatorProfile?->user_id;

            return in_array($reel->id, $ctx['reported'], true) || ($creatorUser && in_array((int) $creatorUser, $ctx['blocked_users'], true));
        })->values();

        $seed = ($userId ? 'u'.$userId : 'g'.($guestId ?: 'anon')).'|'.now()->format('Y-m-d');
        $weights = (array) config('urban_goodz_feed.weights', []);

        $scored = $reels->map(function ($reel) use ($ctx, $seed, $weights) {
            $f = $this->features($reel, $ctx);
            $f['jitter'] = (crc32($seed.'|'.$reel->id) / 4294967295) * 0.1;

            return ['reel' => $reel, 'score' => self::combine($f, $weights)];
        })->sort(function ($a, $b) {
            return [$b['score'], $b['reel']->id] <=> [$a['score'], $a['reel']->id];
        })->values();

        return $this->diversify($scored->pluck('reel'), max(1, $limit));
    }

    /** @param array<string, float> $f @param array<string, float> $w */
    public static function combine(array $f, array $w): float
    {
        return ($w['engagement'] ?? 1) * ($f['engagement'] ?? 0)
            + ($w['recency'] ?? 0.8) * ($f['recency'] ?? 0)
            + ($w['follow'] ?? 0.7) * ($f['follow'] ?? 0)
            + ($w['affinity'] ?? 0.5) * ($f['affinity'] ?? 0)
            + ($w['exploration'] ?? 0.35) * ($f['exploration'] ?? 0)
            + ($w['seen'] ?? -1.2) * ($f['seen'] ?? 0)
            + ($f['jitter'] ?? 0);
    }

    /** @return array<string, float> each feature is 0..1 */
    public function features($reel, array $ctx): array
    {
        $cfg = (array) config('urban_goodz_feed', []);
        $val = (array) ($cfg['action_value'] ?? []);
        $views = max(0, (float) $reel->total_views);

        $weighted = (float) $reel->total_likes * ($val['likes'] ?? 1)
            + (float) $reel->total_comments * ($val['comments'] ?? 2)
            + (float) ($reel->total_shares ?? 0) * ($val['shares'] ?? 3)
            + (float) $reel->total_store_visits * ($val['visits'] ?? 3.5)
            + $views * ($val['views'] ?? 0.05);

        // Smoothed rate: a handful of reactions on a handful of views is not evidence.
        $priorViews = (float) ($cfg['prior_views'] ?? 50);
        $priorRate = max(0.001, (float) ($cfg['prior_rate'] ?? 0.08));
        $rate = ($weighted + $priorViews * $priorRate) / ($views + $priorViews);
        $engagement = min(1.0, ($rate / $priorRate) / 3.0);

        $ageHours = max(0, now()->diffInMinutes($reel->published_at ?? $reel->created_at, true) / 60);
        $half = max(1.0, (float) ($cfg['half_life_hours'] ?? 48));
        $recency = 0.5 ** ($ageHours / $half);

        $profileId = (int) ($reel->creator_profile_id ?? 0);
        $follow = $profileId && in_array($profileId, $ctx['followed_profiles'], true) ? 1.0 : 0.0;

        $likedCreator = $profileId && in_array($profileId, $ctx['liked_profiles'], true);
        $tagOverlap = $this->tagKeys($reel, $ctx) && array_intersect($this->tagKeys($reel, $ctx), $ctx['liked_tags']) ? 1.0 : 0.0;
        $affinity = min(1.0, ($likedCreator ? 0.6 : 0.0) + 0.4 * $tagOverlap);

        $newViews = max(1.0, (float) ($cfg['new_reel_views'] ?? 30));
        $exploration = $views < $newViews ? 1.0 - ($views / $newViews) : 0.0;

        $seen = in_array($reel->id, $ctx['seen'], true) ? 1.0 : 0.0;

        return compact('engagement', 'recency', 'follow', 'affinity', 'exploration', 'seen');
    }

    /**
     * No two reels in a row from the same creator/store, and at most N per page-sized window. A reel that
     * does not fit is deferred to the next slot where it does, never dropped.
     *
     * @param  Collection<int, mixed>  $ordered
     */
    private function diversify(Collection $ordered, int $limit): Collection
    {
        $maxPerPage = max(1, (int) config('urban_goodz_feed.max_per_author_per_page', 2));
        $queue = $ordered->all();
        $out = [];
        $pageCounts = [];
        $lastAuthor = null;

        while ($queue) {
            $picked = null;
            $page = intdiv(count($out), $limit);
            foreach ($queue as $i => $reel) {
                $author = $this->author($reel);
                if ($author !== $lastAuthor && ($pageCounts[$page][$author] ?? 0) < $maxPerPage) {
                    $picked = $i;
                    break;
                }
            }
            // Nothing fits (one creator left): rules relax rather than lose reels.
            $picked ??= array_key_first($queue);
            $reel = $queue[$picked];
            unset($queue[$picked]);
            $author = $this->author($reel);
            $pageCounts[$page][$author] = ($pageCounts[$page][$author] ?? 0) + 1;
            $lastAuthor = $author;
            $out[] = $reel;
        }

        return collect($out);
    }

    private function author($reel): string
    {
        return $reel->creator_profile_id ? 'c'.$reel->creator_profile_id : 's'.($reel->store_id ?? 0);
    }

    /** @return list<string> */
    private function tagKeys($reel, array $ctx): array
    {
        return $ctx['tags_by_reel'][$reel->id] ?? [];
    }

    private function viewerContext(Collection $reels, ?int $userId, ?string $guestId): array
    {
        $ids = $reels->pluck('id')->all();
        $ctx = ['followed_profiles' => [], 'blocked_users' => [], 'reported' => [], 'seen' => [], 'liked_profiles' => [], 'liked_tags' => [], 'tags_by_reel' => []];

        if (Schema::hasTable('creator_reel_tags')) {
            foreach (DB::table('creator_reel_tags')->whereIn('reel_id', $ids)->get(['reel_id', 'taggable_type', 'taggable_id']) as $t) {
                $ctx['tags_by_reel'][(int) $t->reel_id][] = $t->taggable_type.':'.$t->taggable_id;
            }
        }

        $engagements = DB::table('reel_engagements')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId && $guestId, fn ($q) => $q->where('guest_id', $guestId))
            ->when(! $userId && ! $guestId, fn ($q) => $q->whereRaw('1 = 0'));

        $ctx['seen'] = array_map('intval', (clone $engagements)->where('type', 'view')->whereIn('reel_id', $ids)->pluck('reel_id')->all());

        if ($userId) {
            $likedReelIds = (clone $engagements)->where('type', 'like')->pluck('reel_id')->all();
            if ($likedReelIds) {
                $ctx['liked_profiles'] = array_map('intval', DB::table('reels')->whereIn('id', $likedReelIds)->whereNotNull('creator_profile_id')->pluck('creator_profile_id')->unique()->all());
                if (Schema::hasTable('creator_reel_tags')) {
                    $ctx['liked_tags'] = DB::table('creator_reel_tags')->whereIn('reel_id', $likedReelIds)->get(['taggable_type', 'taggable_id'])
                        ->map(fn ($t) => $t->taggable_type.':'.$t->taggable_id)->unique()->values()->all();
                }
            }
            if (Schema::hasTable('urban_goodz_creator_followers')) {
                $ctx['followed_profiles'] = array_map('intval', DB::table('urban_goodz_creator_followers')->where('user_id', $userId)->pluck('creator_profile_id')->all());
            }
            if (Schema::hasTable('urban_goodz_creator_blocks')) {
                $ctx['blocked_users'] = array_map('intval', DB::table('urban_goodz_creator_blocks')->where('blocker_user_id', $userId)->pluck('blocked_user_id')->all());
            }
        }

        if (Schema::hasTable('creator_reel_reports')) {
            $ctx['reported'] = array_map('intval', DB::table('creator_reel_reports')
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when(! $userId && $guestId, fn ($q) => $q->where('guest_id', $guestId))
                ->when(! $userId && ! $guestId, fn ($q) => $q->whereRaw('1 = 0'))
                ->whereIn('reel_id', $ids)->pluck('reel_id')->all());
        }

        return $ctx;
    }
}
