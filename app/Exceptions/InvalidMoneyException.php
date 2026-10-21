<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidMoneyException extends RuntimeException
{
    public static function invalidFormat(string $label): self
    {
        return new self(sprintf('The %s must be a valid monetary amount with up to two decimal places.', $label));
    }

    public static function exceedsLimit(string $label, string $limit): self
    {
        return new self(sprintf('The %s exceeds the allowed maximum of %s.', $label, $limit));
    }
}
