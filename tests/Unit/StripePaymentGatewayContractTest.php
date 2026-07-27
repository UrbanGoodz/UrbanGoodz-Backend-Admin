<?php

namespace Tests\Unit;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\OrderAnywhereRequest;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\StripePaymentGateway;
use App\Services\UrbanGoodzPaymentService;
use LogicException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class StripePaymentGatewayContractTest extends TestCase
{
    public function test_disabled_stripe_fails_closed_instead_of_fabricating_staged_success(): void
    {
        config()->set('urban_goodz_payments.stripe.enabled', false);
        config()->set('urban_goodz_payments.stripe.secret_key', '');

        $gateway = new StripePaymentGateway();

        $this->assertFalse($gateway->isEnabled());
        $this->expectException(LogicException::class);
        $gateway->createPaymentLink(new OrderAnywhereRequest(), 12.34, 'USD', 'OA-TEST');
    }

    public function test_stripe_charge_refund_event_uses_payment_intent_and_cumulative_refund_amount(): void
    {
        config()->set('urban_goodz_payments.stripe.enabled', true);
        config()->set('urban_goodz_payments.stripe.secret_key', 'sk_test_fixture_not_real');

        $events = (new StripePaymentGateway())->parseWebhook(json_encode([
            'id' => 'evt_refund_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_1',
                    'payment_intent' => 'pi_1',
                    'amount' => 5000,
                    'amount_refunded' => 1250,
                    'currency' => 'usd',
                    'metadata' => ['merchant_reference' => 'OA-100'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('evt_refund_1', $events[0]['event_id']);
        $this->assertSame('pi_1', $events[0]['provider_reference']);
        $this->assertSame('OA-100', $events[0]['merchant_reference']);
        $this->assertSame(1250, $events[0]['amount_minor']);
        $this->assertTrue($events[0]['success']);
    }

    public function test_payment_service_resolves_the_provider_stored_on_the_payment(): void
    {
        $activeGateway = Mockery::mock(PaymentGatewayInterface::class);
        $storedGateway = Mockery::mock(PaymentGatewayInterface::class);
        $manager = Mockery::mock(PaymentProviderManager::class);
        $manager->shouldReceive('activeProvider')->once()->andReturn($activeGateway);
        $manager->shouldReceive('resolveProvider')->once()->with('stripe')->andReturn($storedGateway);

        $request = new OrderAnywhereRequest();
        $request->payment_provider = 'stripe';
        $method = new ReflectionMethod(UrbanGoodzPaymentService::class, 'gatewayForRequest');

        $this->assertSame(
            $storedGateway,
            $method->invoke(new UrbanGoodzPaymentService($manager), $request)
        );
    }
}
