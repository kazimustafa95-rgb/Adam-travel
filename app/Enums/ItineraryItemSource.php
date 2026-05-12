<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ItineraryItemSource: string
{
    use HasValues;

    case Manual = 'manual';
    case AiGenerated = 'ai_generated';
}
