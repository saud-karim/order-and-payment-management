<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentConflictException extends RuntimeException
{
    public static function orderNotConfirmed(): self
    {
        return new self('Payments can only be processed for confirmed orders.');
    }

    public static function duplicateSuccessfulPayment(): self
    {
        return new self('This order already has a successful payment.');
    }

    public static function unresolvedPaymentAttempt(): self
    {
        return new self('This order has an unresolved payment attempt. Retry with the same Idempotency-Key or wait for completion.');
    }

    public static function idempotencyKeyMismatch(): self
    {
        return new self('Idempotency-Key was reused with different payment parameters.');
    }

    public static function paymentAlreadyFinalized(): self
    {
        return new self('Payment attempt is no longer pending and cannot be modified.');
    }
}
