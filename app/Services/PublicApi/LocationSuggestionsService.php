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
                    $this->deduplicatePlaces(
                        $this->normalizePlaces($parsedResult['places'] ?? []),
                    ),
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
        $enrichedPlaces = array_map(fn (array $place): array => $this->enrichPlaceWithGoogleDetails($place), $places);

        return $this->deduplicateEnrichedPlaces($enrichedPlaces);
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    protected function enrichPlaceWithGoogleDetails(array $place): array
    {
        $lookupQueries = $this->buildLookupQueries($place);

        if ($lookupQueries === []) {
            $place['google_place_details'] = null;

            return $place;
        }

        try {
            $googlePlaceDetails = null;

            foreach ($lookupQueries as $lookupQuery) {
                try {
                    $googlePlaceDetailsCandidates = $this->googlePlaceDetailsService->searchMinimalLocationDetails(
                        placeQuery: $lookupQuery,
                        regionCode: $this->resolveRegionCode($place['country'] ?? null),
                    );
                } catch (PublicApiException) {
                    continue;
                }

                $googlePlaceDetails = collect($googlePlaceDetailsCandidates)
                    ->first(fn (array $candidate): bool => $this->googlePlaceMatchValidator->matches($place, $candidate));

                if (is_array($googlePlaceDetails)) {
                    break;
                }
            }

            if (! is_array($googlePlaceDetails)) {
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
     * @param  list<array<string, mixed>>  $places
     * @return list<array<string, mixed>>
     */
    protected function deduplicatePlaces(array $places): array
    {
        $deduplicated = [];

        foreach ($places as $place) {
            $key = $this->deduplicationKey($place);

            if ($key === '') {
                $deduplicated[] = $place;

                continue;
            }

            if (! isset($deduplicated[$key])) {
                $deduplicated[$key] = $place;

                continue;
            }

            $deduplicated[$key] = $this->mergeDuplicatePlaces($deduplicated[$key], $place);
        }

        return array_values($deduplicated);
    }

    /**
     * @param  array<string, mixed>  $place
     * @return list<string>
     */
    protected function buildLookupQueries(array $place): array
    {
        $placeName = trim((string) ($place['place'] ?? ''));
        $city = trim((string) ($place['city'] ?? ''));
        $country = trim((string) ($place['country'] ?? ''));

        $queries = [];

        foreach ($this->buildLookupNames($placeName) as $lookupName) {
            $queries[] = $this->implodeQuerySegments([$lookupName, $city, $country]);
            $queries[] = $this->implodeQuerySegments([$lookupName, $country]);
            $queries[] = $this->implodeQuerySegments([$lookupName, $city]);
            $queries[] = $lookupName;
        }

        return array_values(array_unique(array_filter($queries, fn (string $query): bool => $query !== '')));
    }

    /**
     * @return list<string>
     */
    protected function buildLookupNames(string $placeName): array
    {
        $lookupNames = [];

        foreach ([$placeName, ...$this->googlePlaceMatchValidator->aliasesFor($placeName)] as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            $key = $this->normalizePlaceName($candidate);

            if ($key === '' || isset($lookupNames[$key])) {
                continue;
            }

            $lookupNames[$key] = $candidate;
        }

        return array_values($lookupNames);
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

    /**
     * @param  array<string, mixed>  $place
     */
    protected function deduplicationKey(array $place): string
    {
        $segments = [
            $this->normalizePlaceName((string) ($place['place'] ?? '')),
            $this->normalizeBasicText((string) ($place['category'] ?? '')),
            $this->normalizeBasicText((string) ($place['city'] ?? '')),
            $this->normalizeBasicText((string) ($place['country'] ?? '')),
        ];

        if ($segments[0] === '') {
            return '';
        }

        return implode('|', $segments);
    }

    /**
     * @param  array<string, mixed>  $existingPlace
     * @param  array<string, mixed>  $incomingPlace
     * @return array<string, mixed>
     */
    protected function mergeDuplicatePlaces(array $existingPlace, array $incomingPlace): array
    {
        $existingConfidence = $this->confidenceScore($existingPlace['confidence'] ?? null);
        $incomingConfidence = $this->confidenceScore($incomingPlace['confidence'] ?? null);

        $preferredPlace = $existingPlace;
        $secondaryPlace = $incomingPlace;

        if ($incomingConfidence > $existingConfidence) {
            $preferredPlace = $incomingPlace;
            $secondaryPlace = $existingPlace;
        } elseif ($incomingConfidence === $existingConfidence && strlen((string) ($incomingPlace['place'] ?? '')) > strlen((string) ($existingPlace['place'] ?? ''))) {
            $preferredPlace = $incomingPlace;
            $secondaryPlace = $existingPlace;
        }

        if (($preferredPlace['reason'] ?? '') === '' && ($secondaryPlace['reason'] ?? '') !== '') {
            $preferredPlace['reason'] = $secondaryPlace['reason'];
        }

        $canonicalPlaceName = $this->preferredCanonicalPlaceName(
            (string) ($existingPlace['place'] ?? ''),
            (string) ($incomingPlace['place'] ?? ''),
        );

        if ($canonicalPlaceName !== '') {
            $preferredPlace['place'] = $canonicalPlaceName;
        }

        return $preferredPlace;
    }

    /**
     * @param  list<string>  $segments
     */
    protected function implodeQuerySegments(array $segments): string
    {
        return collect($segments)
            ->map(fn (string $segment): string => trim($segment))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->implode(', ');
    }

    protected function preferredCanonicalPlaceName(string $first, string $second): string
    {
        $first = trim($first);
        $second = trim($second);

        if ($first === '') {
            return $second;
        }

        if ($second === '') {
            return $first;
        }

        $firstNormalized = $this->normalizePlaceName($first);
        $secondNormalized = $this->normalizePlaceName($second);

        if ($firstNormalized !== '' && $firstNormalized === $secondNormalized) {
            return strlen($second) > strlen($first) ? $second : $first;
        }

        return strlen($second) > strlen($first) ? $second : $first;
    }

    protected function normalizePlaceName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace("/'s\b/", '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    protected function normalizeBasicText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    protected function confidenceScore(mixed $confidence): int
    {
        if (! is_string($confidence) && ! is_numeric($confidence)) {
            return 0;
        }

        if (preg_match('/(\d{1,3})/', (string) $confidence, $matches) !== 1 || ! isset($matches[1])) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * @param  list<array<string, mixed>>  $places
     * @return list<array<string, mixed>>
     */
    protected function deduplicateEnrichedPlaces(array $places): array
    {
        $deduplicated = [];

        foreach ($places as $place) {
            $key = $this->enrichedDeduplicationKey($place);

            if ($key === '') {
                $deduplicated[] = $place;

                continue;
            }

            if (! isset($deduplicated[$key])) {
                $deduplicated[$key] = $place;

                continue;
            }

            $deduplicated[$key] = $this->mergeEnrichedPlaces($deduplicated[$key], $place);
        }

        return array_values($deduplicated);
    }

    /**
     * @param  array<string, mixed>  $place
     */
    protected function enrichedDeduplicationKey(array $place): string
    {
        $googlePlaceId = trim((string) data_get($place, 'google_place_details.id', ''));

        if ($googlePlaceId !== '') {
            return 'google:'.$googlePlaceId;
        }

        return $this->deduplicationKey($place);
    }

    /**
     * @param  array<string, mixed>  $existingPlace
     * @param  array<string, mixed>  $incomingPlace
     * @return array<string, mixed>
     */
    protected function mergeEnrichedPlaces(array $existingPlace, array $incomingPlace): array
    {
        $preferredDisplayName = $this->preferredEnrichedPlaceName($existingPlace, $incomingPlace);
        $mergedPlace = $this->mergeDuplicatePlaces($existingPlace, $incomingPlace);

        if ($preferredDisplayName !== '') {
            $mergedPlace['place'] = $preferredDisplayName;
        }

        if ((float) ($mergedPlace['lat'] ?? 0) === 0.0) {
            $mergedPlace['lat'] = (float) (($existingPlace['lat'] ?? 0) ?: ($incomingPlace['lat'] ?? 0));
        }

        if ((float) ($mergedPlace['lng'] ?? 0) === 0.0) {
            $mergedPlace['lng'] = (float) (($existingPlace['lng'] ?? 0) ?: ($incomingPlace['lng'] ?? 0));
        }

        if (! is_array($mergedPlace['google_place_details'] ?? null)) {
            $mergedPlace['google_place_details'] = is_array($existingPlace['google_place_details'] ?? null)
                ? $existingPlace['google_place_details']
                : (is_array($incomingPlace['google_place_details'] ?? null) ? $incomingPlace['google_place_details'] : null);
        }

        return $mergedPlace;
    }

    /**
     * @param  array<string, mixed>  $existingPlace
     * @param  array<string, mixed>  $incomingPlace
     */
    protected function preferredEnrichedPlaceName(array $existingPlace, array $incomingPlace): string
    {
        $existingConfidence = $this->confidenceScore($existingPlace['confidence'] ?? null);
        $incomingConfidence = $this->confidenceScore($incomingPlace['confidence'] ?? null);

        if ($incomingConfidence > $existingConfidence) {
            return trim((string) ($incomingPlace['place'] ?? ''));
        }

        if ($incomingConfidence < $existingConfidence) {
            return trim((string) ($existingPlace['place'] ?? ''));
        }

        $existingPlaceName = trim((string) ($existingPlace['place'] ?? ''));
        $incomingPlaceName = trim((string) ($incomingPlace['place'] ?? ''));

        if ($existingPlaceName === '') {
            return $incomingPlaceName;
        }

        if ($incomingPlaceName === '') {
            return $existingPlaceName;
        }

        return strlen($incomingPlaceName) > strlen($existingPlaceName)
            ? $incomingPlaceName
            : $existingPlaceName;
    }
}
