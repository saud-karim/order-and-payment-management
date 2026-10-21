<?php

namespace Tests\Stubs;

use App\Payments\Gateways\AbstractSimulatedGateway;

final class MisconfiguredGateway extends AbstractSimulatedGateway
{
    public function gatewayKey(): string
    {
        return 'misconfigured';
    }
}
