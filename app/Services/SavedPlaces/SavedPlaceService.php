<?php

namespace App\Services\SavedPlaces;

use App\Models\SavedPlace;
use App\Models\SavedPlaceCollection;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Locations\LocationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavedPlaceService
{
    public function __construct(
        protected LocationService $locationService,
        protected SubscriptionService $subscriptionService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->applyFilters($this->queryForUser($user), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload): SavedPlace
    {
        $this->subscriptionService->assertCanCreateSavedPlace($user);

        return DB::transaction(function () use ($user, $payload): SavedPlace {
            $location = $this->locationService->resolveForSavedPlace($payload);

            $savedPlace = SavedPlace::query()->create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'import_id' => $payload['import_id'] ?? null,
                'title_override' => $payload['title_override'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'category' => $payload['category'],
                'region_label' => $this->resolveRegionLabel($user, $payload),
                'saved_place_collection_id' => $payload['saved_place_collection_id'] ?? null,
                'is_favorite' => (bool) ($payload['is_favorite'] ?? false),
                'visibility' => $payload['visibility'] ?? 'private',
                'version' => 1,
            ]);

            return $savedPlace->load(['location', 'savedPlaceCollection']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(SavedPlace $savedPlace, array $payload): SavedPlace
    {
        return DB::transaction(function () use ($savedPlace, $payload): SavedPlace {
            if (array_key_exists('location_id', $payload) || array_key_exists('location', $payload)) {
                $location = $this->locationService->resolveForSavedPlace($payload);
                $savedPlace->location()->associate($location);
            }

            $savedPlace->fill([
                'title_override' => array_key_exists('title_override', $payload) ? $payload['title_override'] : $savedPlace->title_override,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $savedPlace->notes,
                'category' => $payload['category'] ?? $savedPlace->category,
                'region_label' => $this->resolveRegionLabel($savedPlace->user, $payload, $savedPlace->region_label),
                'saved_place_collection_id' => array_key_exists('saved_place_collection_id', $payload)
                    ? $payload['saved_place_collection_id']
                    : $savedPlace->saved_place_collection_id,
                'is_favorite' => array_key_exists('is_favorite', $payload) ? (bool) $payload['is_favorite'] : $savedPlace->is_favorite,
                'visibility' => $payload['visibility'] ?? $savedPlace->visibility,
                'version' => $savedPlace->version + 1,
            ]);

            $savedPlace->save();

            return $savedPlace->fresh()->load(['location', 'savedPlaceCollection']);
        });
    }

    public function delete(SavedPlace $savedPlace): void
    {
        $savedPlace->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SavedPlace>
     */
    public function searchForUser(User $user, array $filters): Collection
    {
        $limit = (int) ($filters['limit'] ?? 10);

        return $this->queryForUser($user)
            ->where(function (Builder $query) use ($filters): void {
                $search = (string) $filters['q'];
                $query->where('title_override', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('location', function (Builder $locationQuery) use ($search): void {
                        $locationQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%')
                            ->orWhere('region', 'like', '%'.$search.'%');
                    });
            })
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, SavedPlace>
     */
    public function favoritesForUser(User $user, int $limit = 5): Collection
    {
        return $this->queryForUser($user)
            ->where('is_favorite', true)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function summaryForUser(User $user): array
    {
        $query = $this->queryForUser($user);

        return [
            'saved_places_count' => (clone $query)->count(),
            'favorite_places_count' => (clone $query)->where('is_favorite', true)->count(),
            'regions_count' => SavedPlaceCollection::query()->where('user_id', $user->id)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SavedPlace>
     */
    public function pinsForUser(User $user, array $filters): Collection
    {
        $query = $this->applyFilters($this->queryForUser($user), $filters)
            ->whereHas('location', function (Builder $locationQuery): void {
                $locationQuery->whereNotNull('latitude')->whereNotNull('longitude');
            });

        if (
            array_key_exists('north', $filters) &&
            array_key_exists('south', $filters) &&
            array_key_exists('east', $filters) &&
            array_key_exists('west', $filters) &&
            $filters['north'] !== null &&
            $filters['south'] !== null &&
            $filters['east'] !== null &&
            $filters['west'] !== null
        ) {
            $north = (float) $filters['north'];
            $south = (float) $filters['south'];
            $east = (float) $filters['east'];
            $west = (float) $filters['west'];

            $query->whereHas('location', function (Builder $locationQuery) use ($north, $south, $east, $west): void {
                $locationQuery->whereBetween('latitude', [$south, $north])
                    ->whereBetween('longitude', [$west, $east]);
            });
        }

        $limit = (int) ($filters['limit'] ?? 500);

        $pins = $query
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if (
            isset($filters['latitude'], $filters['longitude'], $filters['radius_meters']) &&
            $filters['latitude'] !== null &&
            $filters['longitude'] !== null &&
            $filters['radius_meters'] !== null
        ) {
            $latitude = (float) $filters['latitude'];
            $longitude = (float) $filters['longitude'];
            $radiusMeters = (int) $filters['radius_meters'];

            $pins = $pins
                ->filter(function (SavedPlace $savedPlace) use ($latitude, $longitude, $radiusMeters): bool {
                    if ($savedPlace->location?->latitude === null || $savedPlace->location?->longitude === null) {
                        return false;
                    }

                    return $this->locationService->distanceInMeters(
                        $latitude,
                        $longitude,
                        (float) $savedPlace->location->latitude,
                        (float) $savedPlace->location->longitude,
                    ) <= $radiusMeters;
                })
                ->values();
        }

        return $pins;
    }

    public function detailForUser(SavedPlace $savedPlace): SavedPlace
    {
        return $savedPlace->load([
            'location',
            'savedPlaceCollection',
            'import.candidates',
            'tripPlaces.trip.owner',
            'tripPlaces.trip.members',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['q'])) {
            $search = (string) $filters['q'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title_override', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('location', function (Builder $locationQuery) use ($search): void {
                        $locationQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%')
                            ->orWhere('region', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['region_label'])) {
            $query->where('region_label', $filters['region_label']);
        }

        if (! empty($filters['saved_place_collection_id'])) {
            $query->where('saved_place_collection_id', $filters['saved_place_collection_id']);
        }

        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (array_key_exists('is_favorite', $filters) && $filters['is_favorite'] !== null) {
            $query->where('is_favorite', filter_var($filters['is_favorite'], FILTER_VALIDATE_BOOL));
        }

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at'),
            'name' => $query->orderByRaw('COALESCE(title_override, "") asc')->orderBy('created_at'),
            'favorites' => $query->orderByDesc('is_favorite')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }

    protected function queryForUser(User $user): Builder
    {
        return SavedPlace::query()
            ->with(['location', 'savedPlaceCollection'])
            ->where('user_id', $user->id)
            ->whereHas('location', function (Builder $locationQuery): void {
                $locationQuery->where('is_moderated_hidden', false);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveRegionLabel(User $user, array $payload, ?string $fallback = null): ?string
    {
        if (array_key_exists('saved_place_collection_id', $payload)) {
            if ($payload['saved_place_collection_id'] === null) {
                return null;
            }

            $collectionName = SavedPlaceCollection::query()
                ->where('user_id', $user->id)
                ->whereKey($payload['saved_place_collection_id'])
                ->value('name');

            if ($collectionName === null) {
                throw ValidationException::withMessages([
                    'saved_place_collection_id' => ['The selected saved place collection is not available.'],
                ]);
            }

            return $collectionName;
        }

        if (array_key_exists('region_label', $payload)) {
            return $payload['region_label'];
        }

        return $fallback;
    }
}
