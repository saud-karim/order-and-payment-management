<?php

namespace App\Support;

use App\Exceptions\InvalidMoneyException;
use InvalidArgumentException;

final class Money
{
    public const SCALE = 2;

    public static function multiply(string $unitPrice, int $quantity): string
    {
        self::assertValidAmount($unitPrice, 'unit price');

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        return bcmul(self::normalize($unitPrice), (string) $quantity, self::SCALE);
    }

    public static function add(string ...$amounts): string
    {
        $sum = '0.00';

        foreach ($amounts as $amount) {
            self::assertValidAmount($amount, 'amount');
            $sum = bcadd($sum, self::normalize($amount), self::SCALE);
        }

        return $sum;
    }

    public static function assertWithinLimit(string $amount, string $limit, string $label): void
    {
        self::assertValidAmount($amount, $label);

        if (bccomp(self::normalize($amount), self::normalize($limit), self::SCALE) > 0) {
            throw InvalidMoneyException::exceedsLimit($label, $limit);
        }
    }

    public static function normalize(string $amount): string
    {
        self::assertValidAmount($amount, 'amount');

        return bcadd($amount, '0', self::SCALE);
    }

    public static function assertValidAmount(string $amount, string $label): void
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw InvalidMoneyException::invalidFormat($label);
        }
    }
}
