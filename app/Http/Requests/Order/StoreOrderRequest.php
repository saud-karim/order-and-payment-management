<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\Order\Concerns\ValidatesOrderItems;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
        return array_merge([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
        ], $this->itemRules());
    }

    /**
     * @return array{customer_name: string, customer_email: string, items: list<array{product_name: string, quantity: int, unit_price: string}>}
     */
    public function orderPayload(): array
    {
        $validated = $this->validated();

        return [
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'items' => $validated['items'],
        ];
    }
}
