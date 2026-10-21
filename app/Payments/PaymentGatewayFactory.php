<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\Exceptions\InvalidPaymentGatewayConfigurationException;
use App\Payments\Exceptions\UnsupportedPaymentGatewayException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

class PaymentGatewayFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $method): PaymentGatewayInterface
    {
        $gateways = config('payment.gateways', []);

        if (! Arr::exists($gateways, $method)) {
            throw UnsupportedPaymentGatewayException::forMethod($method);
        }

        $gatewayConfig = $gateways[$method];

        if (! ($gatewayConfig['enabled'] ?? true)) {
            throw InvalidPaymentGatewayConfigurationException::disabled($method);
        }

        $driver = $gatewayConfig['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw InvalidPaymentGatewayConfigurationException::missingDriver($method);
        }

        if (! is_subclass_of($driver, PaymentGatewayInterface::class)) {
            throw InvalidPaymentGatewayConfigurationException::invalidDriver($method, $driver);
        }

        $gateway = $this->container->make($driver);

        if ($gateway->gatewayKey() !== $method) {
            throw InvalidPaymentGatewayConfigurationException::invalidDriver($method, $driver);
        }

        return $gateway;
    }

    /**
     * @return list<string>
     */
    public function registeredMethods(): array
    {
        return array_keys(config('payment.gateways', []));
    }

    /**
     * @return list<string>
     */
    public function enabledMethods(): array
    {
        return collect(config('payment.gateways', []))
            ->filter(fn (array $gateway): bool => (bool) ($gateway['enabled'] ?? true))
            ->keys()
            ->all();
    }
}
