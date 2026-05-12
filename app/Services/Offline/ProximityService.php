<?php

namespace App\Services\Offline;

use App\Models\ProximityPromptLog;
use App\Models\SavedPlace;
use App\Models\User;
use App\Services\Support\AppRuntimeConfigService;
use App\Services\Users\UserPreferenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProximityService
{
    public function __construct(
        protected AppRuntimeConfigService $configService,
        protected UserPreferenceService $preferenceService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function check(User $user, array $payload): array
    {
        $preference = $this->preferenceService->ensureDefaults($user);
        $radius = (int) ($payload['radius_meters'] ?? $preference->default_radius_meters ?? $this->configService->integer('proximity.default_radius_meters', 3000));
        $cooldownMinutes = $this->configService->integer('proximity.cooldown_minutes', 180);
        $currentLatitude = (float) $payload['latitude'];
        $currentLongitude = (float) $payload['longitude'];

        $candidates = $this->nearbyCandidates($user, $currentLatitude, $currentLongitude, $radius)
            ->take(3)
            ->values();

        $nextEligibleAt = $user->last_proximity_prompt_at?->copy()->addMinutes($cooldownMinutes);
        $cooldownActive = $nextEligibleAt?->isFuture() ?? false;
        $shouldPrompt = $candidates->isNotEmpty() && ! $cooldownActive;

        if ($shouldPrompt) {
            $nearest = $candidates->first();

            ProximityPromptLog::query()->create([
                'user_id' => $user->id,
                'saved_place_id' => $nearest['saved_place']->id,
                'latitude' => $currentLatitude,
                'longitude' => $currentLongitude,
                'distance_meters' => $nearest['distance_meters'],
                'shown_at' => now(),
            ]);

            $user->forceFill([
                'last_proximity_prompt_at' => now(),
            ])->save();

            $nextEligibleAt = now()->addMinutes($cooldownMinutes);
        }

        return [
            'should_prompt' => $shouldPrompt,
            'cooldown_active' => $cooldownActive,
            'cooldown_minutes' => $cooldownMinutes,
            'radius_meters' => $radius,
            'next_eligible_at' => $nextEligibleAt?->toIso8601String(),
            'nearby_places' => $candidates->map(fn (array $entry): array => [
                'saved_place' => $entry['saved_place'],
                'distance_meters' => $entry['distance_meters'],
            ])->all(),
        ];
    }

    /**
     * @return Collection<int, array{saved_place: SavedPlace, distance_meters: int}>
     */
    protected function nearbyCandidates(User $user, float $latitude, float $longitude, int $radiusMeters): Collection
    {
        $latDelta = $radiusMeters / 111320;
        $lonDivisor = max(cos(deg2rad($latitude)), 0.01);
        $lonDelta = $radiusMeters / (111320 * $lonDivisor);

        $savedPlaces = SavedPlace::query()
            ->with('location')
            ->where('user_id', $user->id)
            ->whereHas('location', function ($query) use ($latitude, $longitude, $latDelta, $lonDelta): void {
                $query->where('is_moderated_hidden', false)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                    ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta]);
            })
            ->get();

        return $savedPlaces
            ->map(function (SavedPlace $savedPlace) use ($latitude, $longitude): array {
                $distanceMeters = $this->distanceMeters(
                    $latitude,
                    $longitude,
                    (float) $savedPlace->location->latitude,
                    (float) $savedPlace->location->longitude,
                );

                return [
                    'saved_place' => $savedPlace,
                    'distance_meters' => $distanceMeters,
                ];
            })
            ->filter(fn (array $entry) => $entry['distance_meters'] <= $radiusMeters)
            ->sortBy('distance_meters')
            ->values();
    }

    protected function distanceMeters(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): int
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return (int) round($angle * $earthRadius);
    }
}
