<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripInviteStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
