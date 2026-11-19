<?php

namespace Tests\Feature;

use App\Models\CreatorReelReport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReelReportTest extends TestCase
{
    use DatabaseTransactions;

    private function reel(): int
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        return DB::table('reels')->insertGetId([
            'description' => 'r', 'thumbnail' => 't.jpg', 'video' => 'v.mp4', 'status' => 1, 'publication_status' => 'published',
            'moderation_status' => 'approved', 'is_always_visible' => 1, 'total_views' => 0, 'total_likes' => 0, 'total_comments' => 0,
            'total_shares' => 0, 'total_store_visits' => 0, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_reporting_a_reel_is_saved_once_and_the_reel_is_marked_reported(): void
    {
        $user = User::create(['f_name' => 'R', 'l_name' => 'Ep', 'email' => 'rep-'.uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1]);
        $reelId = $this->reel();
        Passport::actingAs($user, [], 'api');

        $this->postJson("/api/v1/urban-goodz/reels/{$reelId}/report", ['reason' => 'spam'])->assertOk();
        $this->postJson("/api/v1/urban-goodz/reels/{$reelId}/report", ['reason' => 'spam'])->assertOk();

        $this->assertSame(1, CreatorReelReport::where('reel_id', $reelId)->where('user_id', $user->id)->count());
        $this->postJson("/api/v1/urban-goodz/reels/{$reelId}/report", [])->assertStatus(422);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
