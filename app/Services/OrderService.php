<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidMoneyException;
use App\Exceptions\OrderConflictException;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForUser(User $user, ?string $status, int $perPage): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findForUserOrFail(User $user, int $orderId): Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->whereKey($orderId)
            ->firstOrFail();
    }

    /**
     * @param  array{customer_name: string, customer_email: string, items: list<array{product_name: string, quantity: int, unit_price: string}>}  $data
     */
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'status' => OrderStatus::Pending,
                'total' => '0.00',
                'currency' => config('orders.default_currency'),
            ]);

            $total = $this->syncItems($order, $data['items']);

            $order->update(['total' => $total]);

            return $order->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $order->refresh();

            $hasItemChanges = array_key_exists('items', $data);
            $hasCustomerChanges = array_key_exists('customer_name', $data) || array_key_exists('customer_email', $data);
            $hasStatusChange = array_key_exists('status', $data);

            if ($hasItemChanges || $hasCustomerChanges) {
                $this->assertOrderIsPending($order);
            }

            if ($hasCustomerChanges) {
                $order->fill([
                    'customer_name' => $data['customer_name'] ?? $order->customer_name,
                    'customer_email' => $data['customer_email'] ?? $order->customer_email,
                ]);
            }

            if ($hasStatusChange) {
                $this->transitionStatus($order, OrderStatus::from($data['status']));
            }

            if ($hasItemChanges) {
                $order->items()->delete();
                $total = $this->syncItems($order, $data['items']);
                $order->total = $total;
            }

            $order->save();

            return $order->fresh(['items']);
        });
    }

    public function delete(Order $order): void
    {
        if ($order->hasPaymentAttempts()) {
            throw OrderConflictException::hasPayments();
        }

        $order->delete();
    }

    /**
     * @param  list<array{product_name: string, quantity: int, unit_price: string}>  $items
     */
    private function syncItems(Order $order, array $items): string
    {
        $lineTotals = [];

        foreach ($items as $item) {
            $unitPrice = (string) $item['unit_price'];
            $quantity = (int) $item['quantity'];

            Money::assertWithinLimit($unitPrice, config('orders.money.max_unit_price'), 'unit price');

            if ($quantity > (int) config('orders.money.max_quantity')) {
                throw InvalidMoneyException::exceedsLimit('quantity', (string) config('orders.money.max_quantity'));
            }

            $lineTotal = Money::multiply($unitPrice, $quantity);
            Money::assertWithinLimit($lineTotal, config('orders.money.max_line_total'), 'line total');

            $order->items()->create([
                'product_name' => $item['product_name'],
                'quantity' => $quantity,
                'unit_price' => Money::normalize($unitPrice),
                'line_total' => $lineTotal,
            ]);

            $lineTotals[] = $lineTotal;
        }

        $total = Money::add(...$lineTotals);
        Money::assertWithinLimit($total, config('orders.money.max_order_total'), 'order total');

        return $total;
    }

    private function transitionStatus(Order $order, OrderStatus $targetStatus): void
    {
        $currentStatus = $order->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if ($currentStatus === OrderStatus::Cancelled) {
            throw OrderConflictException::invalidStatusTransition($currentStatus->value, $targetStatus->value);
        }

        if ($currentStatus === OrderStatus::Confirmed && $targetStatus === OrderStatus::Pending) {
            throw OrderConflictException::invalidStatusTransition($currentStatus->value, $targetStatus->value);
        }

        $allowed = match ($currentStatus) {
            OrderStatus::Pending => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Confirmed => [OrderStatus::Cancelled],
            OrderStatus::Cancelled => [],
        };

        if (! in_array($targetStatus, $allowed, true)) {
            throw OrderConflictException::invalidStatusTransition($currentStatus->value, $targetStatus->value);
        }

        if ($targetStatus === OrderStatus::Cancelled && $order->hasPaymentAttempts()) {
            throw OrderConflictException::confirmedOrderHasPayments();
        }

        $order->status = $targetStatus;
    }

    private function assertOrderIsPending(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw OrderConflictException::orderNotEditable($order->status->value);
        }
    }
}
