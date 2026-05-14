<?php

namespace App\Services\Home;

use App\Http\Resources\Api\V1\NearbySavedPlaceResource;
use App\Http\Resources\Api\V1\RecentSearchResource;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Models\AppSetting;
use App\Models\RecentSearch;
use App\Models\SavedPlace;
use App\Models\User;
use App\Services\Locations\LocationService;
use App\Services\SavedPlaces\SavedPlaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HomeSearchService
{
    public function __construct(
        protected SavedPlaceService $savedPlaceService,
        protected LocationService $locationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function payloadForUser(User $user, array $filters): array
    {
        $query = trim((string) ($filters['q'] ?? ''));
        $recentSearches = $query === '' ? $this->recentSearches($user) : collect();
        $nearbyPlaces = $this->nearbyPlaces($user, $filters);
        $results = $query !== ''
            ? $this->savedPlaceService->searchForUser($user, [
                'q' => $query,
                'limit' => $filters['limit'] ?? 12,
            ])
            : collect();

        return [
            'query' => $query,
            'recent_searches' => RecentSearchResource::collection($recentSearches)->resolve(),
            'trending_now' => $this->trendingNow(),
            'nearby_places' => NearbySavedPlaceResource::collection($nearbyPlaces)->resolve(),
            'results' => SavedPlaceResource::collection($results)->resolve(),
            'empty_state' => [
                'has_recent_searches' => $recentSearches->isNotEmpty(),
                'has_results' => $results->isNotEmpty(),
                'has_nearby_places' => $nearbyPlaces->isNotEmpty(),
            ],
        ];
    }

    public function storeRecentSearch(User $user, string $query, ?int $resultCount = null): RecentSearch
    {
        $normalizedQuery = trim($query);

        if ($normalizedQuery === '') {
            throw ValidationException::withMessages([
                'q' => ['The search query cannot be empty.'],
            ]);
        }

        $search = RecentSearch::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(query) = ?', [mb_strtolower($normalizedQuery)])
            ->first();

        if (! $search) {
            $search = new RecentSearch([
                'user_id' => $user->id,
                'query' => $normalizedQuery,
            ]);
        }

        $search->query = $normalizedQuery;
        $search->result_count = $resultCount;
        $search->used_at = now();
        $search->save();

        $keepIds = RecentSearch::query()
            ->where('user_id', $user->id)
            ->orderByDesc('used_at')
            ->limit(8)
            ->pluck('id');

        RecentSearch::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();

        return $search->fresh();
    }

    public function clearRecentSearches(User $user): void
    {
        RecentSearch::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * @return Collection<int, RecentSearch>
     */
    public function recentSearches(User $user, int $limit = 5): Collection
    {
        return RecentSearch::query()
            ->where('user_id', $user->id)
            ->orderByDesc('used_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{saved_place: SavedPlace, distance_meters: int}>
     */
    public function nearbyPlaces(User $user, array $filters): Collection
    {
        if (! isset($filters['latitude'], $filters['longitude'])) {
            return collect();
        }

        $latitude = (float) $filters['latitude'];
        $longitude = (float) $filters['longitude'];
        $radiusMeters = (int) ($filters['radius_meters'] ?? 3000);
        $limit = (int) ($filters['limit'] ?? 6);

        return SavedPlace::query()
            ->with(['location', 'savedPlaceCollection'])
            ->where('user_id', $user->id)
            ->whereHas('location', function (Builder $query): void {
                $query->where('is_moderated_hidden', false)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude');
            })
            ->get()
            ->map(function (SavedPlace $savedPlace) use ($latitude, $longitude): array|null {
                if ($savedPlace->location?->latitude === null || $savedPlace->location?->longitude === null) {
                    return null;
                }

                return [
                    'saved_place' => $savedPlace,
                    'distance_meters' => $this->locationService->distanceInMeters(
                        $latitude,
                        $longitude,
                        (float) $savedPlace->location->latitude,
                        (float) $savedPlace->location->longitude,
                    ),
                ];
            })
            ->filter()
            ->filter(fn (array $item) => $item['distance_meters'] <= $radiusMeters)
            ->sortBy('distance_meters')
            ->values()
            ->take($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trendingNow(): array
    {
        $setting = AppSetting::query()->where('key', 'home.trending_searches')->first();
        $items = data_get($setting?->value, 'items');

        if (is_array($items) && $items !== []) {
            return array_values($items);
        }

        return [
            [
                'title' => 'Hidden Gems',
                'subtitle' => 'Quiet spots locals love',
                'theme' => 'teal',
            ],
            [
                'title' => 'Sunset Spots',
                'subtitle' => 'Golden hour viewpoints',
                'theme' => 'orange',
            ],
        ];
    }
}
