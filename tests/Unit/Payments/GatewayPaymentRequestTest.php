<?php

namespace Tests\Unit\Payments;

use App\Payments\DTOs\GatewayPaymentRequest;
use Tests\TestCase;

class GatewayPaymentRequestTest extends TestCase
{
    public function test_dto_exposes_required_fields(): void
    {
        $request = new GatewayPaymentRequest(
            orderReference: 42,
            amount: '19.99',
            currency: 'USD',
            operationReference: 'pay-op-001',
        );

        $this->assertSame(42, $request->orderReference);
        $this->assertSame('19.99', $request->amount);
        $this->assertSame('USD', $request->currency);
        $this->assertSame('pay-op-001', $request->operationReference);
    }

    public function test_safe_array_contains_no_sensitive_fields(): void
    {
        $request = new GatewayPaymentRequest(1, '10.00', 'USD', 'op-ref');

        $safe = $request->toSafeArray();

        $this->assertArrayHasKey('order_reference', $safe);
        $this->assertArrayNotHasKey('pan', $safe);
        $this->assertArrayNotHasKey('cvv', $safe);
        $this->assertArrayNotHasKey('card_number', $safe);
    }
}
