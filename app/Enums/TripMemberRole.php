<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripMemberRole: string
{
    use HasValues;

    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
