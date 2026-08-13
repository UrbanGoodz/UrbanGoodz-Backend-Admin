<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UrbanGoodzCreatorProfile;

class CreatorSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_profile()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'api')->postJson('/api/v1/urban-goodz/creator-space/register', [
            'handle' => 'testcreator',
            'display_name' => 'Test Creator',
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('urban_goodz_creator_profiles', ['handle' => 'testcreator']);
    }

    public function test_profile_returns_data()
    {
        $user = User::factory()->create();
        UrbanGoodzCreatorProfile::create(['user_id' => $user->id, 'handle' => 'test', 'display_name' => 'Test']);
        
        $response = $this->actingAs($user, 'api')->getJson('/api/v1/urban-goodz/creator-space/profile');
        $response->assertStatus(200)->assertJsonStructure(['data' => ['handle']]);
    }
}
