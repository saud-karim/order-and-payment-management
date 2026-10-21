<?php

namespace Tests\Unit\Payments;

use App\Payments\DTOs\GatewayPaymentRequest;
use App\Payments\Enums\PaymentGatewayResultStatus;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\Support\PaymentGatewaySimulator;
use Tests\TestCase;

class PayPalGatewayTest extends TestCase
{
    private PayPalGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new PayPalGateway(new PaymentGatewaySimulator);
    }

    public function test_successful_simulated_payment(): void
    {
        $request = new GatewayPaymentRequest(5, '100.00', 'USD', 'paypal-op-555');

        $result = $this->gateway->process($request);

        $this->assertSame(PaymentGatewayResultStatus::Successful, $result->status);
        $this->assertStringStartsWith('PAYPAL-', $result->gatewayReference);
    }

    public function test_declined_simulated_payment(): void
    {
        $request = new GatewayPaymentRequest(5, '100.00', 'USD', 'paypal-op-555-DECLINE');

        $result = $this->gateway->process($request);

        $this->assertSame(PaymentGatewayResultStatus::Declined, $result->status);
        $this->assertSame('paypal_payment_denied', $result->failureCode);
    }
}
