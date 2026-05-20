<?php

namespace App\Services\LocationIntelligence;

use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GooglePlacesResolverService
{
    private const SEARCH_FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location';

    /**
     * Resolve a place by free-text query and return minimal structured data.
     *
     * @return array<string, mixed>
     */
    public function resolveByText(string $query, int $confidence = 80): array
    {
        $response = $this->request(self::SEARCH_FIELDS)->post('places:searchText', [
            'textQuery'      => $query,
            'languageCode'   => 'en',
            'maxResultCount' => 1,
        ]);

        if (! $response->successful()) {
            throw new LocationIntelligenceException(
                (string) data_get($response->json(), 'error.message', 'Google Places search failed.'),
                $response->serverError() ? 502 : 422,
            );
        }

        /** @var array<string, mixed>|null $place */
        $place = data_get($response->json(), 'places.0');

        if (! is_array($place) || ($place['id'] ?? '') === '') {
            throw new LocationIntelligenceException('No place found for the given query.', 404);
        }

        return [
            'id'         => (string) ($place['id'] ?? ''),
            'name'       => (string) data_get($place, 'displayName.text', ''),
            'address'    => (string) ($place['formattedAddress'] ?? ''),
            'lat'        => (float) data_get($place, 'location.latitude', 0),
            'lng'        => (float) data_get($place, 'location.longitude', 0),
            'confidence' => $confidence,
        ];
    }

    private function request(?string $fieldMask = null): PendingRequest
    {
        $apiKey = trim((string) config('location_intelligence.google.places_api_key'));

        if ($apiKey === '') {
            throw new LocationIntelligenceException('GOOGLE_MAPS_API_KEY is not configured.', 500);
        }

        $headers = [
            'X-Goog-Api-Key'  => $apiKey,
            'Content-Type'    => 'application/json',
        ];

        if ($fieldMask !== null) {
            $headers['X-Goog-FieldMask'] = $fieldMask;
        }

        return Http::baseUrl((string) config('location_intelligence.google.places_base_url'))
            ->acceptJson()
            ->timeout(15)
            ->withHeaders($headers);
    }
}
