<?php

namespace App\Payments\Gateways;

final class CreditCardGateway extends AbstractSimulatedGateway
{
    public function gatewayKey(): string
    {
        return 'credit_card';
    }
}
