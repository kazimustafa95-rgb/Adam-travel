<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripAiRunStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
