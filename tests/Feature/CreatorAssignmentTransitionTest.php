<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * A creator may only respond to a brand's approval (approved -> accepted/declined) and finish
 * accepted work (accepted -> completed). They must not activate pending or rejected assignments.
 */
class CreatorAssignmentTransitionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private User $user;
    private int $profile;
    private int $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->user = User::create(['f_name' => 'Cr', 'l_name' => 'Eator', 'email' => 'creator-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x')]);
        $app = $this->insertRow('urban_goodz_creator_applications', ['email' => $this->user->email, 'phone' => $this->user->phone]);
        $this->profile = $this->insertRow('urban_goodz_creator_profiles', ['creator_application_id' => $app, 'is_approved' => 1, 'status' => 'approved', 'handle' => 'h'.uniqid()]);
        $this->campaign = $this->insertRow('urban_goodz_creator_campaigns', ['status' => 'active']);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function assignment(string $status): int
    {
        return $this->insertRow('urban_goodz_creator_campaign_assignments', ['campaign_id' => $this->campaign, 'creator_profile_id' => $this->profile, 'approval_status' => $status]);
    }

    private function move(int $id, string $to)
    {
        return $this->actingAs($this->user, 'api')->putJson("/api/v1/customer/creator/campaigns/{$id}", ['status' => $to]);
    }

    public function test_a_creator_cannot_activate_work_the_brand_has_not_approved(): void
    {
        foreach (['pending', 'rejected'] as $state) {
            foreach (['accepted', 'completed'] as $to) {
                $id = $this->assignment($state);
                $this->move($id, $to)->assertStatus(409);
                $this->assertSame($state, DB::table('urban_goodz_creator_campaign_assignments')->where('id', $id)->value('approval_status'), "{$state} -> {$to} must be refused");
            }
        }
    }

    public function test_the_legal_path_still_works(): void
    {
        $id = $this->assignment('approved');
        $this->move($id, 'completed')->assertStatus(409);          // cannot finish before accepting
        $this->move($id, 'accepted')->assertOk();
        $this->move($id, 'completed')->assertOk();
        $this->move($id, 'declined')->assertStatus(409);           // completed is final
        $this->assertSame('completed', DB::table('urban_goodz_creator_campaign_assignments')->where('id', $id)->value('approval_status'));
    }
}
