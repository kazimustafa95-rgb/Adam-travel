<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum OfflinePackageStatus: string
{
    use HasValues;

    case Queued = 'queued';
    case Ready = 'ready';
    case Expired = 'expired';
}
