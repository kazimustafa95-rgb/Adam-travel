<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum FriendRequestStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Canceled = 'canceled';
}
