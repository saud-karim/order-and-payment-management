<?php

namespace App\Payments\Exceptions;

use RuntimeException;

class UnsupportedPaymentGatewayException extends RuntimeException
{
    public static function forMethod(string $method): self
    {
        return new self(sprintf('Payment gateway [%s] is not supported.', $method));
    }
}
