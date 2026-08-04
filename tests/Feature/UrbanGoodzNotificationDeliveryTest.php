<?php

namespace Tests\Feature;

use App\Jobs\SendFirebaseNotification;
use App\Models\DeliveryMan;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Services\FirebaseNotificationTransport;
use App\Services\UrbanGoodzNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UrbanGoodzNotificationDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private Vendor $vendor;
    private DeliveryMan $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = (string) random_int(100000, 999999);

        $this->customer = User::firstOrCreate(
            ['email' => "session9-notify-customer-{$suffix}@urbangoodz.test"],
            [
                'f_name' => 'Notify',
                'l_name' => 'Customer',
                'phone' => '312'.$suffix,
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
        DB::table('users')->where('id', $this->customer->id)->update(['cm_firebase_token' => 'session9-customer-fcm-token']);

        $this->vendor = Vendor::firstOrCreate(
            ['email' => "session9-notify-vendor-{$suffix}@urbangoodz.test"],
            [
                'f_name' => 'Notify',
                'l_name' => 'Vendor',
                'phone' => '313'.$suffix,
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
        DB::table('vendors')->where('id', $this->vendor->id)->update(['firebase_token' => 'session9-vendor-fcm-token']);

        $this->driver = DeliveryMan::firstOrCreate(
            ['phone' => '314'.$suffix],
            [
                'f_name' => 'Notify',
                'l_name' => 'Driver',
                'email' => "session9-notify-driver-{$suffix}@urbangoodz.test",
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
            ]
        );
        DB::table('delivery_men')->where('id', $this->driver->id)->update(['fcm_token' => 'session9-driver-fcm-token']);
    }

    public function test_customer_vendor_and_driver_notifications_persist_and_queue(): void
    {
        Queue::fake();
        $service = app(UrbanGoodzNotificationService::class);

        $customerNotification = $service->notifyCustomer(
            $this->customer->id,
            'Customer test',
            'Safe customer notification.',
            ['type' => 'session9_customer']
        );
        $vendorNotification = $service->notifyVendor(
            $this->vendor->id,
            'Vendor test',
            'Safe vendor notification.',
            ['type' => 'session9_vendor']
        );
        $driverNotification = $service->notifyDriver(
            $this->driver->id,
            'Driver test',
            'Safe driver notification.',
            ['type' => 'session9_driver']
        );

        $this->assertDatabaseHas('user_notifications', [
            'id' => $customerNotification->id,
            'user_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $vendorNotification->id,
            'vendor_id' => $this->vendor->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => $driverNotification->id,
            'delivery_man_id' => $this->driver->id,
        ]);

        Queue::assertPushed(SendFirebaseNotification::class, 3);
        Queue::assertPushed(SendFirebaseNotification::class, fn ($job) =>
            $job->recipientType === 'customer' && $job->queue === 'notifications'
        );
        Queue::assertPushed(SendFirebaseNotification::class, fn ($job) =>
            $job->recipientType === 'vendor' && $job->queue === 'notifications'
        );
        Queue::assertPushed(SendFirebaseNotification::class, fn ($job) =>
            $job->recipientType === 'driver' && $job->queue === 'notifications'
        );
    }

    public function test_queue_job_processes_notification_through_transport(): void
    {
        Queue::fake();
        $notification = app(UrbanGoodzNotificationService::class)->notifyCustomer(
            $this->customer->id,
            'Queue processing test',
            'No real customer communication.',
            ['type' => 'session9_queue']
        );
        $transport = Mockery::mock(FirebaseNotificationTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->with('session9-customer-fcm-token', Mockery::on(fn (array $payload) =>
                $payload['type'] === 'session9_queue'
                && $payload['title'] === 'Queue processing test'
            ))
            ->andReturnTrue();

        $job = new SendFirebaseNotification($notification->id, 'customer', $this->customer->id);
        $job->handle($transport);

        $this->addToAssertionCount(1);
    }

    public function test_queue_job_retries_and_logs_terminal_failure_without_token(): void
    {
        Queue::fake();
        $notification = app(UrbanGoodzNotificationService::class)->notifyDriver(
            $this->driver->id,
            'Failure test',
            'Safe driver failure path.',
            ['type' => 'session9_failure']
        );
        $transport = Mockery::mock(FirebaseNotificationTransport::class);
        $transport->shouldReceive('send')->once()->andReturnFalse();
        $job = new SendFirebaseNotification($notification->id, 'driver', $this->driver->id);

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff);
        Log::spy();

        try {
            $job->handle($transport);
            $this->fail('Expected Firebase rejection to throw.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Firebase notification delivery exhausted retries.',
                Mockery::on(fn (array $context) =>
                    $context['notification_id'] === $notification->id
                    && $context['recipient_type'] === 'driver'
                    && ! array_key_exists('token', $context)
                )
            );
    }
}
