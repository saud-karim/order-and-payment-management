<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentConflictException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\DTOs\GatewayPaymentRequest;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Enums\PaymentGatewayResultStatus;
use App\Payments\Exceptions\InvalidPaymentGatewayConfigurationException;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Exceptions\PaymentGatewayUnavailableException;
use App\Payments\Exceptions\UnsupportedPaymentGatewayException;
use App\Payments\PaymentGatewayFactory;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly PaymentGatewayFactory $gatewayFactory) {}

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginateForUser(User $user, ?int $orderId, int $perPage): LengthAwarePaginator
    {
        if ($orderId !== null) {
            $this->findOwnedOrderOrFail($user, $orderId);
        }

        $query = Payment::query()
            ->where('user_id', $user->id)
            ->with('order')
            ->latest();

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginateForOrder(User $user, int $orderId, int $perPage): LengthAwarePaginator
    {
        $this->findOwnedOrderOrFail($user, $orderId);

        return Payment::query()
            ->where('user_id', $user->id)
            ->where('order_id', $orderId)
            ->latest()
            ->paginate($perPage);
    }

    public function process(User $user, int $orderId, string $paymentMethod, string $idempotencyKey): PaymentProcessingOutcome
    {
        $existingByKey = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existingByKey !== null) {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->firstOrFail();

            $fingerprint = $this->requestFingerprint($user->id, $order, $paymentMethod);
            $this->assertMatchingFingerprint($existingByKey, $fingerprint);

            if ($existingByKey->order_id !== $order->id || $existingByKey->user_id !== $user->id) {
                throw PaymentConflictException::idempotencyKeyMismatch();
            }

            return PaymentProcessingOutcome::replay($existingByKey->fresh());
        }

        $this->assertPaymentMethodIsSupported($paymentMethod);

        $reservation = DB::transaction(function () use ($user, $orderId, $paymentMethod, $idempotencyKey): PaymentProcessingOutcome {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::Confirmed) {
                throw PaymentConflictException::orderNotConfirmed();
            }

            $fingerprint = $this->requestFingerprint($user->id, $order, $paymentMethod);

            $existingByKey = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingByKey !== null) {
                $this->assertMatchingFingerprint($existingByKey, $fingerprint);

                return PaymentProcessingOutcome::replay($existingByKey->fresh());
            }

            if ($order->payments()->where('status', PaymentStatus::Successful)->exists()) {
                throw PaymentConflictException::duplicateSuccessfulPayment();
            }

            if ($order->payments()->where('status', PaymentStatus::Pending)->exists()) {
                throw PaymentConflictException::unresolvedPaymentAttempt();
            }

            $amount = Money::normalize(number_format((float) $order->total, 2, '.', ''));
            $operationReference = sprintf('pay-%d-%s', $order->id, Str::uuid());

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => PaymentStatus::Pending,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'currency' => $order->currency,
                'idempotency_key' => $idempotencyKey,
                'operation_reference' => $operationReference,
                'request_fingerprint' => $fingerprint,
            ]);

            return new PaymentProcessingOutcome(
                payment: $payment,
                httpStatus: 201,
                message: 'Payment attempt created.',
            );
        });

        if ($reservation->replayed) {
            return $reservation;
        }

        $payment = $reservation->payment;

        try {
            $gateway = $this->gatewayFactory->make($paymentMethod);
            $gatewayResult = $gateway->process(new GatewayPaymentRequest(
                orderReference: $orderId,
                amount: Money::normalize(number_format((float) $payment->amount, 2, '.', '')),
                currency: $payment->currency,
                operationReference: (string) $payment->operation_reference,
            ));

            return $this->finalizeGatewayResult($payment, $gatewayResult);
        } catch (PaymentGatewayUnavailableException $exception) {
            return new PaymentProcessingOutcome(
                payment: $payment->fresh(),
                httpStatus: 202,
                message: 'Payment processing is incomplete because the gateway is temporarily unavailable. Retry with the same Idempotency-Key.',
            );
        } catch (PaymentGatewayException $exception) {
            return new PaymentProcessingOutcome(
                payment: $payment->fresh(),
                httpStatus: 503,
                message: 'Payment processing encountered a temporary error. Retry with the same Idempotency-Key.',
            );
        }
    }

    private function finalizeGatewayResult(Payment $payment, PaymentResult $gatewayResult): PaymentProcessingOutcome
    {
        return DB::transaction(function () use ($payment, $gatewayResult): PaymentProcessingOutcome {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Pending) {
                return PaymentProcessingOutcome::replay($payment);
            }

            if ($gatewayResult->status === PaymentGatewayResultStatus::Successful) {
                if (Payment::query()
                    ->where('order_id', $payment->order_id)
                    ->where('status', PaymentStatus::Successful)
                    ->where('id', '!=', $payment->id)
                    ->exists()) {
                    throw PaymentConflictException::duplicateSuccessfulPayment();
                }

                try {
                    $payment->fill([
                        'status' => PaymentStatus::Successful,
                        'external_payment_id' => $gatewayResult->gatewayReference,
                        'failure_reason' => null,
                        'gateway_response' => $gatewayResult->toSafeArray(),
                    ])->save();
                } catch (QueryException $exception) {
                    throw PaymentConflictException::duplicateSuccessfulPayment();
                }

                return new PaymentProcessingOutcome(
                    payment: $payment->fresh(),
                    httpStatus: 201,
                    message: 'Payment processed successfully.',
                );
            }

            $payment->fill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $gatewayResult->failureMessage,
                'gateway_response' => $gatewayResult->toSafeArray(),
            ])->save();

            return new PaymentProcessingOutcome(
                payment: $payment->fresh(),
                httpStatus: 200,
                message: 'Payment was declined by the gateway.',
            );
        });
    }

    private function assertPaymentMethodIsSupported(string $paymentMethod): void
    {
        try {
            $this->gatewayFactory->make($paymentMethod);
        } catch (UnsupportedPaymentGatewayException|InvalidPaymentGatewayConfigurationException $exception) {
            throw ValidationException::withMessages([
                'payment_method' => [$exception->getMessage()],
            ]);
        }
    }

    private function requestFingerprint(int $userId, Order $order, string $paymentMethod): string
    {
        $amount = Money::normalize(number_format((float) $order->total, 2, '.', ''));

        return hash('sha256', implode('|', [
            $userId,
            $order->id,
            $paymentMethod,
            $amount,
            $order->currency,
        ]));
    }

    private function assertMatchingFingerprint(Payment $payment, string $fingerprint): void
    {
        if ($payment->request_fingerprint !== null && $payment->request_fingerprint !== $fingerprint) {
            throw PaymentConflictException::idempotencyKeyMismatch();
        }
    }

    private function findOwnedOrderOrFail(User $user, int $orderId): Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereKey($orderId)
            ->firstOrFail();
    }
}
