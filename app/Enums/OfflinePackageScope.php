<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum OfflinePackageScope: string
{
    use HasValues;

    case Trip = 'trip';
    case Region = 'region';
}
