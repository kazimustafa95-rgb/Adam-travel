<?php

namespace App\Policies;

use App\Enums\TripMemberRole;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) !== null;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function manageInvites(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function manageMembers(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function contribute(User $user, Trip $trip): bool
    {
        return in_array($this->memberRole($user, $trip), [TripMemberRole::Owner, TripMemberRole::Editor], true);
    }

    public function heart(User $user, Trip $trip): bool
    {
        return in_array($this->memberRole($user, $trip), [TripMemberRole::Owner, TripMemberRole::Editor], true);
    }

    public function manageItinerary(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function viewAiItinerary(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    public function generateAiItinerary(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function applyAiItinerary(User $user, Trip $trip): bool
    {
        return $this->memberRole($user, $trip) === TripMemberRole::Owner;
    }

    public function viewSuggestions(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    public function generateSuggestions(User $user, Trip $trip): bool
    {
        return in_array($this->memberRole($user, $trip), [TripMemberRole::Owner, TripMemberRole::Editor], true);
    }

    public function manageSuggestions(User $user, Trip $trip): bool
    {
        return in_array($this->memberRole($user, $trip), [TripMemberRole::Owner, TripMemberRole::Editor], true);
    }

    public function viewBalance(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    public function memberRole(User $user, Trip $trip): TripMemberRole|null
    {
        $members = $trip->relationLoaded('members') ? $trip->members : $trip->members()->get();
        $member = $members->firstWhere('user_id', $user->id);

        return $member?->role;
    }
}
