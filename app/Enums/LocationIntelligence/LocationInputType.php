<?php

namespace App\Enums\LocationIntelligence;

enum LocationInputType: string
{
    case Text      = 'text';
    case ImageUrl  = 'image';
    case VideoUrl  = 'video';
    case SocialUrl = 'social';
}
