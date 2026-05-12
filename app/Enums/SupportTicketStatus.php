<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SupportTicketStatus: string
{
    use HasValues;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
