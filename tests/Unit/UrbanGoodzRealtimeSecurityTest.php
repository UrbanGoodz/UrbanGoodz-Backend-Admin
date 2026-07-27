<?php

namespace Tests\Unit;

use App\Broadcasting\UrbanGoodzChannelAuthorizer;
use App\Events\DeliveryLocationUpdated;
use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Illuminate\Broadcasting\PrivateChannel;
use InvalidArgumentException;
use Tests\TestCase;

class UrbanGoodzRealtimeSecurityTest extends TestCase
{
    private UrbanGoodzChannelAuthorizer $authorizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizer = app(UrbanGoodzChannelAuthorizer::class);
    }

    public function test_broadcast_connection_supports_new_key_and_legacy_fallback(): void
    {
        $source = file_get_contents(config_path('broadcasting.php'));

        $this->assertStringContainsString(
            "env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'null'))",
            $source
        );
    }

    public function test_pusher_defaults_to_verified_tls_and_production_cluster(): void
    {
        $options = config('broadcasting.connections.pusher.options');

        $this->assertSame('us2', $options['cluster']);
        $this->assertSame('https', $options['scheme']);
        $this->assertTrue($options['encrypted']);
        $this->assertTrue($options['useTLS']);
        $this->assertArrayNotHasKey('curl_options', $options);
        $this->assertArrayNotHasKey('host', $options);
    }

    public function test_shopper_cannot_subscribe_to_another_shoppers_orders(): void
    {
        $shopper = (new User())->forceFill(['id' => 41]);

        $this->assertTrue($this->authorizer->shopper($shopper, 41));
        $this->assertFalse($this->authorizer->shopper($shopper, 42));
    }

    public function test_vendor_and_employee_are_limited_to_their_vendor_account(): void
    {
        $vendor = (new Vendor())->forceFill(['id' => 51]);
        $employee = (new VendorEmployee())->forceFill(['id' => 52, 'vendor_id' => 51]);

        $this->assertTrue($this->authorizer->vendor($vendor, 51));
        $this->assertTrue($this->authorizer->vendor($employee, 51));
        $this->assertFalse($this->authorizer->vendor($vendor, 99));
        $this->assertFalse($this->authorizer->vendor($employee, 99));
    }

    public function test_driver_is_limited_to_own_assignment_channel(): void
    {
        $driver = (new DeliveryMan())->forceFill(['id' => 61]);

        $this->assertTrue($this->authorizer->driver($driver, 61));
        $this->assertFalse($this->authorizer->driver($driver, 62));
    }

    public function test_business_and_dispatcher_channels_enforce_account_and_role_scope(): void
    {
        $dispatcher = (new UrbanGoodzBusinessClientUser())->forceFill([
            'id' => 71,
            'business_client_id' => 700,
            'role' => 'dispatcher',
            'is_active' => true,
        ]);
        $billingUser = (new UrbanGoodzBusinessClientUser())->forceFill([
            'id' => 72,
            'business_client_id' => 700,
            'role' => 'billing_manager',
            'is_active' => true,
        ]);

        $this->assertTrue($this->authorizer->business($dispatcher, 700));
        $this->assertFalse($this->authorizer->business($dispatcher, 701));
        $this->assertTrue($this->authorizer->dispatcher($dispatcher, 71));
        $this->assertFalse($this->authorizer->dispatcher($dispatcher, 72));
        $this->assertFalse($this->authorizer->dispatcher($billingUser, 72));
    }

    public function test_payment_and_admin_channels_reject_cross_account_access(): void
    {
        $shopper = (new User())->forceFill(['id' => 81]);
        $admin = (new Admin())->forceFill(['id' => 1]);

        $this->assertTrue($this->authorizer->payment($shopper, 'shopper', 81));
        $this->assertFalse($this->authorizer->payment($shopper, 'shopper', 82));
        $this->assertFalse($this->authorizer->payment($shopper, 'vendor', 81));
        $this->assertTrue($this->authorizer->admin($admin));
        $this->assertFalse($this->authorizer->admin($shopper));
    }

    public function test_domain_updates_only_broadcast_on_private_channels(): void
    {
        $updates = [
            UrbanGoodzRealtimeUpdate::shopperOrder(1, 101, 'confirmed'),
            UrbanGoodzRealtimeUpdate::vendorOrder(2, 101, 'confirmed'),
            UrbanGoodzRealtimeUpdate::driverAssignment(3, 'order', 101, 'assigned'),
            UrbanGoodzRealtimeUpdate::businessRoute(4, 201, 'in_progress'),
            UrbanGoodzRealtimeUpdate::dispatcherLoad(5, 301, 'offered'),
            UrbanGoodzRealtimeUpdate::paymentStatus('shopper', 1, 401, 'paid'),
            UrbanGoodzRealtimeUpdate::supportMessage(501, 601),
            UrbanGoodzRealtimeUpdate::adminOperation('order', 101, 'confirmed'),
        ];

        foreach ($updates as $update) {
            $channels = $update->broadcastOn();

            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertStringStartsWith('private-', $channels[0]->name);
        }

        $this->expectException(InvalidArgumentException::class);
        UrbanGoodzRealtimeUpdate::paymentStatus('unknown', 1, 401, 'paid');
    }

    public function test_legacy_driver_location_event_is_private(): void
    {
        $event = new DeliveryLocationUpdated(91, 29.7604, -95.3698, 'Houston');

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn()[0]);
        $this->assertSame('private-dm_location_91', $event->broadcastOn()[0]->name);
    }

    public function test_guard_specific_broadcast_auth_endpoints_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $uris = $routes->pluck('uri');

        $this->assertTrue($uris->contains('broadcasting/auth'));
        $this->assertTrue($uris->contains('api/v1/realtime/shopper/broadcasting/auth'));
        $this->assertTrue($uris->contains('api/v1/realtime/vendor/broadcasting/auth'));
        $this->assertTrue($uris->contains('api/v1/realtime/driver/broadcasting/auth'));
    }
}
