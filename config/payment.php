<?php

use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway registry
    |--------------------------------------------------------------------------
    |
    | Each key is the public payment method identifier stored on payments.
    | The driver class must implement PaymentGatewayInterface.
    |
    */
    'gateways' => [
        'credit_card' => [
            'driver' => CreditCardGateway::class,
            'enabled' => env('PAYMENT_GATEWAY_CREDIT_CARD_ENABLED', true),
        ],
        'paypal' => [
            'driver' => PayPalGateway::class,
            'enabled' => env('PAYMENT_GATEWAY_PAYPAL_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Simulated gateway behaviour (sandbox / tests)
    |--------------------------------------------------------------------------
    |
    | Outcomes are driven by the server-issued operation reference suffix.
    | These suffixes are configuration-only — not client-controlled API fields.
    |
    */
    'simulator' => [
        'decline_suffix' => env('PAYMENT_SIM_DECLINE_SUFFIX', '-DECLINE'),
        'unavailable_suffix' => env('PAYMENT_SIM_UNAVAILABLE_SUFFIX', '-TIMEOUT'),
        'error_suffix' => env('PAYMENT_SIM_ERROR_SUFFIX', '-ERROR'),
    ],

];
