<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validItemPayload(): array
    {
        return [
            'product_name' => 'Widget',
            'quantity' => 2,
            'unit_price' => '10.50',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validOrderPayload(): array
    {
        return [
            'customer_name' => 'Acme Corp',
            'customer_email' => 'billing@acme.test',
            'items' => [$this->validItemPayload()],
        ];
    }

    public function test_unauthenticated_user_cannot_access_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_user_can_create_order_with_multiple_items_and_server_total(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/orders', [
            'customer_name' => 'Acme Corp',
            'customer_email' => 'billing@acme.test',
            'items' => [
                ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => '10.50'],
                ['product_name' => 'Gadget', 'quantity' => 1, 'unit_price' => '5.25'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.total', '26.25')
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.currency', 'USD')
            ->assertJsonCount(2, 'data.order.items');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => '26.25',
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_create_order_rejects_empty_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'customer_name' => 'Acme Corp',
                'customer_email' => 'billing@acme.test',
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_create_order_rejects_invalid_quantity_and_price(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'customer_name' => 'Acme Corp',
                'customer_email' => 'billing@acme.test',
                'items' => [
                    ['product_name' => 'Widget', 'quantity' => 0, 'unit_price' => '10.00'],
                ],
            ])
            ->assertUnprocessable();

        $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'customer_name' => 'Acme Corp',
                'customer_email' => 'billing@acme.test',
                'items' => [
                    ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => '10.999'],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_create_order_rejects_client_supplied_total_and_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/orders', [
            ...$this->validOrderPayload(),
            'user_id' => $otherUser->id,
            'total' => '1.00',
            'status' => 'confirmed',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.total', '21.00')
            ->assertJsonPath('data.order.status', 'pending');

        $this->assertDatabaseMissing('orders', [
            'user_id' => $otherUser->id,
            'total' => '1.00',
        ]);
    }

    public function test_user_can_filter_orders_by_status(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create(['status' => OrderStatus::Pending, 'total' => '10.00']);
        Order::factory()->for($user)->confirmed()->create(['total' => '20.00']);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/orders?status=confirmed');

        $response->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.status', 'confirmed');
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/orders?status=shipped')
            ->assertUnprocessable();
    }

    public function test_orders_index_supports_pagination_with_cap(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->count(20)->create(['total' => '5.00']);

        $this->actingAs($user, 'api')
            ->getJson('/api/orders?per_page=5')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonCount(5, 'data.orders');

        $this->actingAs($user, 'api')
            ->getJson('/api/orders?per_page=500')
            ->assertUnprocessable();
    }

    public function test_user_can_update_pending_order_and_recalculate_total(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user, 'api')
            ->postJson('/api/orders', $this->validOrderPayload());

        $orderId = $createResponse->json('data.order.id');

        $updateResponse = $this->actingAs($user, 'api')
            ->putJson("/api/orders/{$orderId}", [
                'items' => [
                    ['product_name' => 'Widget', 'quantity' => 3, 'unit_price' => '10.00'],
                ],
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.order.total', '30.00');
    }

    public function test_invalid_status_transition_returns_conflict(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create(['total' => '15.00']);

        $this->actingAs($user, 'api')
            ->patchJson("/api/orders/{$order->id}", ['status' => 'pending'])
            ->assertConflict();

        $order->update(['status' => OrderStatus::Cancelled]);

        $this->actingAs($user, 'api')
            ->patchJson("/api/orders/{$order->id}", ['status' => 'confirmed'])
            ->assertConflict();
    }

    public function test_confirmed_order_cannot_be_modified(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create(['total' => '15.00']);

        $this->actingAs($user, 'api')
            ->putJson("/api/orders/{$order->id}", [
                'customer_name' => 'New Name',
            ])
            ->assertConflict();
    }

    public function test_confirmed_order_can_be_cancelled_without_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create(['total' => '15.00']);

        $this->actingAs($user, 'api')
            ->patchJson("/api/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancelled');
    }

    public function test_confirmed_order_cannot_be_cancelled_when_payments_exist(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create(['total' => '15.00']);

        Payment::factory()->for($order)->for($user)->failed()->create([
            'amount' => '15.00',
            'status' => PaymentStatus::Failed,
        ]);

        $this->actingAs($user, 'api')
            ->patchJson("/api/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertConflict();
    }

    public function test_user_cannot_access_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->create(['total' => '12.00']);

        $this->actingAs($intruder, 'api')
            ->getJson("/api/orders/{$order->id}")
            ->assertNotFound();

        $this->actingAs($intruder, 'api')
            ->putJson("/api/orders/{$order->id}", ['customer_name' => 'Hack'])
            ->assertNotFound();

        $this->actingAs($intruder, 'api')
            ->deleteJson("/api/orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_user_can_delete_order_without_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);

        $this->actingAs($user, 'api')
            ->deleteJson("/api/orders/{$order->id}")
            ->assertOk();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_delete_order_with_payments_returns_conflict(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);

        Payment::factory()->for($order)->for($user)->create([
            'amount' => '10.00',
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($user, 'api')
            ->deleteJson("/api/orders/{$order->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Order cannot be deleted because it has associated payments.');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_order_creation_rolls_back_when_total_exceeds_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'customer_name' => 'Acme Corp',
                'customer_email' => 'billing@acme.test',
                'items' => [
                    ['product_name' => 'Expensive', 'quantity' => 2, 'unit_price' => '99999999.99'],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_exact_bcmath_total_for_fractional_quantities(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/orders', [
            'customer_name' => 'Acme Corp',
            'customer_email' => 'billing@acme.test',
            'items' => [
                ['product_name' => 'Penny Item', 'quantity' => 3, 'unit_price' => '0.10'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.total', '0.30');
    }
}
