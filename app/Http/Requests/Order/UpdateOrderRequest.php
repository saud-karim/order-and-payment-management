<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use App\Http\Requests\Order\Concerns\ValidatesOrderItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    use ValidatesOrderItems;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $moneyPattern = '/^\d+(\.\d{1,2})?$/';

        return [
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'string', 'email', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(OrderStatus::values())],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:'.config('orders.money.max_quantity')],
            'items.*.unit_price' => ['required_with:items', 'string', 'regex:'.$moneyPattern, 'max:20'],
        ];
    }
}
