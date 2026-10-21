<?php

namespace Tests\Unit\Support;

use App\Exceptions\InvalidMoneyException;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_multiply_calculates_line_total_with_scale(): void
    {
        $this->assertSame('19.98', Money::multiply('9.99', 2));
        $this->assertSame('30.00', Money::multiply('10.00', 3));
    }

    public function test_add_sums_amounts_without_float_drift(): void
    {
        $total = Money::add('0.10', '0.20', '0.70');

        $this->assertSame('1.00', $total);
    }

    public function test_rejects_invalid_monetary_format(): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::multiply('9.999', 1);
    }

    public function test_rejects_total_above_configured_limit(): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::assertWithinLimit('9999999999.99', '100.00', 'order total');
    }

    #[DataProvider('overflowMultiplicationProvider')]
    public function test_multiply_respects_line_total_limit(string $unitPrice, int $quantity): void
    {
        $lineTotal = Money::multiply($unitPrice, $quantity);

        $this->expectException(InvalidMoneyException::class);

        Money::assertWithinLimit($lineTotal, config('orders.money.max_line_total'), 'line total');
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function overflowMultiplicationProvider(): array
    {
        return [
            'large unit price' => ['99999999.99', 2],
        ];
    }
}
