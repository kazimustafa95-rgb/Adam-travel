<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripPlaceSource: string
{
    use HasValues;

    case Manual = 'manual';
    case SavedPlace = 'saved_place';
    case AiSuggestion = 'ai_suggestion';
}
