<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripSuggestionStatus: string
{
    use HasValues;

    case Suggested = 'suggested';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';
}
