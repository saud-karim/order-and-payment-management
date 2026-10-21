<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in(OrderStatus::values())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('orders.pagination.max_per_page')],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? config('orders.pagination.default_per_page'));
    }

    public function statusFilter(): ?string
    {
        return $this->validated('status');
    }
}
