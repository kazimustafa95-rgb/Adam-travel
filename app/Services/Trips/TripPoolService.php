<?php

namespace App\Services\Trips;

use App\Enums\TripPlaceSource;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripPlace;
use App\Models\TripPlaceHeart;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripPoolService
{
    /**
     * @return Collection<int, TripPlace>
     */
    public function list(Trip $trip): Collection
    {
        return TripPlace::query()
            ->where('trip_id', $trip->id)
            ->with(['savedPlace.location', 'addedBy', 'hearts'])
            ->withCount('hearts')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Trip $trip, User $actor, array $payload): TripPlace
    {
        $savedPlace = SavedPlace::query()
            ->where('id', $payload['saved_place_id'])
            ->where('user_id', $actor->id)
            ->first();

        if (! $savedPlace) {
            throw ValidationException::withMessages([
                'saved_place_id' => ['You may only add your own saved places to a shared trip pool.'],
            ]);
        }

        $exists = TripPlace::query()
            ->where('trip_id', $trip->id)
            ->where('saved_place_id', $savedPlace->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'saved_place_id' => ['This saved place is already in the trip pool.'],
            ]);
        }

        return $this->storeTripPlace(
            $trip,
            $savedPlace,
            $actor,
            source: TripPlaceSource::SavedPlace,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(TripPlace $tripPlace, array $payload): TripPlace
    {
        $tripPlace->fill([
            'trip_category' => array_key_exists('trip_category', $payload) ? $payload['trip_category'] : $tripPlace->trip_category,
            'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $tripPlace->notes,
            'version' => $tripPlace->version + 1,
        ]);
        $tripPlace->save();

        return $tripPlace->fresh(['savedPlace.location', 'addedBy', 'hearts'])->loadCount('hearts');
    }

    public function delete(TripPlace $tripPlace): void
    {
        $tripPlace->delete();
    }

    public function heart(TripPlace $tripPlace, User $user): TripPlace
    {
        TripPlaceHeart::query()->firstOrCreate([
            'trip_place_id' => $tripPlace->id,
            'user_id' => $user->id,
        ]);

        return $tripPlace->fresh(['savedPlace.location', 'addedBy', 'hearts'])->loadCount('hearts');
    }

    public function unheart(TripPlace $tripPlace, User $user): TripPlace
    {
        TripPlaceHeart::query()
            ->where('trip_place_id', $tripPlace->id)
            ->where('user_id', $user->id)
            ->delete();

        return $tripPlace->fresh(['savedPlace.location', 'addedBy', 'hearts'])->loadCount('hearts');
    }

    public function removeMember(Trip $trip, int $memberId): void
    {
        DB::transaction(function () use ($trip, $memberId): void {
            $member = $trip->members()->whereKey($memberId)->first();

            if (! $member) {
                throw ValidationException::withMessages([
                    'member' => ['The trip member was not found.'],
                ]);
            }

            if ($member->role->value === \App\Enums\TripMemberRole::Owner->value) {
                throw ValidationException::withMessages([
                    'member' => ['The trip owner cannot be removed.'],
                ]);
            }

            $member->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFromSuggestedSavedPlace(Trip $trip, SavedPlace $savedPlace, User $actor, array $payload = []): TripPlace
    {
        return $this->storeTripPlace(
            $trip,
            $savedPlace,
            $actor,
            source: $payload['source'] ?? TripPlaceSource::AiSuggestion,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeTripPlace(Trip $trip, SavedPlace $savedPlace, User $actor, TripPlaceSource|string $source, array $payload): TripPlace
    {
        $exists = TripPlace::query()
            ->where('trip_id', $trip->id)
            ->where('saved_place_id', $savedPlace->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'saved_place_id' => ['This saved place is already in the trip pool.'],
            ]);
        }

        $tripPlace = TripPlace::query()->create([
            'trip_id' => $trip->id,
            'saved_place_id' => $savedPlace->id,
            'added_by_user_id' => $actor->id,
            'source' => $source,
            'trip_category' => $payload['trip_category'] ?? $savedPlace->category?->value,
            'notes' => $payload['notes'] ?? null,
            'is_removed' => false,
            'version' => 1,
        ]);

        return $tripPlace->load(['savedPlace.location', 'addedBy', 'hearts'])->loadCount('hearts');
    }
}
