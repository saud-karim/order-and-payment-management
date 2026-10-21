<?php

namespace Tests\Concerns;

use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\DTOs\PaymentResult;
use App\Payments\PaymentGatewayFactory;
use Mockery;
use Mockery\MockInterface;
use Throwable;

trait MocksPaymentGateway
{
    /**
     * @return array{0: PaymentGatewayInterface&MockInterface, 1: PaymentGatewayFactory&MockInterface}
     */
    protected function mockPaymentGateway(string $method, PaymentResult|Throwable $result, ?int $processTimes = null): array
    {
        /** @var PaymentGatewayInterface&MockInterface $gateway */
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('gatewayKey')->andReturn($method);

        $processExpectation = $gateway->shouldReceive('process');

        if ($processTimes !== null) {
            $processExpectation->times($processTimes);
        }

        if ($result instanceof Throwable) {
            $processExpectation->andThrow($result);
        } else {
            $processExpectation->andReturn($result);
        }

        /** @var PaymentGatewayFactory&MockInterface $factory */
        $factory = Mockery::mock(PaymentGatewayFactory::class);
        $factory->shouldReceive('make')->with($method)->andReturn($gateway);

        $this->app->instance(PaymentGatewayFactory::class, $factory);

        return [$gateway, $factory];
    }
}
