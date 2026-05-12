<?php

namespace App\Services\Trips;

use App\Enums\SavedPlaceCategory;
use App\Enums\TripAiRunStatus;
use App\Enums\TripAiRunType;
use App\Enums\TripPlaceSource;
use App\Enums\TripSuggestionStatus;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripAiRun;
use App\Models\TripPlace;
use App\Models\TripSuggestion;
use App\Models\User;
use App\Services\AI\AiRequestLogger;
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripSuggestionService
{
    public function __construct(
        protected AiRequestLogger $aiRequestLogger,
        protected SubscriptionService $subscriptionService,
        protected TripBalanceService $tripBalanceService,
        protected TripPoolService $tripPoolService,
    ) {
    }

    /**
     * @return Collection<int, TripSuggestion>
     */
    public function list(Trip $trip): Collection
    {
        return TripSuggestion::query()
            ->where('trip_id', $trip->id)
            ->with(['savedPlace.location', 'location'])
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{run: TripAiRun, suggestions: Collection<int, TripSuggestion>}
     */
    public function generate(Trip $trip, User $actor, array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 6);
        $forceRefresh = (bool) ($options['force_refresh'] ?? false);

        $candidates = $this->candidateSavedPlaces($trip);

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'trip' => ['No eligible saved places are available to suggest for this trip.'],
            ]);
        }

        $inputHash = $this->contextHash($trip, $candidates);
        $latest = TripAiRun::query()
            ->where('trip_id', $trip->id)
            ->where('type', TripAiRunType::Suggestions)
            ->where('status', TripAiRunStatus::Completed)
            ->latest('id')
            ->first();

        if ($latest && ! $forceRefresh && $latest->input_hash === $inputHash) {
            $suggestions = $this->list($trip)->where('trip_ai_run_id', $latest->id)->values();

            return [
                'run' => $latest->setAttribute('was_cached', true)->setAttribute('is_stale', false),
                'suggestions' => $suggestions,
            ];
        }

        $model = $this->modelForActor($actor);

        $run = TripAiRun::query()->create([
            'trip_id' => $trip->id,
            'requested_by_user_id' => $actor->id,
            'type' => TripAiRunType::Suggestions,
            'status' => TripAiRunStatus::Pending,
            'provider' => 'local_heuristic',
            'model' => $model,
            'trip_version' => $trip->version,
            'input_hash' => $inputHash,
        ]);

        $aiRequest = $this->aiRequestLogger->start(
            user: $actor,
            contextType: 'suggestion',
            contextId: $run->id,
            provider: 'local_heuristic',
            model: $model,
            requestHash: $inputHash,
        );

        try {
            $balance = $this->tripBalanceService->summarize($trip);
            $gaps = collect($balance['gaps'] ?? [])->keyBy('category');
            $anchors = $this->anchorContext($trip);

            $ranked = $candidates->map(function (SavedPlace $savedPlace) use ($gaps, $anchors): array {
                $score = 20.0;
                $reasons = [];
                $category = $savedPlace->category?->value ?? SavedPlaceCategory::Other->value;

                if ($gaps->has($category)) {
                    $score += 35;
                    $reasons[] = 'This category is currently underrepresented in the trip.';
                }

                if ($savedPlace->is_favorite) {
                    $score += 12;
                    $reasons[] = 'Marked as a favorite saved place.';
                }

                $location = $savedPlace->location;
                $distance = $location ? $this->nearestDistanceMeters($location->latitude, $location->longitude, $anchors['coordinates']) : null;

                if ($location && $anchors['cities']->contains(fn (string $city): bool => strcasecmp($city, (string) $location->city) === 0)) {
                    $score += 20;
                    $reasons[] = 'Matches a city already represented in the trip route.';
                } elseif ($location && $anchors['countries']->contains(fn (string $country): bool => strcasecmp($country, (string) $location->country_code) === 0)) {
                    $score += 10;
                    $reasons[] = 'Matches the trip country context.';
                }

                if ($distance !== null) {
                    if ($distance <= 5_000) {
                        $score += 15;
                        $reasons[] = 'Located very close to the current route anchors.';
                    } elseif ($distance <= 25_000) {
                        $score += 8;
                        $reasons[] = 'Reasonably close to the current route anchors.';
                    }
                }

                if ($reasons === []) {
                    $reasons[] = 'This saved place broadens the mix of stops in the trip.';
                }

                return [
                    'saved_place' => $savedPlace,
                    'category' => $category,
                    'score' => round($score, 2),
                    'distance_meters' => $distance,
                    'reasons' => $reasons,
                ];
            })->sortByDesc('score')->take($limit)->values();

            $suggestions = DB::transaction(function () use ($trip, $run, $ranked): Collection {
                TripSuggestion::query()
                    ->where('trip_id', $trip->id)
                    ->where('status', TripSuggestionStatus::Suggested)
                    ->delete();

                $created = new Collection();

                foreach ($ranked as $entry) {
                    /** @var SavedPlace $savedPlace */
                    $savedPlace = $entry['saved_place'];

                    $created->push(TripSuggestion::query()->create([
                        'trip_id' => $trip->id,
                        'trip_ai_run_id' => $run->id,
                        'saved_place_id' => $savedPlace->id,
                        'location_id' => $savedPlace->location_id,
                        'title' => $savedPlace->title_override ?: $savedPlace->location?->name ?: 'Suggested place',
                        'category' => $entry['category'],
                        'summary' => implode(' ', $entry['reasons']),
                        'score' => $entry['score'],
                        'distance_meters' => $entry['distance_meters'],
                        'status' => TripSuggestionStatus::Suggested,
                        'raw_payload' => [
                            'reasons' => $entry['reasons'],
                        ],
                    ]));
                }

                return $created;
            });

            $run->update([
                'status' => TripAiRunStatus::Completed,
                'result_payload' => [
                    'summary' => 'Generated '.$suggestions->count().' trip suggestions from member saved places.',
                    'meta' => [
                        'suggestions_count' => $suggestions->count(),
                    ],
                ],
                'error_message' => null,
            ]);

            $this->aiRequestLogger->complete(
                $aiRequest,
                status: TripAiRunStatus::Completed->value,
                responseExcerpt: 'Generated '.$suggestions->count().' trip suggestions.',
            );

            return [
                'run' => $run->fresh(),
                'suggestions' => $this->list($trip)->where('trip_ai_run_id', $run->id)->values(),
            ];
        } catch (\Throwable $exception) {
            $run->update([
                'status' => TripAiRunStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            $this->aiRequestLogger->complete(
                $aiRequest,
                status: TripAiRunStatus::Failed->value,
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }
    }

    /**
     * @return array{suggestion: TripSuggestion, trip_place: TripPlace}
     */
    public function accept(Trip $trip, TripSuggestion $suggestion, User $actor): array
    {
        $this->guardSuggestionTrip($trip, $suggestion);

        if (! $suggestion->savedPlace) {
            throw ValidationException::withMessages([
                'suggestion' => ['This suggestion can no longer be added because its source place is missing.'],
            ]);
        }

        return DB::transaction(function () use ($trip, $suggestion, $actor): array {
            $existingTripPlace = TripPlace::query()
                ->where('trip_id', $trip->id)
                ->where('saved_place_id', $suggestion->saved_place_id)
                ->first();

            $tripPlace = $existingTripPlace
                ?? $this->tripPoolService->createFromSuggestedSavedPlace($trip, $suggestion->savedPlace, $actor, [
                    'trip_category' => $suggestion->category,
                    'notes' => $suggestion->summary,
                    'source' => TripPlaceSource::AiSuggestion,
                ]);

            $suggestion->update([
                'status' => TripSuggestionStatus::Accepted,
            ]);

            return [
                'suggestion' => $suggestion->fresh(['savedPlace.location', 'location']),
                'trip_place' => $tripPlace,
            ];
        });
    }

    public function dismiss(Trip $trip, TripSuggestion $suggestion): TripSuggestion
    {
        $this->guardSuggestionTrip($trip, $suggestion);

        $suggestion->update([
            'status' => TripSuggestionStatus::Dismissed,
        ]);

        return $suggestion->fresh(['savedPlace.location', 'location']);
    }

    /**
     * @return Collection<int, SavedPlace>
     */
    protected function candidateSavedPlaces(Trip $trip): Collection
    {
        $memberIds = $trip->members()->pluck('user_id');
        $tripSavedPlaceIds = $trip->pool()->pluck('saved_place_id');

        return SavedPlace::query()
            ->whereIn('user_id', $memberIds)
            ->whereNotIn('id', $tripSavedPlaceIds)
            ->whereHas('location', fn ($query) => $query->where('is_moderated_hidden', false))
            ->with('location')
            ->orderByDesc('is_favorite')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @param  Collection<int, SavedPlace>  $candidates
     */
    protected function contextHash(Trip $trip, Collection $candidates): string
    {
        return sha1(json_encode([
            'trip_id' => $trip->id,
            'pool_ids' => $trip->pool()->pluck('id')->all(),
            'candidate_ids' => $candidates->pluck('id')->all(),
            'candidate_versions' => $candidates->map(fn (SavedPlace $savedPlace): array => [
                'id' => $savedPlace->id,
                'updated_at' => optional($savedPlace->updated_at)?->timestamp,
            ])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{cities: \Illuminate\Support\Collection<int, string>, countries: \Illuminate\Support\Collection<int, string>, coordinates: array<int, array{lat: float, lng: float}>}
     */
    protected function anchorContext(Trip $trip): array
    {
        $pool = TripPlace::query()
            ->where('trip_id', $trip->id)
            ->with('savedPlace.location')
            ->get();

        $cities = collect();
        $countries = collect();
        $coordinates = [];

        foreach ($pool as $tripPlace) {
            $location = $tripPlace->savedPlace?->location;

            if (! $location) {
                continue;
            }

            if ($location->city) {
                $cities->push($location->city);
            }

            if ($location->country_code) {
                $countries->push($location->country_code);
            }

            if ($location->latitude !== null && $location->longitude !== null) {
                $coordinates[] = [
                    'lat' => (float) $location->latitude,
                    'lng' => (float) $location->longitude,
                ];
            }
        }

        if ($trip->start_latitude !== null && $trip->start_longitude !== null) {
            $coordinates[] = [
                'lat' => (float) $trip->start_latitude,
                'lng' => (float) $trip->start_longitude,
            ];
        }

        if ($trip->end_latitude !== null && $trip->end_longitude !== null) {
            $coordinates[] = [
                'lat' => (float) $trip->end_latitude,
                'lng' => (float) $trip->end_longitude,
            ];
        }

        return [
            'cities' => $cities->filter()->unique()->values(),
            'countries' => $countries->filter()->unique()->values(),
            'coordinates' => $coordinates,
        ];
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $anchors
     */
    protected function nearestDistanceMeters(float|null $latitude, float|null $longitude, array $anchors): int|null
    {
        if ($latitude === null || $longitude === null || $anchors === []) {
            return null;
        }

        $distances = array_map(function (array $anchor) use ($latitude, $longitude): int {
            $earthRadius = 6371000;
            $latFrom = deg2rad($latitude);
            $lonFrom = deg2rad($longitude);
            $latTo = deg2rad($anchor['lat']);
            $lonTo = deg2rad($anchor['lng']);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(
                pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
            ));

            return (int) round($angle * $earthRadius);
        }, $anchors);

        sort($distances);

        return $distances[0] ?? null;
    }

    protected function guardSuggestionTrip(Trip $trip, TripSuggestion $suggestion): void
    {
        if ($suggestion->trip_id !== $trip->id) {
            throw ValidationException::withMessages([
                'suggestion' => ['The selected suggestion does not belong to this trip.'],
            ]);
        }
    }

    protected function modelForActor(User $actor): string
    {
        return $this->subscriptionService->featureEnabled($actor, 'enhanced_ai', false)
            ? 'gap-fill-suggester-premium'
            : 'gap-fill-suggester';
    }
}
