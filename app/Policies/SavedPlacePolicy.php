<?php

namespace App\Policies;

use App\Models\SavedPlace;
use App\Models\User;

class SavedPlacePolicy
{
    public function view(User $user, SavedPlace $savedPlace): bool
    {
        return $savedPlace->user_id === $user->id;
    }

    public function update(User $user, SavedPlace $savedPlace): bool
    {
        return $savedPlace->user_id === $user->id;
    }

    public function delete(User $user, SavedPlace $savedPlace): bool
    {
        return $savedPlace->user_id === $user->id;
    }
}
