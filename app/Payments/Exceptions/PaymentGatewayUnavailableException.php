<?php

namespace App\Payments\Exceptions;

use RuntimeException;

class PaymentGatewayUnavailableException extends RuntimeException
{
    public static function forGateway(string $gatewayKey): self
    {
        return new self(sprintf('Payment gateway [%s] is temporarily unavailable. Please try again later.', $gatewayKey));
    }
}
