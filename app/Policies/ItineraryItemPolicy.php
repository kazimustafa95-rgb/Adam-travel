<?php

namespace App\Policies;

use App\Models\ItineraryItem;
use App\Models\User;

class ItineraryItemPolicy
{
    public function view(User $user, ItineraryItem $itineraryItem): bool
    {
        return (new TripPolicy())->view($user, $itineraryItem->day->trip);
    }

    public function update(User $user, ItineraryItem $itineraryItem): bool
    {
        return (new TripPolicy())->manageItinerary($user, $itineraryItem->day->trip);
    }

    public function delete(User $user, ItineraryItem $itineraryItem): bool
    {
        return (new TripPolicy())->manageItinerary($user, $itineraryItem->day->trip);
    }
}
