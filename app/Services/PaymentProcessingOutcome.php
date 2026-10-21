<?php

namespace App\Services;

use App\Models\Payment;

final readonly class PaymentProcessingOutcome
{
    public function __construct(
        public Payment $payment,
        public int $httpStatus,
        public string $message,
        public bool $replayed = false,
    ) {}

    public static function replay(Payment $payment, string $message = 'Existing payment result returned.'): self
    {
        $status = match ($payment->status->value) {
            'pending' => 202,
            default => 200,
        };

        return new self(
            payment: $payment,
            httpStatus: $status,
            message: $message,
            replayed: true,
        );
    }
}
