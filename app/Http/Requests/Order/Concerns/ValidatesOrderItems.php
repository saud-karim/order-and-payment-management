<?php

namespace App\Http\Requests\Order\Concerns;

trait ValidatesOrderItems
{
    /**
     * @return array<string, mixed>
     */
    protected function itemRules(): array
    {
        $moneyPattern = '/^\d+(\.\d{1,2})?$/';

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('orders.money.max_quantity')],
            'items.*.unit_price' => [
                'required',
                'string',
                'regex:'.$moneyPattern,
                'max:20',
            ],
        ];
    }
}
