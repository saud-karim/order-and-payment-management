<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\Exceptions\PaymentGatewayUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\MocksPaymentGateway;
use Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use MocksPaymentGateway;
    use RefreshDatabase;

    private function createConfirmedOrder(User $user, string $total = '25.00'): Order
    {
        return Order::factory()->for($user)->confirmed()->create([
            'total' => $total,
            'currency' => 'USD',
        ]);
    }

    private function paymentHeaders(string $key = 'idem-key-001'): array
    {
        return ['Idempotency-Key' => $key];
    }

    public function test_unauthenticated_payment_request_is_rejected(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => 'credit_card',
        ], $this->paymentHeaders())->assertUnauthorized();
    }

    public function test_successfully_processes_credit_card_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user, '25.00');

        $this->mockPaymentGateway('credit_card', PaymentResult::successful('CC-GW-123'), 1);

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", [
                'payment_method' => 'credit_card',
            ], $this->paymentHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.payment.status', 'successful')
            ->assertJsonPath('data.payment.amount', '25.00')
            ->assertJsonPath('data.payment.external_payment_id', 'CC-GW-123');
    }

    public function test_successfully_processes_paypal_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user, '40.00');

        $this->mockPaymentGateway('paypal', PaymentResult::successful('PP-GW-999'), 1);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", [
                'payment_method' => 'paypal',
            ], $this->paymentHeaders('paypal-key'))
            ->assertCreated()
            ->assertJsonPath('data.payment.payment_method', 'paypal')
            ->assertJsonPath('data.payment.status', 'successful');
    }

    public function test_declined_payment_is_persisted_as_failed(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway(
            'credit_card',
            PaymentResult::declined('card_declined', 'The card was declined by the issuer.'),
            1
        );

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", [
                'payment_method' => 'credit_card',
            ], $this->paymentHeaders());

        $response->assertOk()
            ->assertJsonPath('data.payment.status', 'failed')
            ->assertJsonPath('data.payment.failure_reason', 'The card was declined by the issuer.');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Failed->value,
        ]);
    }

    public function test_rejects_payment_for_pending_and_cancelled_orders(): void
    {
        $user = User::factory()->create();
        $pending = Order::factory()->for($user)->create(['status' => OrderStatus::Pending, 'total' => '10.00']);
        $cancelled = Order::factory()->for($user)->cancelled()->create(['total' => '10.00']);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$pending->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders('k1'))
            ->assertConflict();

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$cancelled->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders('k2'))
            ->assertConflict();
    }

    public function test_rejects_unsupported_payment_method(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", [
                'payment_method' => 'bitcoin',
            ], $this->paymentHeaders())
            ->assertUnprocessable();
    }

    public function test_rejects_disabled_gateway(): void
    {
        Config::set('payment.gateways.paypal.enabled', false);

        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", [
                'payment_method' => 'paypal',
            ], $this->paymentHeaders())
            ->assertUnprocessable();
    }

    public function test_rejects_cross_user_payment_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->createConfirmedOrder($owner);

        $this->actingAs($intruder, 'api')
            ->postJson("/api/orders/{$order->id}/payments", [
                'payment_method' => 'credit_card',
            ], $this->paymentHeaders())
            ->assertNotFound();
    }

    public function test_idempotency_replay_does_not_invoke_gateway_twice(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway('credit_card', PaymentResult::successful('CC-REPLAY'), 1);

        $headers = $this->paymentHeaders('shared-idem-key');

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $headers)
            ->assertCreated();

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $headers)
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'successful');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_idempotency_key_reuse_with_different_parameters_returns_conflict(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway('credit_card', PaymentResult::successful('CC-1'), 1);

        $headers = $this->paymentHeaders('conflict-key');

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $headers)
            ->assertCreated();

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'paypal'], $headers)
            ->assertConflict();
    }

    public function test_prevents_duplicate_successful_payments(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway('credit_card', PaymentResult::successful('CC-1'), 1);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders('first'))
            ->assertCreated();

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders('second'))
            ->assertConflict();
    }

    public function test_prevents_new_attempt_while_pending_attempt_exists(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        Payment::factory()->for($order)->for($user)->create([
            'status' => PaymentStatus::Pending,
            'amount' => '25.00',
            'operation_reference' => 'pay-pending-001',
            'request_fingerprint' => hash('sha256', 'pending'),
        ]);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders('new-key'))
            ->assertConflict();
    }

    public function test_gateway_timeout_keeps_payment_pending_and_returns_accepted(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway(
            'credit_card',
            PaymentGatewayUnavailableException::forGateway('credit_card'),
            1
        );

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders());

        $response->assertStatus(202)
            ->assertJsonPath('data.payment.status', 'pending');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_unexpected_gateway_error_does_not_leak_details(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway(
            'credit_card',
            PaymentGatewayException::unexpected('credit_card'),
            1
        );

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders());

        $response->assertStatus(503)
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonMissing(['exception', 'trace', 'stack']);
    }

    public function test_payment_response_excludes_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $this->mockPaymentGateway('credit_card', PaymentResult::successful('SAFE-REF'), 1);

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders());

        $response->assertCreated()
            ->assertJsonMissing(['pan', 'cvv', 'card_number', 'gateway_response']);
    }

    public function test_user_can_list_payments_with_pagination(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);
        Payment::factory()->for($order)->for($user)->count(3)->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/payments?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.pagination.per_page', 2);
    }

    public function test_user_can_list_payments_for_owned_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);
        Payment::factory()->for($order)->for($user)->count(2)->create();

        $this->actingAs($user, 'api')
            ->getJson("/api/orders/{$order->id}/payments")
            ->assertOk()
            ->assertJsonCount(2, 'data.payments');
    }

    public function test_user_cannot_list_payments_for_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->createConfirmedOrder($owner);

        $this->actingAs($intruder, 'api')
            ->getJson("/api/orders/{$order->id}/payments")
            ->assertNotFound();
    }

    public function test_order_with_payments_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);

        Payment::factory()->for($order)->for($user)->create([
            'status' => PaymentStatus::Failed,
        ]);

        $this->actingAs($user, 'api')
            ->deleteJson("/api/orders/{$order->id}")
            ->assertConflict();
    }

    public function test_failed_payment_allows_new_attempt_with_new_idempotency_key(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        Payment::factory()->for($order)->for($user)->failed()->create([
            'amount' => '25.00',
            'operation_reference' => 'pay-failed-001',
        ]);

        $this->mockPaymentGateway('credit_card', PaymentResult::successful('CC-NEW'), 1);

        $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'credit_card'], $this->paymentHeaders('retry-key'))
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'successful');
    }
}
