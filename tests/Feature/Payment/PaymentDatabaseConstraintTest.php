<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_partial_unique_index_rejects_duplicate_successful_payments(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Partial unique index assertion uses SQLite in-memory test database.');
        }

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);

        Payment::factory()->for($order)->for($user)->create([
            'status' => PaymentStatus::Successful,
            'operation_reference' => 'op-success-1',
            'idempotency_key' => 'idem-1',
        ]);

        $this->expectException(QueryException::class);

        Payment::factory()->for($order)->for($user)->create([
            'status' => PaymentStatus::Successful,
            'operation_reference' => 'op-success-2',
            'idempotency_key' => 'idem-2',
        ]);
    }

    public function test_multiple_failed_payments_are_allowed_for_same_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);

        Payment::factory()->for($order)->for($user)->failed()->create([
            'operation_reference' => 'op-fail-1',
            'idempotency_key' => 'idem-f1',
        ]);

        Payment::factory()->for($order)->for($user)->failed()->create([
            'operation_reference' => 'op-fail-2',
            'idempotency_key' => 'idem-f2',
        ]);

        $this->assertDatabaseCount('payments', 2);
    }
}
