<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripAiRunType: string
{
    use HasValues;

    case Itinerary = 'itinerary';
    case Suggestions = 'suggestions';
    case Balance = 'balance';
}
