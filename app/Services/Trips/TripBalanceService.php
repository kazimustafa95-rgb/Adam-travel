<?php

namespace App\Services\Trips;

use App\Enums\SavedPlaceCategory;
use App\Models\Trip;
use App\Models\TripPlace;

class TripBalanceService
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(Trip $trip): array
    {
        $pool = TripPlace::query()
            ->where('trip_id', $trip->id)
            ->with(['savedPlace.location', 'itineraryItem'])
            ->get();

        $counts = array_fill_keys(SavedPlaceCategory::values(), 0);

        foreach ($pool as $tripPlace) {
            $category = $tripPlace->trip_category ?? $tripPlace->savedPlace?->category?->value ?? SavedPlaceCategory::Other->value;
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        $dayCount = $trip->start_date && $trip->end_date
            ? max(1, $trip->start_date->diffInDays($trip->end_date) + 1)
            : 1;

        $targets = [
            SavedPlaceCategory::Hotel->value => $dayCount > 1 ? 1 : 0,
            SavedPlaceCategory::Restaurant->value => min(max($dayCount, 1), 3),
            SavedPlaceCategory::Activity->value => max($dayCount, 1),
            SavedPlaceCategory::Transport->value => 1,
        ];

        $categorySummaries = [];
        $gaps = [];

        foreach ($targets as $category => $targetCount) {
            $currentCount = (int) ($counts[$category] ?? 0);
            $isMet = $currentCount >= $targetCount;
            $summary = [
                'category' => $category,
                'current_count' => $currentCount,
                'target_count' => $targetCount,
                'is_balanced' => $isMet,
            ];

            $categorySummaries[] = $summary;

            if (! $isMet) {
                $gaps[] = [
                    'category' => $category,
                    'current_count' => $currentCount,
                    'target_count' => $targetCount,
                    'priority' => $targetCount - $currentCount,
                    'reason' => $this->gapReason($category, $dayCount),
                ];
            }
        }

        usort($gaps, fn (array $left, array $right): int => $right['priority'] <=> $left['priority']);

        $requiredCategories = count(array_filter($targets, fn (int $target): bool => $target > 0));
        $metCategories = count(array_filter($categorySummaries, fn (array $summary): bool => $summary['target_count'] > 0 && $summary['is_balanced']));

        return [
            'summary' => [
                'total_pool_places' => $pool->count(),
                'scheduled_places' => $pool->filter(fn (TripPlace $tripPlace): bool => $tripPlace->itineraryItem !== null)->count(),
                'unscheduled_places' => $pool->filter(fn (TripPlace $tripPlace): bool => $tripPlace->itineraryItem === null)->count(),
                'day_count' => $dayCount,
                'balance_score' => $requiredCategories > 0 ? (int) round(($metCategories / $requiredCategories) * 100) : 100,
            ],
            'categories' => $categorySummaries,
            'gaps' => $gaps,
        ];
    }

    protected function gapReason(string $category, int $dayCount): string
    {
        return match ($category) {
            SavedPlaceCategory::Hotel->value => 'A multi-day trip usually needs at least one lodging anchor.',
            SavedPlaceCategory::Restaurant->value => 'The current plan is light on food stops relative to the trip length.',
            SavedPlaceCategory::Activity->value => 'The itinerary needs more core experiences to fill the travel days.',
            SavedPlaceCategory::Transport->value => 'A transport stop helps connect arrival, departure, or intercity movement.',
            default => 'This category is currently underrepresented in the trip.',
        };
    }
}
