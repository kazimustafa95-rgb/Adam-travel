<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SocialAuthProvider: string
{
    use HasValues;

    case Google = 'google';
    case Apple = 'apple';
}
