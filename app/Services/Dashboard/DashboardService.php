<?php

namespace App\Services\Dashboard;

use App\Http\Resources\Api\V1\NearbySavedPlaceResource;
use App\Http\Resources\Api\V1\SavedPlaceCollectionResource;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Models\SavedPlaceCollection;
use App\Models\User;
use App\Services\Home\HomeNotificationService;
use App\Services\Home\HomeSearchService;
use App\Services\SavedPlaces\SavedPlaceService;

class DashboardService
{
    public function __construct(
        protected SavedPlaceService $savedPlaceService,
        protected HomeSearchService $homeSearchService,
        protected HomeNotificationService $homeNotificationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildForUser(User $user, array $filters = []): array
    {
        $summary = $this->savedPlaceService->summaryForUser($user);
        $savedPlaces = $this->savedPlaceService
            ->paginateForUser($user, ['per_page' => 5, 'sort' => 'newest'])
            ->getCollection();

        $favoritePlaces = $this->savedPlaceService->favoritesForUser($user, 5);
        $pins = $this->savedPlaceService->pinsForUser($user, ['limit' => 500]);
        $nearbyPlaces = $this->homeSearchService->nearbyPlaces($user, $filters);
        $notificationSummary = $this->homeNotificationService->summaryForUser($user);
        $collections = SavedPlaceCollection::query()
            ->where('user_id', $user->id)
            ->withCount('savedPlaces')
            ->orderByDesc('saved_places_count')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $categoryBreakdown = $pins
            ->groupBy(fn ($savedPlace) => $savedPlace->category?->value ?? 'other')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->toArray();

        return [
            'summary' => [
                'saved_places_count' => $summary['saved_places_count'],
                'favorite_places_count' => $summary['favorite_places_count'],
                'mappable_places_count' => $pins->count(),
                'regions_count' => $summary['regions_count'],
            ],
            'empty_states' => [
                'has_saved_places' => $summary['saved_places_count'] > 0,
                'has_mappable_places' => $pins->isNotEmpty(),
                'has_favorites' => $summary['favorite_places_count'] > 0,
            ],
            'quick_actions' => [
                ['key' => 'add_pin', 'label' => 'Add Pin'],
                ['key' => 'import', 'label' => 'Import'],
            ],
            'recent_places' => $savedPlaces,
            'favorite_places' => $favoritePlaces,
            'collections' => SavedPlaceCollectionResource::collection($collections)->resolve(),
            'map_summary' => [
                'total_pins' => $pins->count(),
                'category_breakdown' => $categoryBreakdown,
            ],
            'filters' => [
                'categories' => collect($categoryBreakdown)->map(
                    fn (int $count, string $category): array => [
                        'key' => $category,
                        'label' => str($category)->replace('_', ' ')->title()->toString(),
                        'count' => $count,
                    ],
                )->values()->all(),
                'collections' => SavedPlaceCollectionResource::collection($collections)->resolve(),
                'favorites_count' => $summary['favorite_places_count'],
                'radius_presets' => [1000, 2500, 5000],
            ],
            'notifications' => [
                'unread_count' => $notificationSummary['unread_count'],
                'latest' => UserNotificationResource::collection($notificationSummary['latest'])->resolve(),
            ],
            'smart_banner' => $nearbyPlaces->isNotEmpty() ? [
                'type' => 'nearby_saved_places',
                'title' => $nearbyPlaces->count().' saved places nearby',
                'subtitle' => 'Review nearby places and add them into your plans faster.',
                'nearby_places' => NearbySavedPlaceResource::collection($nearbyPlaces->take(2))->resolve(),
                'primary_action' => [
                    'label' => 'View Nearby Places',
                    'type' => 'open_nearby_search',
                ],
            ] : null,
            'search' => [
                'recent_count' => $this->homeSearchService->recentSearches($user)->count(),
                'trending_now' => $this->homeSearchService->trendingNow(),
            ],
        ];
    }
}
