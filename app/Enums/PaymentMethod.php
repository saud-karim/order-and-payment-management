<?php

namespace App\Enums;

/**
 * Persisted payment method identifiers for the domain model.
 *
 * Gateway resolution uses config/payment.php as the runtime registry.
 * Adding a gateway requires config registration; update this enum when the
 * API and database should accept the new method explicitly.
 */
enum PaymentMethod: string
{
    case CreditCard = 'credit_card';
    case PayPal = 'paypal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
