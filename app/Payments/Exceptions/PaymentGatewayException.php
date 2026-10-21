<?php

namespace App\Payments\Exceptions;

use RuntimeException;
use Throwable;

class PaymentGatewayException extends RuntimeException
{
    public static function unexpected(string $gatewayKey, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('An unexpected error occurred while processing payment via [%s].', $gatewayKey),
            0,
            $previous
        );
    }
}
