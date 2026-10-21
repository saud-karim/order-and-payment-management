<?php

namespace App\Exceptions;

use RuntimeException;

class OrderConflictException extends RuntimeException
{
    public static function hasPayments(): self
    {
        return new self('Order cannot be deleted because it has associated payments.');
    }

    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self(sprintf('Cannot transition order status from %s to %s.', $from, $to));
    }

    public static function confirmedOrderHasPayments(): self
    {
        return new self('Confirmed orders with payment attempts cannot be cancelled.');
    }

    public static function orderNotEditable(string $status): self
    {
        return new self(sprintf('Orders in %s status cannot be modified.', $status));
    }
}
