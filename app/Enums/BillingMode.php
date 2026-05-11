<?php

namespace App\Enums;

enum BillingMode: string
{
    case Daily = 'daily';
    case Hourly = 'hourly';
}
