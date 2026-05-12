<?php

namespace App\Services\Offline;

use App\Enums\SavedPlaceCategory;
use App\Http\Resources\Api\V1\ItineraryDayResource;
use App\Http\Resources\Api\V1\ItineraryItemResource;
use App\Http\Resources\Api\V1\OfflinePackageResource;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Http\Resources\Api\V1\TripPlaceResource;
use App\Http\Resources\Api\V1\TripResource;
use App\Http\Resources\Api\V1\UserPreferenceResource;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\OfflinePackage;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripPlace;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\SavedPlaces\SavedPlaceService;
use App\Services\Users\UserPreferenceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncService
{
    public function __construct(
        protected UserPreferenceService $preferenceService,
        protected SavedPlaceService $savedPlaceService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function pull(User $user, array $payload): array
    {
        $cursor = ! empty($payload['cursor']) ? Carbon::parse((string) $payload['cursor']) : null;
        $snapshotAt = now();
        $preference = $this->preferenceService->ensureDefaults($user);
        $tripIds = $this->memberTripIds($user);
        $request = Request::create('/api/v1/sync');

        $savedPlaces = SavedPlace::query()
            ->with('location')
            ->where('user_id', $user->id)
            ->when($cursor, fn ($query) => $query->where('updated_at', '>', $cursor))
            ->where('updated_at', '<=', $snapshotAt)
            ->get();

        $trips = Trip::query()
            ->whereIn('id', $tripIds)
            ->with(['owner', 'members.user'])
            ->withCount(['members', 'pool'])
            ->when($cursor, fn ($query) => $query->where('updated_at', '>', $cursor))
            ->where('updated_at', '<=', $snapshotAt)
            ->get();

        $tripPlaces = TripPlace::query()
            ->whereIn('trip_id', $tripIds)
            ->with(['savedPlace.location', 'addedBy', 'hearts'])
            ->withCount('hearts')
            ->when($cursor, fn ($query) => $query->where('updated_at', '>', $cursor))
            ->where('updated_at', '<=', $snapshotAt)
            ->get();

        $itineraryDays = ItineraryDay::query()
            ->whereIn('trip_id', $tripIds)
            ->with([
                'items.tripPlace.savedPlace.location',
                'items.tripPlace.savedPlace',
                'items.tripPlace.addedBy',
                'items.tripPlace.hearts',
                'items.scheduledBy',
            ])
            ->when($cursor, fn ($query) => $query->where('updated_at', '>', $cursor))
            ->where('updated_at', '<=', $snapshotAt)
            ->get();

        $itineraryItems = ItineraryItem::query()
            ->whereHas('day', fn ($query) => $query->whereIn('trip_id', $tripIds))
            ->with(['tripPlace.savedPlace.location', 'tripPlace.savedPlace', 'tripPlace.addedBy', 'tripPlace.hearts', 'scheduledBy'])
            ->when($cursor, fn ($query) => $query->where('updated_at', '>', $cursor))
            ->where('updated_at', '<=', $snapshotAt)
            ->get();

        $offlinePackages = OfflinePackage::query()
            ->where('user_id', $user->id)
            ->with('trip.owner', 'trip.members.user')
            ->when($cursor, fn ($query) => $query->where('updated_at', '>', $cursor))
            ->where('updated_at', '<=', $snapshotAt)
            ->get();

        $deleted = [
            ...$this->deletedMarkers(SavedPlace::class, 'saved_place', $cursor, $snapshotAt, fn ($query) => $query->where('user_id', $user->id)),
            ...$this->deletedMarkers(Trip::class, 'trip', $cursor, $snapshotAt, fn ($query) => $query->whereIn('id', $tripIds)),
            ...$this->deletedMarkers(TripPlace::class, 'trip_place', $cursor, $snapshotAt, fn ($query) => $query->whereIn('trip_id', $tripIds)),
            ...$this->deletedMarkers(ItineraryDay::class, 'itinerary_day', $cursor, $snapshotAt, fn ($query) => $query->whereIn('trip_id', $tripIds)),
            ...$this->deletedMarkers(ItineraryItem::class, 'itinerary_item', $cursor, $snapshotAt, fn ($query) => $query->whereHas('day', fn ($dayQuery) => $dayQuery->whereIn('trip_id', $tripIds))),
        ];

        $this->touchDevice($user, $payload);

        return [
            'server_time' => $snapshotAt->toIso8601String(),
            'next_cursor' => $snapshotAt->toIso8601String(),
            'changes' => [
                'user_preference' => (new UserPreferenceResource($preference))->toArray($request),
                'saved_places' => SavedPlaceResource::collection($savedPlaces)->resolve($request),
                'trips' => TripResource::collection($trips)->resolve($request),
                'trip_places' => TripPlaceResource::collection($tripPlaces)->resolve($request),
                'itinerary_days' => ItineraryDayResource::collection($itineraryDays)->resolve($request),
                'itinerary_items' => ItineraryItemResource::collection($itineraryItems)->resolve($request),
                'offline_packages' => OfflinePackageResource::collection($offlinePackages)->resolve($request),
                'deleted' => $deleted,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{applied: array<string, mixed>, conflicts: array<int, array<string, mixed>>}
     */
    public function push(User $user, array $payload): array
    {
        $applied = [
            'user_preference' => null,
            'saved_places' => [],
        ];
        $conflicts = [];
        $request = Request::create('/api/v1/sync/push');

        foreach ($payload['changes'] as $change) {
            $entity = $change['entity'];
            $action = $change['action'];

            if ($entity === 'user_preference' && $action === 'update') {
                continue;
            }

            if ($entity === 'saved_place') {
                $savedPlace = SavedPlace::query()->where('user_id', $user->id)->find($change['record_id']);

                if (! $savedPlace) {
                    $conflicts[] = [
                        'entity' => 'saved_place',
                        'record_id' => $change['record_id'],
                        'reason' => 'The saved place no longer exists.',
                    ];

                    continue;
                }

                if ((int) ($change['version'] ?? 0) !== $savedPlace->version) {
                    $conflicts[] = [
                        'entity' => 'saved_place',
                        'record_id' => $savedPlace->id,
                        'reason' => 'The saved place version is stale.',
                        'current_version' => $savedPlace->version,
                    ];
                }
            }
        }

        if ($conflicts !== []) {
            return [
                'applied' => $applied,
                'conflicts' => $conflicts,
            ];
        }

        DB::transaction(function () use ($user, $payload, &$applied, $request): void {
            foreach ($payload['changes'] as $change) {
                $entity = $change['entity'];
                $action = $change['action'];

                if ($entity === 'user_preference' && $action === 'update') {
                    $preference = $this->preferenceService->update($user, $change['payload'] ?? []);
                    $applied['user_preference'] = (new UserPreferenceResource($preference))->toArray($request);
                    continue;
                }

                if ($entity === 'saved_place') {
                    /** @var SavedPlace $savedPlace */
                    $savedPlace = SavedPlace::query()->where('user_id', $user->id)->findOrFail($change['record_id']);

                    if ($action === 'update') {
                        $updated = $this->savedPlaceService->update($savedPlace, $this->normalizeSavedPlacePayload($change['payload'] ?? []));
                        $applied['saved_places'][] = (new SavedPlaceResource($updated))->toArray($request);
                    }

                    if ($action === 'delete') {
                        $this->savedPlaceService->delete($savedPlace);
                        $applied['saved_places'][] = [
                            'id' => $savedPlace->id,
                            'uuid' => $savedPlace->uuid,
                            'deleted_at' => optional($savedPlace->deleted_at)->toIso8601String(),
                        ];
                    }
                }
            }

            $this->touchDevice($user, $payload);
        });

        return [
            'applied' => $applied,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return list<int>
     */
    protected function memberTripIds(User $user): array
    {
        return Trip::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  callable(\Illuminate\Database\Eloquent\Builder): void  $scope
     * @return array<int, array<string, mixed>>
     */
    protected function deletedMarkers(string $modelClass, string $entityType, Carbon|null $cursor, Carbon $snapshotAt, callable $scope): array
    {
        if (! in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            return [];
        }

        $query = $modelClass::onlyTrashed();
        $scope($query);

        if ($cursor) {
            $query->where('deleted_at', '>', $cursor);
        }

        return $query
            ->where('deleted_at', '<=', $snapshotAt)
            ->get(['id', 'uuid', 'deleted_at'])
            ->map(fn ($record): array => [
                'entity' => $entityType,
                'id' => $record->id,
                'uuid' => $record->uuid,
                'deleted_at' => optional($record->deleted_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeSavedPlacePayload(array $payload): array
    {
        $normalized = array_intersect_key($payload, array_flip([
            'title_override',
            'notes',
            'category',
            'region_label',
            'is_favorite',
            'visibility',
        ]));

        if (array_key_exists('category', $normalized) && ! in_array((string) $normalized['category'], \App\Enums\SavedPlaceCategory::values(), true)) {
            unset($normalized['category']);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function touchDevice(User $user, array $payload): void
    {
        if (empty($payload['device_identifier'])) {
            return;
        }

        $deviceIdentifier = (string) $payload['device_identifier'];
        $deviceHash = hash('sha256', $deviceIdentifier);

        UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_identifier_hash' => $deviceHash,
            ],
            [
                'device_name' => (string) ($payload['device_name'] ?? 'Unknown Device'),
                'device_platform' => (string) ($payload['device_platform'] ?? 'unknown'),
                'last_ip' => request()->ip(),
                'last_synced_at' => now(),
            ],
        );
    }
}
