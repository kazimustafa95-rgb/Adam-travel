<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SavedPlaceCategory: string
{
    use HasValues;

    case Hotel = 'hotel';
    case Restaurant = 'restaurant';
    case Activity = 'activity';
    case Viewpoint = 'viewpoint';
    case Transport = 'transport';
    case Shopping = 'shopping';
    case Other = 'other';
}
