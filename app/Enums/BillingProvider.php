<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BillingProvider: string
{
    use HasValues;

    case RevenueCat = 'revenuecat';
}
