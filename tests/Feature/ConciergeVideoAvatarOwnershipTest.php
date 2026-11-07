<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Video avatar rooms are billed per minute. Only the person who opened one may end it,
 * and a double tap / app restart must resume the live room rather than open a second.
 */
class ConciergeVideoAvatarOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $tag): User
    {
        return User::create([
            'f_name' => 'Vid', 'l_name' => $tag, 'email' => "vid-{$tag}-".uniqid().'@urbangoodz.test',
            'phone' => '1'.random_int(1000000000, 1999999999), 'password' => bcrypt('x'),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('urban_goodz_ai.tavus', ['api_key' => 'test-key-1234567890', 'face_id' => 'f1', 'pal_id' => 'p1', 'base_url' => 'https://tavus.test/v2']);
        Cache::flush();
    }

    public function test_starting_twice_resumes_the_same_room_and_calls_the_provider_once(): void
    {
        Http::fake(['tavus.test/*' => Http::response(['conversation_id' => 'c_1', 'conversation_url' => 'https://room/1'], 200)]);
        $me = $this->user('a');

        $first = $this->actingAs($me, 'api')->postJson('/api/v1/urban-goodz/ai-concierge/video-avatar/start')->assertOk();
        $second = $this->actingAs($me, 'api')->postJson('/api/v1/urban-goodz/ai-concierge/video-avatar/start')->assertOk()->assertJsonPath('resumed', true);

        $this->assertSame($first->json('data.conversation_id'), $second->json('data.conversation_id'));
        Http::assertSentCount(1);
    }

    public function test_another_customer_cannot_end_my_room(): void
    {
        Http::fake(['tavus.test/*' => Http::response(['conversation_id' => 'c_2', 'conversation_url' => 'https://room/2'], 200)]);
        $me = $this->user('owner');
        $other = $this->user('other');

        $this->actingAs($me, 'api')->postJson('/api/v1/urban-goodz/ai-concierge/video-avatar/start')->assertOk();

        $this->actingAs($other, 'api')->postJson('/api/v1/urban-goodz/ai-concierge/video-avatar/c_2/end')->assertNotFound();
        Http::assertSentCount(1); // no /end call reached the provider

        $this->actingAs($me, 'api')->postJson('/api/v1/urban-goodz/ai-concierge/video-avatar/c_2/end')->assertOk();
        // After ending, a fresh start opens a new room.
        $this->actingAs($me, 'api')->postJson('/api/v1/urban-goodz/ai-concierge/video-avatar/start')->assertOk()->assertJsonMissing(['resumed' => true]);
    }
}
