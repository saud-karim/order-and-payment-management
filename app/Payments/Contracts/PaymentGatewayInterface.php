<?php

namespace App\Payments\Contracts;

use App\Payments\DTOs\GatewayPaymentRequest;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Exceptions\PaymentGatewayUnavailableException;

interface PaymentGatewayInterface
{
    /**
     * Process a payment against the external (simulated) provider.
     *
     * @throws PaymentGatewayUnavailableException When the provider is temporarily unavailable.
     * @throws PaymentGatewayException When an unexpected processing error occurs.
     */
    public function process(GatewayPaymentRequest $request): PaymentResult;

    /**
     * Registry key for this gateway (matches config/payment.php).
     */
    public function gatewayKey(): string;
}
