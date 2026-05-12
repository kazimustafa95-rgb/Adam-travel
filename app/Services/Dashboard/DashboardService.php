<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\SavedPlaces\SavedPlaceService;

class DashboardService
{
    public function __construct(protected SavedPlaceService $savedPlaceService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForUser(User $user): array
    {
        $summary = $this->savedPlaceService->summaryForUser($user);
        $savedPlaces = $this->savedPlaceService
            ->paginateForUser($user, ['per_page' => 5, 'sort' => 'newest'])
            ->getCollection();

        $favoritePlaces = $this->savedPlaceService->favoritesForUser($user, 5);

        $pins = $this->savedPlaceService->pinsForUser($user, ['limit' => 500]);

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
            'recent_places' => $savedPlaces,
            'favorite_places' => $favoritePlaces,
            'map_summary' => [
                'total_pins' => $pins->count(),
                'category_breakdown' => $categoryBreakdown,
            ],
        ];
    }
}
