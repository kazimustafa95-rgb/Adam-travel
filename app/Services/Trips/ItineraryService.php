<?php

namespace App\Services\Trips;

use App\Enums\ItineraryItemSource;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Models\TripPlace;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ItineraryService
{
    /**
     * @return array{days: Collection<int, ItineraryDay>, meta: array<string, int>}
     */
    public function list(Trip $trip): array
    {
        $days = $this->queryDays($trip)->get();

        return [
            'days' => $days,
            'meta' => [
                'count' => $days->count(),
                'items_count' => $days->sum(fn (ItineraryDay $day): int => $day->items->count()),
                'unscheduled_pool_count' => $trip->pool()->whereDoesntHave('itineraryItem')->count(),
                'trip_version' => $trip->version,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDay(Trip $trip, array $payload): ItineraryDay
    {
        return DB::transaction(function () use ($trip, $payload): ItineraryDay {
            $dayNumber = (int) $payload['day_number'];

            if ($trip->itineraryDays()->where('day_number', $dayNumber)->exists()) {
                throw ValidationException::withMessages([
                    'day_number' => ['An itinerary day with this day number already exists.'],
                ]);
            }

            $tripDate = $this->resolveTripDate($trip, $dayNumber, $payload['trip_date'] ?? null);

            $day = ItineraryDay::query()->create([
                'trip_id' => $trip->id,
                'day_number' => $dayNumber,
                'trip_date' => $tripDate?->toDateString(),
                'title' => $payload['title'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'version' => 1,
            ]);

            $this->bumpTripVersion($trip);

            return $day->fresh(['items.tripPlace.savedPlace.location', 'items.scheduledBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createItem(Trip $trip, User $actor, array $payload): ItineraryItem
    {
        return DB::transaction(function () use ($trip, $actor, $payload): ItineraryItem {
            $day = $this->resolveDay($trip, (int) $payload['itinerary_day_id']);
            $tripPlace = $this->resolveTripPlace($trip, (int) $payload['trip_place_id']);

            if (ItineraryItem::query()->where('trip_place_id', $tripPlace->id)->whereNull('deleted_at')->exists()) {
                throw ValidationException::withMessages([
                    'trip_place_id' => ['This trip place is already scheduled in the itinerary.'],
                ]);
            }

            $item = ItineraryItem::query()->create([
                'itinerary_day_id' => $day->id,
                'trip_place_id' => $tripPlace->id,
                'scheduled_by_user_id' => $actor->id,
                'source' => $payload['source'] ?? ItineraryItemSource::Manual,
                'starts_at' => $payload['starts_at'] ?? null,
                'ends_at' => $payload['ends_at'] ?? null,
                'sort_order' => $payload['sort_order'] ?? ((int) $day->items()->max('sort_order') + 1),
                'notes' => $payload['notes'] ?? null,
                'version' => 1,
            ]);

            $this->touchDays([$day->id]);
            $this->bumpTripVersion($trip);

            return $item->fresh(['tripPlace.savedPlace.location', 'tripPlace.savedPlace', 'tripPlace.addedBy', 'tripPlace.hearts', 'scheduledBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(Trip $trip, ItineraryItem $itineraryItem, array $payload): ItineraryItem
    {
        return DB::transaction(function () use ($trip, $itineraryItem, $payload): ItineraryItem {
            $originalDayId = $itineraryItem->itinerary_day_id;
            $targetDay = array_key_exists('itinerary_day_id', $payload)
                ? $this->resolveDay($trip, (int) $payload['itinerary_day_id'])
                : $itineraryItem->day;

            $itineraryItem->fill([
                'itinerary_day_id' => $targetDay->id,
                'starts_at' => array_key_exists('starts_at', $payload) ? $payload['starts_at'] : $itineraryItem->starts_at,
                'ends_at' => array_key_exists('ends_at', $payload) ? $payload['ends_at'] : $itineraryItem->ends_at,
                'sort_order' => $payload['sort_order'] ?? $itineraryItem->sort_order,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $itineraryItem->notes,
                'version' => $itineraryItem->version + 1,
            ]);
            $itineraryItem->save();

            $this->touchDays([$originalDayId, $targetDay->id]);
            $this->bumpTripVersion($trip);

            return $itineraryItem->fresh(['tripPlace.savedPlace.location', 'tripPlace.savedPlace', 'tripPlace.addedBy', 'tripPlace.hearts', 'scheduledBy']);
        });
    }

    public function deleteItem(Trip $trip, ItineraryItem $itineraryItem): void
    {
        DB::transaction(function () use ($trip, $itineraryItem): void {
            $dayId = $itineraryItem->itinerary_day_id;

            $itineraryItem->delete();

            $this->touchDays([$dayId]);
            $this->bumpTripVersion($trip);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{days: Collection<int, ItineraryDay>, meta: array<string, int>}
     */
    public function reorder(Trip $trip, array $payload): array
    {
        if ((int) $payload['version'] !== $trip->version) {
            throw new ConflictHttpException('This itinerary is out of date. Refresh and try again.');
        }

        return DB::transaction(function () use ($trip, $payload): array {
            $touchedDayIds = [];

            foreach ($payload['days'] as $dayPayload) {
                $day = $this->resolveDay($trip, (int) $dayPayload['day_id']);
                $touchedDayIds[] = $day->id;

                foreach ($dayPayload['items'] as $index => $itemPayload) {
                    $item = $this->resolveItem($trip, (int) $itemPayload['item_id']);
                    $touchedDayIds[] = $item->itinerary_day_id;

                    $item->fill([
                        'itinerary_day_id' => $day->id,
                        'sort_order' => $itemPayload['sort_order'] ?? ($index + 1),
                        'starts_at' => $itemPayload['starts_at'] ?? null,
                        'ends_at' => $itemPayload['ends_at'] ?? null,
                        'version' => $item->version + 1,
                    ]);
                    $item->save();
                }
            }

            $this->touchDays($touchedDayIds);
            $this->bumpTripVersion($trip);

            return $this->list($trip->fresh());
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ItineraryDay>
     */
    protected function queryDays(Trip $trip)
    {
        return $trip->itineraryDays()->with([
            'items.tripPlace.savedPlace.location',
            'items.tripPlace.savedPlace',
            'items.tripPlace.addedBy',
            'items.tripPlace.hearts',
            'items.scheduledBy',
        ]);
    }

    protected function resolveDay(Trip $trip, int $dayId): ItineraryDay
    {
        $day = $trip->itineraryDays()->whereKey($dayId)->first();

        if (! $day) {
            throw ValidationException::withMessages([
                'itinerary_day_id' => ['The selected itinerary day does not belong to this trip.'],
            ]);
        }

        return $day;
    }

    protected function resolveTripPlace(Trip $trip, int $tripPlaceId): TripPlace
    {
        $tripPlace = $trip->pool()->whereKey($tripPlaceId)->first();

        if (! $tripPlace) {
            throw ValidationException::withMessages([
                'trip_place_id' => ['The selected trip place does not belong to this trip.'],
            ]);
        }

        return $tripPlace;
    }

    protected function resolveItem(Trip $trip, int $itemId): ItineraryItem
    {
        $item = ItineraryItem::query()
            ->whereKey($itemId)
            ->whereHas('day', fn ($query) => $query->where('trip_id', $trip->id)->whereNull('deleted_at'))
            ->whereNull('deleted_at')
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => ['The selected itinerary item does not belong to this trip.'],
            ]);
        }

        return $item;
    }

    protected function resolveTripDate(Trip $trip, int $dayNumber, string|null $tripDate): Carbon|null
    {
        if (! $trip->start_date || ! $trip->end_date) {
            return $tripDate !== null ? Carbon::parse($tripDate)->startOfDay() : null;
        }

        $totalTripDays = $trip->start_date->diffInDays($trip->end_date) + 1;

        if ($dayNumber > $totalTripDays) {
            throw ValidationException::withMessages([
                'day_number' => ['The selected day number falls outside the trip date range.'],
            ]);
        }

        $expectedDate = $trip->start_date->copy()->addDays($dayNumber - 1)->startOfDay();

        if ($tripDate === null) {
            return $expectedDate;
        }

        $parsedDate = Carbon::parse($tripDate)->startOfDay();

        if ($parsedDate->lt($trip->start_date->copy()->startOfDay()) || $parsedDate->gt($trip->end_date->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'trip_date' => ['The itinerary day must fall within the trip date range.'],
            ]);
        }

        if (! $parsedDate->isSameDay($expectedDate)) {
            throw ValidationException::withMessages([
                'trip_date' => ['The itinerary day date must match the selected day number.'],
            ]);
        }

        return $parsedDate;
    }

    /**
     * @param  array<int, int>  $dayIds
     */
    protected function touchDays(array $dayIds): void
    {
        $uniqueDayIds = array_values(array_unique($dayIds));

        if ($uniqueDayIds === []) {
            return;
        }

        ItineraryDay::query()->whereIn('id', $uniqueDayIds)->increment('version');
    }

    protected function bumpTripVersion(Trip $trip): void
    {
        $trip->increment('version');
    }
}
