<?php

namespace Tests\Unit\Payments;

use App\Payments\DTOs\GatewayPaymentRequest;
use App\Payments\Enums\PaymentGatewayResultStatus;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Exceptions\PaymentGatewayUnavailableException;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Support\PaymentGatewaySimulator;
use Tests\TestCase;

class CreditCardGatewayTest extends TestCase
{
    private CreditCardGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new CreditCardGateway(new PaymentGatewaySimulator);
    }

    public function test_successful_simulated_charge(): void
    {
        $request = new GatewayPaymentRequest(10, '25.00', 'USD', 'order-10-charge-001');

        $result = $this->gateway->process($request);

        $this->assertSame(PaymentGatewayResultStatus::Successful, $result->status);
        $this->assertStringStartsWith('CREDIT_CARD-', $result->gatewayReference);
        $this->assertNotSame('', $result->gatewayReference);
    }

    public function test_declined_simulated_charge(): void
    {
        $request = new GatewayPaymentRequest(10, '25.00', 'USD', 'order-10-charge-001-DECLINE');

        $result = $this->gateway->process($request);

        $this->assertSame(PaymentGatewayResultStatus::Declined, $result->status);
        $this->assertSame('card_declined', $result->failureCode);
    }

    public function test_simulated_timeout_is_temporary_unavailability(): void
    {
        $request = new GatewayPaymentRequest(10, '25.00', 'USD', 'order-10-charge-001-TIMEOUT');

        $this->expectException(PaymentGatewayUnavailableException::class);

        $this->gateway->process($request);
    }

    public function test_simulated_processing_error_is_unexpected(): void
    {
        $request = new GatewayPaymentRequest(10, '25.00', 'USD', 'order-10-charge-001-ERROR');

        $this->expectException(PaymentGatewayException::class);

        $this->gateway->process($request);
    }
}
