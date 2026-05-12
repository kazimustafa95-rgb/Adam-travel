<?php

namespace App\Services\Trips;

use App\Enums\ItineraryItemSource;
use App\Enums\SavedPlaceCategory;
use App\Enums\TripAiRunStatus;
use App\Enums\TripAiRunType;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Models\TripAiRun;
use App\Models\TripPlace;
use App\Models\User;
use App\Services\AI\AiRequestLogger;
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TripAiItineraryService
{
    public function __construct(
        protected AiRequestLogger $aiRequestLogger,
        protected SubscriptionService $subscriptionService,
        protected ItineraryService $itineraryService,
    ) {
    }

    public function latestForTrip(Trip $trip): TripAiRun|null
    {
        $run = TripAiRun::query()
            ->where('trip_id', $trip->id)
            ->where('type', TripAiRunType::Itinerary)
            ->latest('id')
            ->with('requestedBy')
            ->first();

        if (! $run) {
            return null;
        }

        return $run->setAttribute('is_stale', $run->input_hash !== $this->contextHash($trip));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Trip $trip, User $actor, array $options = []): TripAiRun
    {
        $this->guardTripReadiness($trip);

        $pool = $this->planningPool($trip);
        $inputHash = $this->contextHash($trip, $pool);
        $latest = TripAiRun::query()
            ->where('trip_id', $trip->id)
            ->where('type', TripAiRunType::Itinerary)
            ->where('status', TripAiRunStatus::Completed)
            ->latest('id')
            ->with('requestedBy')
            ->first();

        $forceRefresh = (bool) ($options['force_refresh'] ?? false);

        if ($latest && ! $forceRefresh && $latest->input_hash === $inputHash) {
            return $latest->setAttribute('was_cached', true)->setAttribute('is_stale', false);
        }

        $model = $this->modelForActor($actor);

        $run = TripAiRun::query()->create([
            'trip_id' => $trip->id,
            'requested_by_user_id' => $actor->id,
            'type' => TripAiRunType::Itinerary,
            'status' => TripAiRunStatus::Pending,
            'provider' => 'local_heuristic',
            'model' => $model,
            'trip_version' => $trip->version,
            'input_hash' => $inputHash,
        ]);

        $aiRequest = $this->aiRequestLogger->start(
            user: $actor,
            contextType: 'itinerary',
            contextId: $run->id,
            provider: 'local_heuristic',
            model: $model,
            requestHash: $inputHash,
        );

        try {
            $payload = $this->buildProposal($trip, $pool);

            $run->update([
                'status' => TripAiRunStatus::Completed,
                'result_payload' => $payload,
                'error_message' => null,
            ]);

            $this->aiRequestLogger->complete(
                $aiRequest,
                status: TripAiRunStatus::Completed->value,
                responseExcerpt: (string) ($payload['summary'] ?? 'Trip itinerary proposal generated successfully.'),
            );

            return $run->fresh('requestedBy')->setAttribute('is_stale', false);
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
     * @return array{run: TripAiRun, itinerary: array{days: Collection<int, ItineraryDay>, meta: array<string, int>}}
     */
    public function apply(Trip $trip, User $actor, int|null $runId = null): array
    {
        $run = $this->resolveRun($trip, $runId);

        if ($run->input_hash !== $this->contextHash($trip)) {
            throw new ConflictHttpException('The trip changed after this AI itinerary was generated. Generate a new proposal before applying it.');
        }

        $payload = $run->result_payload ?? [];
        $days = $payload['days'] ?? [];

        if ($days === []) {
            throw ValidationException::withMessages([
                'trip_ai_run_id' => ['The selected AI run does not contain an itinerary proposal.'],
            ]);
        }

        DB::transaction(function () use ($trip, $actor, $days, $run): void {
            ItineraryItem::query()
                ->whereHas('day', fn ($query) => $query->where('trip_id', $trip->id))
                ->forceDelete();

            ItineraryDay::query()
                ->where('trip_id', $trip->id)
                ->forceDelete();

            foreach ($days as $dayPayload) {
                $day = ItineraryDay::query()->create([
                    'trip_id' => $trip->id,
                    'day_number' => $dayPayload['day_number'],
                    'trip_date' => $dayPayload['trip_date'] ?? null,
                    'title' => $dayPayload['title'] ?? null,
                    'notes' => $dayPayload['notes'] ?? null,
                    'version' => 1,
                ]);

                foreach ($dayPayload['items'] ?? [] as $itemPayload) {
                    $tripPlace = $trip->pool()->whereKey($itemPayload['trip_place_id'])->first();

                    if (! $tripPlace) {
                        throw ValidationException::withMessages([
                            'trip_ai_run_id' => ['The proposal references a trip place that is no longer available.'],
                        ]);
                    }

                    ItineraryItem::query()->create([
                        'itinerary_day_id' => $day->id,
                        'trip_place_id' => $tripPlace->id,
                        'scheduled_by_user_id' => $actor->id,
                        'source' => ItineraryItemSource::AiGenerated,
                        'starts_at' => $itemPayload['starts_at'] ?? null,
                        'ends_at' => $itemPayload['ends_at'] ?? null,
                        'sort_order' => $itemPayload['sort_order'],
                        'notes' => $itemPayload['reason'] ?? null,
                        'version' => 1,
                    ]);
                }
            }

            $trip->increment('version');

            $run->update([
                'applied_at' => now(),
            ]);
        });

        return [
            'run' => $run->fresh('requestedBy'),
            'itinerary' => $this->itineraryService->list($trip->fresh()),
        ];
    }

    /**
     * @param  Collection<int, TripPlace>|null  $pool
     */
    protected function contextHash(Trip $trip, Collection|null $pool = null): string
    {
        $pool ??= $this->planningPool($trip);

        return sha1(json_encode([
            'trip_id' => $trip->id,
            'start_date' => optional($trip->start_date)?->toDateString(),
            'end_date' => optional($trip->end_date)?->toDateString(),
            'pool' => $pool->map(fn (TripPlace $tripPlace): array => [
                'id' => $tripPlace->id,
                'category' => $tripPlace->trip_category,
                'hearts_count' => (int) $tripPlace->hearts_count,
                'favorite' => (bool) ($tripPlace->savedPlace?->is_favorite ?? false),
                'updated_at' => optional($tripPlace->updated_at)?->timestamp,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return Collection<int, TripPlace>
     */
    protected function planningPool(Trip $trip): Collection
    {
        return TripPlace::query()
            ->where('trip_id', $trip->id)
            ->with(['savedPlace.location', 'savedPlace'])
            ->withCount('hearts')
            ->orderByDesc('hearts_count')
            ->orderByDesc('created_at')
            ->get();
    }

    protected function guardTripReadiness(Trip $trip): void
    {
        if (! $trip->start_date || ! $trip->end_date) {
            throw ValidationException::withMessages([
                'trip' => ['Trips must have a start and end date before AI itinerary generation can run.'],
            ]);
        }

        if ($trip->end_date->lt($trip->start_date)) {
            throw ValidationException::withMessages([
                'trip' => ['Trip dates are invalid.'],
            ]);
        }

        if (! $trip->pool()->exists()) {
            throw ValidationException::withMessages([
                'trip' => ['Add places to the shared trip pool before generating an AI itinerary.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, TripPlace>  $pool
     * @return array<string, mixed>
     */
    protected function buildProposal(Trip $trip, Collection $pool): array
    {
        $dayCount = max(1, $trip->start_date->diffInDays($trip->end_date) + 1);
        $days = collect(range(1, $dayCount))->map(function (int $dayNumber) use ($trip, $dayCount): array {
            $date = $trip->start_date->copy()->addDays($dayNumber - 1)->toDateString();

            return [
                'day_number' => $dayNumber,
                'trip_date' => $date,
                'title' => $dayNumber === 1
                    ? 'Arrival and first stops'
                    : ($dayNumber === $dayCount ? 'Wrap-up and departure' : 'Exploration day '.$dayNumber),
                'notes' => null,
                'items' => [],
            ];
        })->keyBy('day_number')->all();

        $buckets = [
            'hotel' => [],
            'transport' => [],
            'restaurant' => [],
            'experience' => [],
        ];

        foreach ($pool as $tripPlace) {
            $category = $tripPlace->trip_category ?? $tripPlace->savedPlace?->category?->value ?? SavedPlaceCategory::Other->value;

            $entry = [
                'trip_place_id' => $tripPlace->id,
                'title' => $tripPlace->savedPlace?->title_override ?: $tripPlace->savedPlace?->location?->name,
                'category' => $category,
                'priority' => ((int) $tripPlace->hearts_count * 10) + (($tripPlace->savedPlace?->is_favorite ?? false) ? 5 : 0),
            ];

            $bucket = match ($category) {
                SavedPlaceCategory::Hotel->value => 'hotel',
                SavedPlaceCategory::Transport->value => 'transport',
                SavedPlaceCategory::Restaurant->value => 'restaurant',
                default => 'experience',
            };

            $buckets[$bucket][] = $entry;
        }

        foreach ($buckets as &$bucket) {
            usort($bucket, fn (array $left, array $right): int => $right['priority'] <=> $left['priority']);
        }
        unset($bucket);

        if ($buckets['transport'] !== []) {
            $item = array_shift($buckets['transport']);
            $this->pushSuggestedItem($days[1], $trip->start_date->copy()->setTime(8, 30), $item, 'Front-loaded to anchor arrival or early movement.');
        }

        if ($buckets['hotel'] !== []) {
            $item = array_shift($buckets['hotel']);
            $this->pushSuggestedItem($days[1], $trip->start_date->copy()->setTime(15, 0), $item, 'Placed on day one to establish a lodging base.');
        }

        $experienceTimes = ['10:00', '13:30', '16:30'];
        $restaurantTimes = ['12:30', '19:00'];

        while ($buckets['experience'] !== []) {
            for ($dayNumber = 1; $dayNumber <= $dayCount && $buckets['experience'] !== []; $dayNumber++) {
                foreach ($experienceTimes as $time) {
                    if ($buckets['experience'] === []) {
                        break;
                    }

                    if ($this->dayHasTime($days[$dayNumber]['items'], $time)) {
                        continue;
                    }

                    $item = array_shift($buckets['experience']);
                    $date = Carbon::parse($days[$dayNumber]['trip_date'])->setTimeFromTimeString($time);
                    $this->pushSuggestedItem($days[$dayNumber], $date, $item, 'Distributed to balance experiences across the trip.');
                }
            }
        }

        while ($buckets['restaurant'] !== []) {
            for ($dayNumber = 1; $dayNumber <= $dayCount && $buckets['restaurant'] !== []; $dayNumber++) {
                foreach ($restaurantTimes as $time) {
                    if ($buckets['restaurant'] === []) {
                        break;
                    }

                    if ($this->dayHasTime($days[$dayNumber]['items'], $time)) {
                        continue;
                    }

                    $item = array_shift($buckets['restaurant']);
                    $date = Carbon::parse($days[$dayNumber]['trip_date'])->setTimeFromTimeString($time);
                    $this->pushSuggestedItem($days[$dayNumber], $date, $item, 'Inserted near meal windows to keep the day practical.');
                }
            }
        }

        if ($buckets['transport'] !== []) {
            $item = array_shift($buckets['transport']);
            $date = $trip->end_date->copy()->setTime(18, 0);
            $this->pushSuggestedItem($days[$dayCount], $date, $item, 'Held for the final day to support departure or onward transit.');
        }

        foreach (['hotel', 'transport'] as $bucketName) {
            while ($buckets[$bucketName] !== []) {
                for ($dayNumber = 1; $dayNumber <= $dayCount && $buckets[$bucketName] !== []; $dayNumber++) {
                    $fallbackTime = Carbon::parse($days[$dayNumber]['trip_date'])->setTime(20, 30);
                    $item = array_shift($buckets[$bucketName]);
                    $this->pushSuggestedItem($days[$dayNumber], $fallbackTime, $item, 'Placed in the next available slot after higher-priority stops were arranged.');
                }
            }
        }

        $normalizedDays = array_values(array_map(function (array $day): array {
            usort($day['items'], fn (array $left, array $right): int => strcmp($left['starts_at'], $right['starts_at']));

            $day['items'] = array_map(function (array $item, int $index): array {
                $item['sort_order'] = $index + 1;

                return $item;
            }, $day['items'], array_keys($day['items']));

            return $day;
        }, $days));

        $scheduledCount = collect($normalizedDays)->sum(fn (array $day): int => count($day['items']));

        return [
            'summary' => 'Generated a balanced '.$dayCount.'-day plan around '.$scheduledCount.' shared trip stops.',
            'meta' => [
                'day_count' => $dayCount,
                'scheduled_count' => $scheduledCount,
                'heuristic' => 'balanced-route-planner',
            ],
            'days' => $normalizedDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $day
     * @param  array<string, mixed>  $item
     */
    protected function pushSuggestedItem(array &$day, Carbon $startsAt, array $item, string $reason): void
    {
        $day['items'][] = [
            'trip_place_id' => $item['trip_place_id'],
            'sort_order' => count($day['items']) + 1,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => null,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function dayHasTime(array $items, string $time): bool
    {
        foreach ($items as $item) {
            if (str_ends_with((string) $item['starts_at'], $time.':00+00:00') || str_contains((string) $item['starts_at'], 'T'.$time.':00')) {
                return true;
            }
        }

        return false;
    }

    protected function resolveRun(Trip $trip, int|null $runId): TripAiRun
    {
        $run = TripAiRun::query()
            ->where('trip_id', $trip->id)
            ->where('type', TripAiRunType::Itinerary)
            ->when($runId !== null, fn ($query) => $query->whereKey($runId))
            ->latest('id')
            ->first();

        if (! $run || $run->status !== TripAiRunStatus::Completed) {
            throw ValidationException::withMessages([
                'trip_ai_run_id' => ['A completed AI itinerary proposal is required before apply can run.'],
            ]);
        }

        return $run;
    }

    protected function modelForActor(User $actor): string
    {
        return $this->subscriptionService->featureEnabled($actor, 'enhanced_ai', false)
            ? 'balanced-route-planner-premium'
            : 'balanced-route-planner';
    }
}
