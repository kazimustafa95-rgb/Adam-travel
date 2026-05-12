<?php

namespace App\Services\Trips;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TimelineService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);

        $paginator = $this->queryForUser($user)
            ->with(['owner', 'pool.savedPlace.location'])
            ->withCount(['members', 'pool'])
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (Trip $trip) => $this->decorate($trip));

        return $paginator;
    }

    public function timelineCountForUser(User $user): int
    {
        return $this->queryForUser($user)->count();
    }

    public function detailForUser(Trip $trip, User $user): Trip
    {
        $eligibleTrip = $this->queryForUser($user)
            ->whereKey($trip->getKey())
            ->first();

        if (! $eligibleTrip) {
            throw ValidationException::withMessages([
                'trip' => ['This trip is not available in your travel memory timeline.'],
            ]);
        }

        return $this->decorate(
            $trip->load([
                'owner',
                'members.user',
                'itineraryDays.items.tripPlace.savedPlace.location',
                'itineraryDays.items.scheduledBy',
                'itineraryDays.items.tripPlace.addedBy',
                'itineraryDays.items.tripPlace.savedPlace.import',
            ])->loadCount([
                'members',
                'pool',
            ]),
        );
    }

    protected function queryForUser(User $user): Builder
    {
        return Trip::query()
            ->whereHas('members', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where(function (Builder $query): void {
                $query->whereIn('status', [TripStatus::Completed->value, TripStatus::Archived->value])
                    ->orWhereDate('end_date', '<', now()->toDateString());
            });
    }

    protected function decorate(Trip $trip): Trip
    {
        $primaryCountryCode = $trip->relationLoaded('itineraryDays')
            ? $trip->itineraryDays
                ->flatMap(fn ($day) => $day->items)
                ->map(fn ($item) => $item->tripPlace?->savedPlace?->location?->country_code)
                ->filter()
                ->first()
            : null;

        if ($primaryCountryCode === null && $trip->relationLoaded('pool')) {
            $primaryCountryCode = $trip->pool
                ->map(fn ($tripPlace) => $tripPlace->savedPlace?->location?->country_code)
                ->filter()
                ->first();
        }

        $trip->setAttribute('primary_country_code', $primaryCountryCode);
        $trip->setAttribute('date_range_label', $trip->start_date && $trip->end_date
            ? $trip->start_date->format('M j').'–'.$trip->end_date->format('j, Y')
            : null);

        return $trip;
    }
}
