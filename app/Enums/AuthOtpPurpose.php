<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AuthOtpPurpose: string
{
    use HasValues;

    case PasswordReset = 'password_reset';
}
