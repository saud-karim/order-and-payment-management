<?php

namespace App\Payments\Support;

use App\Payments\DTOs\GatewayPaymentRequest;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Exceptions\PaymentGatewayUnavailableException;
use Illuminate\Support\Str;

class PaymentGatewaySimulator
{
    public function simulate(string $gatewayKey, GatewayPaymentRequest $request): PaymentResult
    {
        $operationReference = $request->operationReference;

        if (Str::endsWith($operationReference, (string) config('payment.simulator.unavailable_suffix'))) {
            throw PaymentGatewayUnavailableException::forGateway($gatewayKey);
        }

        if (Str::endsWith($operationReference, (string) config('payment.simulator.error_suffix'))) {
            throw PaymentGatewayException::unexpected($gatewayKey);
        }

        if (Str::endsWith($operationReference, (string) config('payment.simulator.decline_suffix'))) {
            return PaymentResult::declined(
                failureCode: $this->declineCode($gatewayKey),
                failureMessage: $this->declineMessage($gatewayKey),
            );
        }

        return PaymentResult::successful(
            gatewayReference: $this->generateGatewayReference($gatewayKey, $request),
        );
    }

    private function generateGatewayReference(string $gatewayKey, GatewayPaymentRequest $request): string
    {
        return strtoupper($gatewayKey).'-'.hash('sha256', $request->operationReference.'|'.$request->orderReference);
    }

    private function declineCode(string $gatewayKey): string
    {
        return match ($gatewayKey) {
            'credit_card' => 'card_declined',
            'paypal' => 'paypal_payment_denied',
            default => 'payment_declined',
        };
    }

    private function declineMessage(string $gatewayKey): string
    {
        return match ($gatewayKey) {
            'credit_card' => 'The card was declined by the issuer.',
            'paypal' => 'PayPal rejected the payment.',
            default => 'The payment was declined.',
        };
    }
}
