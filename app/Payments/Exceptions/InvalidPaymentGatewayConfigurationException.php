<?php

namespace App\Payments\Exceptions;

use RuntimeException;

class InvalidPaymentGatewayConfigurationException extends RuntimeException
{
    public static function missingDriver(string $method): self
    {
        return new self(sprintf('Payment gateway [%s] is missing a driver class.', $method));
    }

    public static function invalidDriver(string $method, string $driver): self
    {
        return new self(sprintf('Payment gateway [%s] driver [%s] must implement PaymentGatewayInterface.', $method, $driver));
    }

    public static function disabled(string $method): self
    {
        return new self(sprintf('Payment gateway [%s] is disabled.', $method));
    }
}
