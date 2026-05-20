<?php

namespace App\Services\LocationIntelligence;

use App\Contracts\LocationIntelligence\LocationResolverContract;
use App\Exceptions\LocationIntelligence\LocationIntelligenceException;

class TextLocationResolverService implements LocationResolverContract
{
    private const SHORT_TEXT_THRESHOLD = 80;  // chars — treat as a direct place query
    private const MAX_PLACES           = 15;  // max candidates to resolve

    /**
     * Non-place proper nouns / sentence starters that look capitalised
     * but are NOT place names.
     *
     * @var list<string>
     */
    private const SKIP_WORDS = [
        'Nestled', 'Surrounded', 'The', 'This', 'These', 'Those', 'There',
        'Throughout', 'Along', 'With', 'Where', 'When', 'And', 'But', 'Or',
        'Its', 'Their', 'Our', 'Your', 'His', 'Her', 'Many', 'Most', 'Some',
        'All', 'Each', 'Every', 'Such', 'Like', 'Also', 'Both', 'Just',
        'From', 'Into', 'Over', 'Under', 'Between', 'Among', 'Through',
        'Here', 'More', 'Less', 'Much', 'Very', 'Only', 'Even', 'Still',
        'Beautiful', 'Breathtaking', 'Famous', 'Peaceful', 'Scenic',
        'Historic', 'Natural', 'Cultural', 'Amazing', 'Stunning', 'Lush',
        'Snow', 'Green', 'Blue', 'White', 'Old', 'New', 'Great', 'Grand',
        'Highway', 'Route', 'Valley', 'River', 'Lake', 'Mountain', 'Hill',
        'Fort', 'Pass', 'View', 'Point', 'Bridge', 'Road', 'Peak', 'Nest',
        'Travelers', 'Visitors', 'Tourists', 'People', 'Destination',
        'Journey', 'Adventure', 'Nature', 'Culture', 'Views', 'Year',
    ];

    public function __construct(
        private readonly GooglePlacesResolverService $placesResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $input): array
    {
        if (strlen($input) <= self::SHORT_TEXT_THRESHOLD) {
            return $this->resolveSingle($input);
        }

        return $this->resolveMultiple($input);
    }

    // -------------------------------------------------------------------------
    // Single place — short direct query like "Lucky One Mall Karachi"
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function resolveSingle(string $input): array
    {
        $resolvedPlace = $this->placesResolver->resolveByText($input, 90);

        return [
            'type'             => 'text',
            'resolved_place'   => $resolvedPlace,
            'resolved_places'  => [$resolvedPlace],
            'signals'          => ['query' => $input],
        ];
    }

    // -------------------------------------------------------------------------
    // Multiple places — long descriptive text
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function resolveMultiple(string $input): array
    {
        $candidates     = $this->extractPlaceCandidates($input);
        $resolvedPlaces = [];

        foreach (array_slice($candidates, 0, self::MAX_PLACES) as $candidate) {
            try {
                $resolvedPlaces[] = $this->placesResolver->resolveByText($candidate, 85);
            } catch (LocationIntelligenceException) {
                // Skip candidates Places API could not resolve.
            }
        }

        return [
            'type'             => 'text',
            'resolved_place'   => $resolvedPlaces[0] ?? null,
            'resolved_places'  => $resolvedPlaces,
            'signals'          => [
                'query'             => $input,
                'extracted_places'  => $candidates,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Place name extraction
    // -------------------------------------------------------------------------

    /**
     * Extract all place name candidates from free-form text.
     * Two strategies, applied in order:
     *
     * Strategy A — Explicit list pattern
     *   Finds "places like X, Y, Z" / "including X, Y and Z" etc.
     *   Very precise when the text contains an enumeration.
     *
     * Strategy B — Capitalised proper-noun phrases
     *   Scans for 1-4 consecutive Title-Case words throughout the text.
     *   Used when no explicit list is found.
     *
     * @return list<string>
     */
    private function extractPlaceCandidates(string $text): array
    {
        // --- Strategy A: explicit enumeration ---
        $listCandidates = $this->extractFromExplicitList($text);

        if (count($listCandidates) >= 2) {
            return $listCandidates;
        }

        // --- Strategy B: proper-noun scan ---
        return $this->extractFromProperNouns($text);
    }

    /**
     * @return list<string>
     */
    private function extractFromExplicitList(string $text): array
    {
        $patterns = [
            // "places like Gilgit, Karimabad, and Khunjerab Pass"
            '/(?:places?|locations?|destinations?|spots?|sites?|areas?|towns?|cities?)\s+(?:like|such as|including|e\.g\.)[,:]?\s+(.+?)(?:\.|$)/i',
            // "like Gilgit, Rakaposhi, ..."  (no noun before like)
            '/\blike\s+([A-Z][^.]+?)(?:\.|$)/m',
            // "including X, Y and Z"
            '/\b(?:including|includes?)\s+([A-Z][^.]+?)(?:\.|$)/i',
            // "visit X, Y, and Z"
            '/\b(?:visit|explore|see|experience|discover)\s+([A-Z][^.]+?)(?:\.|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $items = $this->splitList((string) $matches[1]);

                if (count($items) >= 2) {
                    return $items;
                }
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractFromProperNouns(string $text): array
    {
        // Match 1–4 consecutive Title-Case words (handles "Khunjerab Pass", "Eagle's Nest", etc.)
        preg_match_all(
            "/\b([A-Z][a-zA-Z']+(?:\s+[A-Z][a-zA-Z']+){0,3})\b/",
            $text,
            $matches,
        );

        $candidates = [];

        foreach ($matches[1] as $match) {
            $match = trim($match);

            // Skip single generic words
            if (in_array($match, self::SKIP_WORDS, true)) {
                continue;
            }

            // Must be at least 3 chars and not all-caps abbreviation
            if (strlen($match) < 3 || strtoupper($match) === $match) {
                continue;
            }

            $candidates[] = $match;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Split a comma/and-separated list string into individual place names.
     *
     * @return list<string>
     */
    private function splitList(string $listText): array
    {
        // Split on commas (with optional "and" before the last item)
        $parts = preg_split('/,\s*(?:and\s+)?|\s+and\s+/', $listText) ?? [];

        $results = [];

        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r.,;\"'");

            if (strlen($part) >= 2 && strlen($part) <= 80) {
                $results[] = $part;
            }
        }

        return array_values(array_filter($results));
    }
}
