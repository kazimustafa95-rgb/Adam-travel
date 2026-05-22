<?php

namespace App\Services\PublicApi;

class GooglePlaceMatchValidator
{
    /**
     * @param  array<string, mixed>  $place
     * @param  array<string, mixed>  $googlePlaceDetails
     */
    public function matches(array $place, array $googlePlaceDetails): bool
    {
        $requestedName = $this->normalize((string) ($place['place'] ?? ''));
        $resolvedName = $this->normalize((string) ($googlePlaceDetails['place'] ?? ''));

        if ($requestedName === '' || $resolvedName === '') {
            return false;
        }

        if ($this->hasStrongTypeMismatch(
            (string) ($place['category'] ?? ''),
            (string) ($googlePlaceDetails['primaryType'] ?? ''),
            is_array($googlePlaceDetails['types'] ?? null) ? $googlePlaceDetails['types'] : [],
        )) {
            return false;
        }

        if ($requestedName === $resolvedName) {
            return true;
        }

        if (str_contains($resolvedName, $requestedName) || str_contains($requestedName, $resolvedName)) {
            return true;
        }

        $requestedTokens = $this->tokens($requestedName);
        $resolvedTokens = $this->tokens($resolvedName);

        if ($requestedTokens === [] || $resolvedTokens === []) {
            return false;
        }

        $intersection = array_intersect($requestedTokens, $resolvedTokens);
        $union = array_unique([...$requestedTokens, ...$resolvedTokens]);
        $jaccard = count($union) > 0 ? count($intersection) / count($union) : 0;

        return $jaccard >= 0.6;
    }

    /**
     * @param  list<string>  $types
     */
    protected function hasStrongTypeMismatch(string $requestedCategory, string $primaryType, array $types): bool
    {
        $category = $this->normalize($requestedCategory);
        $resolvedTypes = array_map($this->normalize(...), array_filter([$primaryType, ...$types]));

        if ($resolvedTypes === []) {
            return false;
        }

        $requestedIsHospitality = $this->containsAny($category, ['hotel', 'resort', 'lodging']);
        $requestedIsFood = $this->containsAny($category, ['restaurant', 'cafe', 'food']);
        $requestedIsShopping = $this->containsAny($category, ['mall', 'shop', 'market', 'store']);
        $requestedIsAirport = $this->containsAny($category, ['airport']);

        if (! $requestedIsHospitality && $this->typeListContainsAny($resolvedTypes, ['lodging'])) {
            return true;
        }

        if (! $requestedIsFood && $this->typeListContainsAny($resolvedTypes, ['restaurant', 'cafe', 'food', 'meal'])) {
            return true;
        }

        if (! $requestedIsShopping && $this->typeListContainsAny($resolvedTypes, ['shopping_mall', 'store'])) {
            return true;
        }

        if (! $requestedIsAirport && $this->typeListContainsAny($resolvedTypes, ['airport'])) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function tokens(string $value): array
    {
        $parts = preg_split('/[^a-z0-9]+/', $value) ?: [];

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    protected function normalize(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? '') ?? '';
    }

    protected function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $types
     */
    protected function typeListContainsAny(array $types, array $needles): bool
    {
        foreach ($types as $type) {
            foreach ($needles as $needle) {
                if (str_contains($type, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
