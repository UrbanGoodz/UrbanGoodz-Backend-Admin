<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzServiceRequest;
use App\Services\ServiceBookings\HttpSandboxServiceBookingPaymentGateway;
use App\Services\ServiceBookings\StripeServiceBookingPaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ServiceBookingContractTest extends TestCase
{
    public function test_stripe_gateway_uses_sandbox_key_and_validates_response(): void
    {
        config()->set('service_bookings.payment', [
            'sandbox' => true,
            'provider' => 'stripe',
            'stripe_endpoint' => 'https://api.stripe.com/v1/payment_intents',
            'stripe_secret_sandbox' => 'sk_test_fixture',
            'stripe_secret_live' => null,
            'timeout' => 5,
        ]);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'pi_stripe_test_1', 'status' => 'succeeded',
        ], 200)]);
        $booking = new UrbanGoodzServiceRequest(['deposit_amount_minor' => 2500, 'quoted_amount_minor' => 10000, 'currency' => 'USD']);
        $booking->id = 41;
        $result = (new StripeServiceBookingPaymentGateway())->charge($booking, 'pm_fixture', 'booking-41');
        $this->assertSame(['id' => 'pi_stripe_test_1', 'status' => 'succeeded'], $result);
        Http::assertSent(fn($request) => $request['amount'] === 2500 && $request['metadata[booking_id]'] === 41 && str_contains($request->url(), 'stripe.com'));
    }

    public function test_stripe_gateway_uses_live_key_when_sandbox_false(): void
    {
        config()->set('service_bookings.payment', [
            'sandbox' => false,
            'provider' => 'stripe',
            'stripe_endpoint' => 'https://api.stripe.com/v1/payment_intents',
            'stripe_secret_sandbox' => null,
            'stripe_secret_live' => 'sk_live_fixture',
            'timeout' => 5,
        ]);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'pi_stripe_live_1', 'status' => 'requires_capture',
        ], 200)]);
        $booking = new UrbanGoodzServiceRequest(['deposit_amount_minor' => 5000, 'quoted_amount_minor' => 20000, 'currency' => 'USD']);
        $booking->id = 99;
        $result = (new StripeServiceBookingPaymentGateway())->charge($booking, 'pm_live_fixture', 'booking-99');
        $this->assertSame(['id' => 'pi_stripe_live_1', 'status' => 'requires_capture'], $result);
        Http::assertSent(fn($request) => $request['metadata[environment]'] === 'live');
    }

    public function test_stripe_gateway_fails_closed_on_missing_key(): void
    {
        config()->set('service_bookings.payment', [
            'sandbox' => true,
            'provider' => 'stripe',
            'stripe_endpoint' => 'https://api.stripe.com/v1/payment_intents',
            'stripe_secret_sandbox' => null,
            'stripe_secret_live' => null,
            'timeout' => 5,
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sandbox secret key is not configured');
        (new StripeServiceBookingPaymentGateway())->charge(
            new UrbanGoodzServiceRequest(['deposit_amount_minor' => 100, 'currency' => 'USD']),
            'pm_fixture', 'no-key-1'
        );
    }

    public function test_stripe_gateway_fails_closed_on_rejection(): void
    {
        config()->set('service_bookings.payment', [
            'sandbox' => true,
            'provider' => 'stripe',
            'stripe_endpoint' => 'https://api.stripe.com/v1/payment_intents',
            'stripe_secret_sandbox' => 'sk_test_fixture',
            'stripe_secret_live' => null,
            'timeout' => 5,
        ]);
        Http::fake(['api.stripe.com/*' => Http::response([
            'error' => ['message' => 'Your card was declined.', 'type' => 'card_error'],
        ], 402)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Your card was declined');
        (new StripeServiceBookingPaymentGateway())->charge(
            new UrbanGoodzServiceRequest(['deposit_amount_minor' => 100, 'currency' => 'USD']),
            'pm_fixture', 'reject-1'
        );
    }

    public function test_sandbox_gateway_uses_server_configuration_and_validates_response(): void
    {
        config()->set('service_bookings.payment', ['sandbox'=>true,'provider'=>'http','endpoint'=>'https://sandbox.example.test/charges','secret'=>'server-only','timeout'=>5]);
        Http::fake(['sandbox.example.test/*'=>Http::response(['id'=>'sandbox_tx_1','status'=>'succeeded'],200)]);
        $booking=new UrbanGoodzServiceRequest(['deposit_amount_minor'=>2500,'quoted_amount_minor'=>10000,'currency'=>'USD']); $booking->id=41;
        $result=(new HttpSandboxServiceBookingPaymentGateway())->charge($booking,'tok_fixture','booking-41');
        $this->assertSame(['id'=>'sandbox_tx_1','status'=>'succeeded'],$result);
        Http::assertSent(fn($request)=>$request['amount_minor']===2500 && $request['metadata']['booking_id']===41 && $request->hasHeader('Authorization'));
    }

    public function test_sandbox_gateway_fails_closed_on_provider_rejection(): void
    {
        config()->set('service_bookings.payment', ['sandbox'=>true,'provider'=>'http','endpoint'=>'https://sandbox.example.test/charges','secret'=>'server-only','timeout'=>5]);
        Http::fake(['sandbox.example.test/*'=>Http::response(['error'=>'declined'],402)]);
        $this->expectException(RuntimeException::class);
        (new HttpSandboxServiceBookingPaymentGateway())->charge(new UrbanGoodzServiceRequest(['deposit_amount_minor'=>100,'currency'=>'USD']),'tok_fixture','reject-1');
    }

    public function test_ownership_status_money_and_notifications_are_server_enforced(): void
    {
        $customer=file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/ServiceBookingCustomerController.php');
        $vendor=file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Vendor/ServiceBookingController.php');
        $workflow=file_get_contents(__DIR__.'/../../app/Services/ServiceBookings/ServiceBookingWorkflow.php');
        $this->assertStringContainsString('(int)$booking->user_id===(int)$request->user()->id',$customer);
        $this->assertStringContainsString('(int)$booking->provider_id===(int)$provider->id',$vendor);
        $this->assertStringContainsString('Illegal service booking transition',$workflow);
        $this->assertStringContainsString('platform_fee_percent',$workflow);
        $this->assertStringContainsString('UrbanGoodzNotificationService',$workflow);
        $this->assertStringContainsString('notifyCustomer',$workflow);
        $this->assertStringContainsString('notifyVendor',$workflow);
    }

    public function test_all_required_categories_are_configured(): void
    {
        $this->assertSame(['barber','hair_stylist','braider','nail_technician','makeup_artist','mobile_mechanic','photographer','dj','contractor','tax_professional','home_health_provider','personal_trainer'],config('service_bookings.categories'));
    }
}
