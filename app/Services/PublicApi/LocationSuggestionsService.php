<?php

namespace App\Services\PublicApi;

use App\Exceptions\PublicApiException;
use App\Services\PublicApi\Evidence\VideoEvidenceExtractor;
use App\Services\PublicApi\Evidence\WebEvidenceExtractor;

class LocationSuggestionsService
{
    public function __construct(
        protected GooglePlaceDetailsService $googlePlaceDetailsService,
        protected WebEvidenceExtractor $webEvidenceExtractor,
        protected VideoEvidenceExtractor $videoEvidenceExtractor,
        protected OpenAiLocationAnalyzer $openAiLocationAnalyzer,
        protected GooglePlaceMatchValidator $googlePlaceMatchValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getLocations(string $inputValue, array $options = []): array
    {
        $trimmedValue = trim($inputValue);

        if ($trimmedValue === '') {
            throw new PublicApiException('Input value is required', 422, [
                'input' => ['Input value is required.'],
            ]);
        }

        $evidence = $this->webEvidenceExtractor->extract($trimmedValue);

        if ($evidence->isVideo()) {
            $evidence = $this->videoEvidenceExtractor->enrich($trimmedValue, $evidence);
        }

        $analysis = $this->openAiLocationAnalyzer->analyze($evidence, $trimmedValue);
        $parsedResult = is_array($analysis['result'] ?? null) ? $analysis['result'] : [];
        $analysisDebug = is_array($analysis['debug'] ?? null) ? $analysis['debug'] : [];

        $response = [
            'query' => (string) ($parsedResult['query'] ?? $trimmedValue),
            'places' => $this->enrichPlacesWithGoogleDetails(
                $this->filterParentPlaces(
                    $this->normalizePlaces($parsedResult['places'] ?? []),
                ),
            ),
            'metadata' => $evidence->toResponseMetadata(),
        ];

        if ((bool) config('location_suggestions.debug.enabled', false)) {
            $response['analysis_debug'] = array_replace_recursive(
                [
                    'mode' => (string) ($options['mode'] ?? 'sync'),
                    'used_async' => ($options['mode'] ?? 'sync') === 'async',
                    'platform' => $evidence->platform,
                    'media_type' => $evidence->mediaType,
                    'analysis_images_available' => count($evidence->analysisImages),
                ],
                $evidence->analysisDebug,
                $analysisDebug,
            );
        }

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $places
     * @return list<array<string, mixed>>
     */
    protected function enrichPlacesWithGoogleDetails(array $places): array
    {
        return array_map(fn (array $place): array => $this->enrichPlaceWithGoogleDetails($place), $places);
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    protected function enrichPlaceWithGoogleDetails(array $place): array
    {
        $lookupQuery = collect([
            $place['place'] ?? '',
            $place['city'] ?? '',
            $place['country'] ?? '',
        ])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->implode(', ');

        if ($lookupQuery === '') {
            $place['google_place_details'] = null;

            return $place;
        }

        try {
            $googlePlaceDetails = $this->googlePlaceDetailsService->getMinimalLocationDetail(
                placeQuery: $lookupQuery,
                regionCode: $this->resolveRegionCode($place['country'] ?? null),
            );

            if (! $this->googlePlaceMatchValidator->matches($place, $googlePlaceDetails)) {
                $place['google_place_details'] = null;

                return $place;
            }

            $place['lat'] = (float) ($googlePlaceDetails['lat'] ?? $place['lat'] ?? 0);
            $place['lng'] = (float) ($googlePlaceDetails['lng'] ?? $place['lng'] ?? 0);
            $place['google_place_details'] = [
                'id' => (string) ($googlePlaceDetails['id'] ?? ''),
                'place' => (string) ($googlePlaceDetails['place'] ?? ''),
                'shortAddress' => (string) ($googlePlaceDetails['shortAddress'] ?? ''),
                'image' => $googlePlaceDetails['image'] ?? null,
            ];
        } catch (PublicApiException) {
            $place['google_place_details'] = null;
        }

        return $place;
    }

    /**
     * @return list<array<string, string|float|int>>
     */
    protected function normalizePlaces(mixed $places): array
    {
        if (! is_array($places)) {
            return [];
        }

        return array_map(function (mixed $item): array {
            $place = is_array($item) ? $item : [];
            $confidence = (string) ($place['confidence'] ?? '0%');

            return [
                'place' => (string) ($place['place'] ?? ''),
                'category' => (string) ($place['category'] ?? ''),
                'city' => (string) ($place['city'] ?? ''),
                'country' => (string) ($place['country'] ?? ''),
                'confidence' => str_contains($confidence, '%') ? $confidence : $confidence.'%',
                'lat' => (float) ($place['lat'] ?? 0),
                'lng' => (float) ($place['lng'] ?? 0),
                'reason' => (string) ($place['reason'] ?? ''),
            ];
        }, $places);
    }

    /**
     * Remove generic parent places (city, country, region) when a more specific
     * venue at that same city/country is already in the list.
     *
     * @param  list<array<string, mixed>>  $places
     * @return list<array<string, mixed>>
     */
    protected function filterParentPlaces(array $places): array
    {
        if (count($places) <= 1) {
            return $places;
        }

        $genericCategories = [
            'city', 'town', 'country', 'region', 'province',
            'state', 'district', 'area',
        ];

        $specificCategories = [
            'theme park', 'landmark', 'hotel', 'restaurant', 'museum',
            'mall', 'beach', 'resort', 'attraction', 'park', 'cafe',
            'monument', 'airport', 'valley', 'lake', 'waterfall',
            'fort', 'castle', 'temple', 'mosque', 'church',
        ];

        $hasSpecificPlace = collect($places)->contains(
            fn (array $p): bool => in_array(
                strtolower(trim((string) ($p['category'] ?? ''))),
                $specificCategories,
                true,
            ),
        );

        if (! $hasSpecificPlace) {
            return $places;
        }

        return array_values(array_filter($places, function (array $place) use ($places, $genericCategories, $specificCategories): bool {
            $category = strtolower(trim((string) ($place['category'] ?? '')));

            if (! in_array($category, $genericCategories, true)) {
                return true;
            }

            $placeName = strtolower(trim((string) ($place['place'] ?? '')));

            foreach ($places as $other) {
                if ($other === $place) {
                    continue;
                }

                $otherCategory = strtolower(trim((string) ($other['category'] ?? '')));

                if (! in_array($otherCategory, $specificCategories, true)) {
                    continue;
                }

                $otherCity = strtolower(trim((string) ($other['city'] ?? '')));
                $otherCountry = strtolower(trim((string) ($other['country'] ?? '')));

                if ($placeName !== '' && ($otherCity === $placeName || $otherCountry === $placeName)) {
                    return false;
                }
            }

            return true;
        }));
    }

    protected function resolveRegionCode(mixed $country): ?string
    {
        if (! is_string($country)) {
            return null;
        }

        $country = strtoupper(trim($country));

        return strlen($country) === 2 ? $country : null;
    }
}
