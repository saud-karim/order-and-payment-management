<?php

namespace App\Payments\Gateways;

use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\DTOs\GatewayPaymentRequest;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Exceptions\PaymentGatewayUnavailableException;
use App\Payments\Support\PaymentGatewaySimulator;
use Throwable;

abstract class AbstractSimulatedGateway implements PaymentGatewayInterface
{
    public function __construct(protected PaymentGatewaySimulator $simulator) {}

    public function process(GatewayPaymentRequest $request): PaymentResult
    {
        try {
            return $this->simulator->simulate($this->gatewayKey(), $request);
        } catch (PaymentGatewayUnavailableException $exception) {
            throw $exception;
        } catch (PaymentGatewayException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw PaymentGatewayException::unexpected($this->gatewayKey(), $exception);
        }
    }
}
