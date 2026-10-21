<?php

namespace App\Payments\Enums;

enum PaymentGatewayResultStatus: string
{
    case Successful = 'successful';
    case Declined = 'declined';
}
