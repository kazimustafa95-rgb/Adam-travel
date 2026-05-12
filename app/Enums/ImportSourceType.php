<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ImportSourceType: string
{
    use HasValues;

    case Url = 'url';
    case Text = 'text';
    case Manual = 'manual';
}
