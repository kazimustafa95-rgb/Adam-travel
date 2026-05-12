<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';
}
