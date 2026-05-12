<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AdminRole: string
{
    use HasValues;

    case SuperAdmin = 'super_admin';
}
