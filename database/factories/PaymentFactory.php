<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'external_payment_id' => fake()->uuid(),
            'status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::CreditCard,
            'amount' => '10.00',
            'currency' => config('orders.default_currency'),
            'failure_reason' => null,
            'idempotency_key' => fake()->unique()->uuid(),
            'operation_reference' => 'pay-factory-'.fake()->unique()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
            'gateway_response' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Simulated failure',
        ]);
    }
}
