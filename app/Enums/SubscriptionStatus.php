<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SubscriptionStatus: string
{
    use HasValues;

    case Trialing = 'trialing';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Canceled = 'canceled';
}
