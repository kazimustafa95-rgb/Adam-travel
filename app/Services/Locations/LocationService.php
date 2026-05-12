<?php

namespace App\Services\Locations;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class LocationService
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function resolveForSavedPlace(array $payload): Location
    {
        if (! empty($payload['location_id'])) {
            $location = Location::query()
                ->where('id', $payload['location_id'])
                ->where('is_moderated_hidden', false)
                ->first();

            if (! $location) {
                throw ValidationException::withMessages([
                    'location_id' => ['The selected location is not available.'],
                ]);
            }

            return $location;
        }

        $locationPayload = $payload['location'] ?? [];
        $providerPlaceId = $locationPayload['provider_place_id'] ?? null;
        $providerSource = $locationPayload['provider_source'] ?? null;
        $name = trim((string) ($locationPayload['name'] ?? ''));
        $latitude = $locationPayload['latitude'] ?? null;
        $longitude = $locationPayload['longitude'] ?? null;

        $query = Location::query()->where('is_moderated_hidden', false);

        if ($providerPlaceId && $providerSource) {
            $query->where('provider_place_id', $providerPlaceId)
                ->where('provider_source', $providerSource);
        } else {
            $query->where('name', $name);

            if ($latitude !== null && $longitude !== null) {
                $query->where('latitude', $latitude)->where('longitude', $longitude);
            }
        }

        $location = $query->first();

        if ($location) {
            return $location;
        }

        return Location::query()->create([
            'name' => $name,
            'slug' => $locationPayload['slug'] ?? Str::slug($name),
            'category' => $locationPayload['category'] ?? null,
            'address_line' => $locationPayload['address_line'] ?? null,
            'city' => $locationPayload['city'] ?? null,
            'region' => $locationPayload['region'] ?? null,
            'country_code' => isset($locationPayload['country_code']) ? strtoupper((string) $locationPayload['country_code']) : null,
            'postal_code' => $locationPayload['postal_code'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'provider_place_id' => $providerPlaceId,
            'provider_source' => $providerSource,
            'metadata' => $locationPayload['metadata'] ?? [],
            'is_moderated_hidden' => false,
        ]);
    }

    public function baseVisibleQuery(): Builder
    {
        return Location::query()->where('is_moderated_hidden', false);
    }
}
