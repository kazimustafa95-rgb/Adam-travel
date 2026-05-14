<?php

namespace App\Services\PublicApi;

use App\Exceptions\PublicApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GooglePlaceDetailsService
{
    private const SEARCH_FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location,places.photos';

    private const DETAILS_FIELDS = 'id,name,displayName,formattedAddress,shortFormattedAddress,location,viewport,types,primaryType,primaryTypeDisplayName,businessStatus,googleMapsUri,websiteUri,nationalPhoneNumber,internationalPhoneNumber,rating,userRatingCount,priceLevel,priceRange,regularOpeningHours,currentOpeningHours,photos,reviews,editorialSummary,plusCode,utcOffsetMinutes,timeZone,parkingOptions,paymentOptions,accessibilityOptions';

    /**
     * @return array<string, mixed>
     */
    public function getLocationDetail(string $placeQuery, ?string $regionCode = 'PK'): array
    {
        $placeId = $this->searchPlaceId($placeQuery, $regionCode);

        return $this->getPlaceDetailsById($placeId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMinimalLocationDetail(string $placeQuery, ?string $regionCode = 'PK'): array
    {
        $place = $this->searchPlace($placeQuery, $regionCode);
        $photoName = data_get($place, 'photos.0.name');
        $formattedAddress = (string) ($place['formattedAddress'] ?? '');

        return [
            'id' => (string) ($place['id'] ?? ''),
            'place' => (string) data_get($place, 'displayName.text', ''),
            'shortAddress' => $this->deriveShortAddress($formattedAddress),
            'image' => is_string($photoName) ? $this->getPlacePhotoUrl($photoName) : null,
            'lat' => (float) data_get($place, 'location.latitude', 0),
            'lng' => (float) data_get($place, 'location.longitude', 0),
        ];
    }

    protected function searchPlaceId(string $placeQuery, ?string $regionCode = 'PK'): string
    {
        return (string) ($this->searchPlace($placeQuery, $regionCode)['id'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchPlace(string $placeQuery, ?string $regionCode = 'PK'): array
    {
        $payload = [
            'textQuery' => $placeQuery,
            'languageCode' => 'en',
        ];

        if (is_string($regionCode) && trim($regionCode) !== '') {
            $payload['regionCode'] = strtoupper(trim($regionCode));
        }

        $response = $this->googleRequest(self::SEARCH_FIELDS)->post('places:searchText', $payload);

        if (! $response->successful()) {
            throw $this->googleException($response, 'Google place search failed.');
        }

        /** @var array<string, mixed>|null $place */
        $place = data_get($response->json(), 'places.0');
        $placeId = $place['id'] ?? null;

        if (! is_string($placeId) || $placeId === '') {
            throw new PublicApiException('No place found from Google Places', 404);
        }

        return $place;
    }

    protected function deriveShortAddress(string $formattedAddress): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $formattedAddress))));

        if ($parts === []) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[count($parts) - 1];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPlaceDetailsById(string $placeId): array
    {
        $response = $this->googleRequest(self::DETAILS_FIELDS)->get("places/{$placeId}");

        if (! $response->successful()) {
            throw $this->googleException($response, 'Google place details request failed.');
        }

        /** @var array<string, mixed> $place */
        $place = $response->json();
        $photos = is_array($place['photos'] ?? null) ? $place['photos'] : [];
        $resolvedPhotos = [];

        foreach (array_slice($photos, 0, 5) as $photo) {
            if (! is_array($photo) || ! is_string($photo['name'] ?? null)) {
                continue;
            }

            $resolvedPhotos[] = [
                'name' => $photo['name'],
                'widthPx' => isset($photo['widthPx']) ? (int) $photo['widthPx'] : null,
                'heightPx' => isset($photo['heightPx']) ? (int) $photo['heightPx'] : null,
                'authorAttributions' => is_array($photo['authorAttributions'] ?? null) ? $photo['authorAttributions'] : [],
                'url' => $this->getPlacePhotoUrl($photo['name']),
            ];
        }

        return [
            'id' => (string) ($place['id'] ?? ''),
            'name' => (string) data_get($place, 'displayName.text', ''),
            'address' => (string) ($place['formattedAddress'] ?? ''),
            'shortAddress' => (string) ($place['shortFormattedAddress'] ?? ''),
            'lat' => (float) data_get($place, 'location.latitude', 0),
            'lng' => (float) data_get($place, 'location.longitude', 0),
            'types' => is_array($place['types'] ?? null) ? $place['types'] : [],
            'primaryType' => (string) ($place['primaryType'] ?? ''),
            'primaryTypeDisplayName' => (string) data_get($place, 'primaryTypeDisplayName.text', ''),
            'businessStatus' => (string) ($place['businessStatus'] ?? ''),
            'googleMapsUri' => (string) ($place['googleMapsUri'] ?? ''),
            'websiteUri' => (string) ($place['websiteUri'] ?? ''),
            'nationalPhoneNumber' => (string) ($place['nationalPhoneNumber'] ?? ''),
            'internationalPhoneNumber' => (string) ($place['internationalPhoneNumber'] ?? ''),
            'rating' => (float) ($place['rating'] ?? 0),
            'userRatingCount' => (int) ($place['userRatingCount'] ?? 0),
            'priceLevel' => (string) ($place['priceLevel'] ?? ''),
            'priceRange' => $place['priceRange'] ?? null,
            'regularOpeningHours' => $place['regularOpeningHours'] ?? null,
            'currentOpeningHours' => $place['currentOpeningHours'] ?? null,
            'editorialSummary' => (string) data_get($place, 'editorialSummary.text', ''),
            'plusCode' => $place['plusCode'] ?? null,
            'utcOffsetMinutes' => isset($place['utcOffsetMinutes']) ? (int) $place['utcOffsetMinutes'] : null,
            'timeZone' => $place['timeZone'] ?? null,
            'parkingOptions' => $place['parkingOptions'] ?? null,
            'paymentOptions' => $place['paymentOptions'] ?? null,
            'accessibilityOptions' => $place['accessibilityOptions'] ?? null,
            'reviews' => is_array($place['reviews'] ?? null) ? $place['reviews'] : [],
            'photos' => array_values(array_filter($resolvedPhotos, fn (array $photo): bool => ! empty($photo['url']))),
            'raw' => $place,
        ];
    }

    protected function getPlacePhotoUrl(string $photoName): ?string
    {
        $response = $this->googleRequest()->get("{$photoName}/media", [
            'maxWidthPx' => 1200,
            'skipHttpRedirect' => 'true',
        ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'photoUri');
    }

    protected function googleRequest(?string $fieldMask = null): PendingRequest
    {
        $apiKey = trim((string) config('services.google_places.api_key'));

        if ($apiKey === '') {
            throw new PublicApiException('GOOGLE_MAPS_API_KEY is missing', 500);
        }

        $headers = [
            'X-Goog-Api-Key' => $apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($fieldMask !== null) {
            $headers['X-Goog-FieldMask'] = $fieldMask;
        }

        return Http::baseUrl((string) config('services.google_places.base_url'))
            ->acceptJson()
            ->timeout(15)
            ->withHeaders($headers);
    }

    protected function googleException(Response $response, string $fallbackMessage): PublicApiException
    {
        $message = (string) data_get($response->json(), 'error.message', $fallbackMessage);
        $status = $response->serverError() ? 502 : 422;

        return new PublicApiException($message, $status);
    }
}
