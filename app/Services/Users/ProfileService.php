<?php

namespace App\Services\Users;

use App\Models\User;

class ProfileService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProfile(User $user, array $payload): User
    {
        $email = strtolower((string) $payload['email']);
        $emailChanged = $email !== $user->email;

        $user->fill([
            'name' => $payload['name'],
            'email' => $email,
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user->fresh()->load('preference');
    }

    public function updateOnboarding(User $user, bool $completed): User
    {
        $user->forceFill([
            'onboarding_completed_at' => $completed ? now() : null,
        ])->save();

        return $user->fresh()->load('preference');
    }

    public function touchLastSeen(User $user): void
    {
        $user->forceFill(['last_seen_at' => now()])->save();
    }
}
