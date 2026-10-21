<?php

namespace App\Payments\Gateways;

final class PayPalGateway extends AbstractSimulatedGateway
{
    public function gatewayKey(): string
    {
        return 'paypal';
    }
}
