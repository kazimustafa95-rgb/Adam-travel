<?php

namespace App\Policies;

use App\Models\TripPlace;
use App\Models\User;

class TripPlacePolicy
{
    public function view(User $user, TripPlace $tripPlace): bool
    {
        return (new TripPolicy())->view($user, $tripPlace->trip);
    }

    public function update(User $user, TripPlace $tripPlace): bool
    {
        return (new TripPolicy())->contribute($user, $tripPlace->trip);
    }

    public function delete(User $user, TripPlace $tripPlace): bool
    {
        return (new TripPolicy())->contribute($user, $tripPlace->trip);
    }

    public function heart(User $user, TripPlace $tripPlace): bool
    {
        return (new TripPolicy())->heart($user, $tripPlace->trip);
    }
}
