<?php

namespace Tests\Unit\Payments;

use App\Payments\DTOs\PaymentResult;
use App\Payments\Enums\PaymentGatewayResultStatus;
use Tests\TestCase;

class PaymentResultTest extends TestCase
{
    public function test_successful_result_shape(): void
    {
        $result = PaymentResult::successful('CC-REF-123');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(PaymentGatewayResultStatus::Successful, $result->status);
        $this->assertSame('CC-REF-123', $result->gatewayReference);
        $this->assertNull($result->failureCode);
    }

    public function test_declined_result_shape(): void
    {
        $result = PaymentResult::declined('card_declined', 'The card was declined by the issuer.');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('card_declined', $result->failureCode);
        $this->assertSame('The card was declined by the issuer.', $result->failureMessage);
    }

    public function test_safe_array_excludes_sensitive_data(): void
    {
        $result = PaymentResult::successful('PAYPAL-ABC');

        $safe = $result->toSafeArray();

        $this->assertSame('successful', $safe['status']);
        $this->assertArrayNotHasKey('pan', $safe);
        $this->assertArrayNotHasKey('cvv', $safe);
        $this->assertArrayNotHasKey('stack_trace', $safe);
    }
}
