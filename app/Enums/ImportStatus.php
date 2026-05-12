<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ImportStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Processing = 'processing';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case ManualReview = 'manual_review';
    case Completed = 'completed';
    case Failed = 'failed';
    case Moderated = 'moderated';
}
