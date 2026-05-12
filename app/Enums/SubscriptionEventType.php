<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SubscriptionEventType: string
{
    use HasValues;

    case RestoreRequested = 'restore_requested';
    case InitialPurchase = 'initial_purchase';
    case Renewal = 'renewal';
    case Cancellation = 'cancellation';
    case Expiration = 'expiration';
    case BillingIssue = 'billing_issue';
    case Uncancellation = 'uncancellation';
    case ProductChange = 'product_change';
}
