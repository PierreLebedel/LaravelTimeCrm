<?php

namespace App\Enums;

enum BillingMode: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
}
