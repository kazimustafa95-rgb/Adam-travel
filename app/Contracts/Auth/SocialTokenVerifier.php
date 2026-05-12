<?php

namespace App\Contracts\Auth;

use App\Enums\SocialAuthProvider;
use App\Services\Auth\SocialIdentity;

interface SocialTokenVerifier
{
    public function verify(SocialAuthProvider $provider, string $firebaseIdToken): SocialIdentity;
}
