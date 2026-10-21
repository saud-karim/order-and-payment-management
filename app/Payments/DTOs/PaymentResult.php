<?php

namespace App\Payments\DTOs;

use App\Payments\Enums\PaymentGatewayResultStatus;

final readonly class PaymentResult
{
    public function __construct(
        public PaymentGatewayResultStatus $status,
        public string $gatewayReference,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}

    public static function successful(string $gatewayReference): self
    {
        return new self(
            status: PaymentGatewayResultStatus::Successful,
            gatewayReference: $gatewayReference,
        );
    }

    public static function declined(string $failureCode, string $failureMessage): self
    {
        return new self(
            status: PaymentGatewayResultStatus::Declined,
            gatewayReference: '',
            failureCode: $failureCode,
            failureMessage: $failureMessage,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentGatewayResultStatus::Successful;
    }

    /**
     * @return array<string, string|null>
     */
    public function toSafeArray(): array
    {
        return [
            'status' => $this->status->value,
            'gateway_reference' => $this->gatewayReference !== '' ? $this->gatewayReference : null,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
        ];
    }
}
