<?php

namespace App\Services\Offline;

use App\Enums\OfflinePackageScope;
use App\Enums\OfflinePackageStatus;
use App\Http\Resources\Api\V1\ItineraryDayResource;
use App\Http\Resources\Api\V1\TripPlaceResource;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\OfflinePackage;
use App\Models\Trip;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Trips\ItineraryService;
use App\Services\Trips\TripBalanceService;
use App\Services\Trips\TripService;
use App\Services\Support\AppRuntimeConfigService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OfflinePackageService
{
    public function __construct(
        protected AppRuntimeConfigService $configService,
        protected SubscriptionService $subscriptionService,
        protected TripService $tripService,
        protected ItineraryService $itineraryService,
        protected TripBalanceService $tripBalanceService,
    ) {
    }

    /**
     * @return Collection<int, OfflinePackage>
     */
    public function listForUser(User $user, array $filters = []): Collection
    {
        $this->expireStalePackages($user);

        return OfflinePackage::query()
            ->where('user_id', $user->id)
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->with('trip.owner', 'trip.members.user')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (OfflinePackage $package): OfflinePackage {
                $tripVersion = $package->trip?->version;
                $isExpired = $package->expires_at?->isPast() ?? false;
                $isStale = $tripVersion !== null && $tripVersion > $package->manifest_version;

                return $package
                    ->setAttribute('is_expired', $isExpired)
                    ->setAttribute('is_stale', $isStale);
            });
    }

    public function createTripPackage(User $user, Trip $trip): OfflinePackage
    {
        $this->expireStalePackages($user);

        $existing = OfflinePackage::query()
            ->where('user_id', $user->id)
            ->where('trip_id', $trip->id)
            ->where('package_scope', OfflinePackageScope::Trip)
            ->whereIn('status', [OfflinePackageStatus::Queued, OfflinePackageStatus::Ready])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('trip.owner', 'trip.members.user')
            ->first();

        if ($existing && $existing->manifest_version >= $trip->version) {
            return $existing
                ->setAttribute('is_expired', false)
                ->setAttribute('is_stale', false);
        }

        $activePackageCount = OfflinePackage::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OfflinePackageStatus::Queued, OfflinePackageStatus::Ready])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if (! $existing && $activePackageCount >= $this->offlinePackageLimit($user)) {
            throw ValidationException::withMessages([
                'package' => ['Your current plan has reached its offline package limit.'],
            ]);
        }

        $tripDetail = $this->tripService->detailForUser($trip->fresh(), $user);
        $pool = $trip->pool()->with(['savedPlace.location', 'addedBy', 'hearts'])->withCount('hearts')->get();
        $itinerary = $this->itineraryService->list($trip->fresh());
        $balance = $this->tripBalanceService->summarize($trip->fresh());
        $expiresAt = now()->addDays($this->configService->integer('offline.package_ttl_days', 30));
        $request = Request::create('/api/v1/offline/packages');

        $manifestPayload = [
            'trip' => (new TripResource($tripDetail))->toArray($request),
            'pool' => TripPlaceResource::collection($pool)->resolve($request),
            'itinerary' => ItineraryDayResource::collection($itinerary['days'])->resolve($request),
            'balance' => $balance,
            'meta' => [
                'trip_version' => $trip->version,
                'generated_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ];

        $package = $existing ?? new OfflinePackage([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'package_scope' => OfflinePackageScope::Trip,
        ]);

        $package->fill([
            'scope_reference' => $trip->slug,
            'manifest_version' => $trip->version,
            'status' => OfflinePackageStatus::Ready,
            'manifest_payload' => $manifestPayload,
            'expires_at' => $expiresAt,
        ]);
        $package->save();

        return $package->fresh(['trip.owner', 'trip.members.user'])
            ->setAttribute('is_expired', false)
            ->setAttribute('is_stale', false);
    }

    protected function offlinePackageLimit(User $user): int
    {
        return $this->subscriptionService->featureLimit($user, 'offline_packages_limit', 1);
    }

    protected function expireStalePackages(User $user): void
    {
        OfflinePackage::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', OfflinePackageStatus::Expired)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => OfflinePackageStatus::Expired,
            ]);
    }
}
