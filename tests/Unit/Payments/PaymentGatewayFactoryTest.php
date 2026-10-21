<?php

namespace Tests\Unit\Payments;

use App\Payments\Exceptions\InvalidPaymentGatewayConfigurationException;
use App\Payments\Exceptions\UnsupportedPaymentGatewayException;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\PaymentGatewayFactory;
use Illuminate\Support\Facades\Config;
use Tests\Stubs\MisconfiguredGateway;
use Tests\TestCase;

class PaymentGatewayFactoryTest extends TestCase
{
    private PaymentGatewayFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->app->make(PaymentGatewayFactory::class);
    }

    public function test_factory_resolves_credit_card_gateway(): void
    {
        $gateway = $this->factory->make('credit_card');

        $this->assertInstanceOf(CreditCardGateway::class, $gateway);
        $this->assertSame('credit_card', $gateway->gatewayKey());
    }

    public function test_factory_resolves_paypal_gateway(): void
    {
        $gateway = $this->factory->make('paypal');

        $this->assertInstanceOf(PayPalGateway::class, $gateway);
        $this->assertSame('paypal', $gateway->gatewayKey());
    }

    public function test_factory_rejects_unsupported_gateway(): void
    {
        $this->expectException(UnsupportedPaymentGatewayException::class);

        $this->factory->make('bitcoin');
    }

    public function test_factory_rejects_disabled_gateway(): void
    {
        Config::set('payment.gateways.paypal.enabled', false);

        $this->expectException(InvalidPaymentGatewayConfigurationException::class);

        $this->factory->make('paypal');
    }

    public function test_factory_rejects_missing_driver(): void
    {
        Config::set('payment.gateways.stripe', [
            'enabled' => true,
        ]);

        $this->expectException(InvalidPaymentGatewayConfigurationException::class);

        $this->factory->make('stripe');
    }

    public function test_factory_rejects_invalid_driver_class(): void
    {
        Config::set('payment.gateways.stripe', [
            'enabled' => true,
            'driver' => \stdClass::class,
        ]);

        $this->expectException(InvalidPaymentGatewayConfigurationException::class);

        $this->factory->make('stripe');
    }

    public function test_factory_rejects_gateway_key_mismatch(): void
    {
        Config::set('payment.gateways.stripe', [
            'enabled' => true,
            'driver' => MisconfiguredGateway::class,
        ]);

        $this->expectException(InvalidPaymentGatewayConfigurationException::class);

        $this->factory->make('stripe');
    }
}
