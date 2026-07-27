<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzRealtimeAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('broadcasting.default', 'pusher');
        Config::set('broadcasting.connections.pusher.key', 'test-public-key');
        Config::set('broadcasting.connections.pusher.secret', 'test-signing-secret');
        Config::set('broadcasting.connections.pusher.app_id', 'test-app');

        Broadcast::setDefaultDriver('pusher');
        require base_path('routes/channels.php');
    }

    public function test_shopper_can_authorize_only_own_order_channel(): void
    {
        $shopper = User::query()->create([
            'f_name' => 'Realtime',
            'l_name' => 'Shopper',
            'email' => 'realtime-shopper@urbangoodz.test',
            'password' => bcrypt('not-a-production-password'),
        ]);

        $this->actingAs($shopper, 'api');

        $this->postJson('/api/v1/realtime/shopper/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-ug.shopper.{$shopper->id}.orders",
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->postJson('/api/v1/realtime/shopper/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-ug.shopper.999999999.orders',
        ])->assertForbidden();
    }
}
