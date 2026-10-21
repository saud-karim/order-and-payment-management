<?php

namespace App\Payments\DTOs;

final readonly class GatewayPaymentRequest
{
    public function __construct(
        public int $orderReference,
        public string $amount,
        public string $currency,
        public string $operationReference,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toSafeArray(): array
    {
        return [
            'order_reference' => $this->orderReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'operation_reference' => $this->operationReference,
        ];
    }
}
