<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class IndexPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('orders.pagination.max_per_page')],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? config('orders.pagination.default_per_page'));
    }

    public function orderFilter(): ?int
    {
        $orderId = $this->validated('order_id');

        return $orderId !== null ? (int) $orderId : null;
    }
}
