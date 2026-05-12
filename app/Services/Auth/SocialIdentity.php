<?php

namespace App\Services\Auth;

use App\Enums\SocialAuthProvider;

readonly class SocialIdentity
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public SocialAuthProvider $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $avatarUrl,
        public array $claims = [],
    ) {
    }
}
