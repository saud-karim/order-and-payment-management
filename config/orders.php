<?php

return [

    'default_currency' => env('ORDER_DEFAULT_CURRENCY', 'USD'),

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    'money' => [
        'max_unit_price' => '99999999.99',
        'max_line_total' => '99999999.99',
        'max_order_total' => '9999999999.99',
        'max_quantity' => 999_999,
    ],

];
