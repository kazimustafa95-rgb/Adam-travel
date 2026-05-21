<?php

namespace App\Services\PublicApi;

use App\Exceptions\PublicApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;

class LocationSuggestionsService
{
    private const METADATA_FALLBACK = 'Metadata could not be fetched. Link may be private, blocked, login-protected, or anti-bot protected.';

    public function __construct(protected GooglePlaceDetailsService $googlePlaceDetailsService) {}

    /**
     * @return array<string, mixed>
     */
    public function getLocations(string $inputValue): array
    {
        $trimmedValue = trim($inputValue);

        if ($trimmedValue === '') {
            throw new PublicApiException('Input value is required', 422, [
                'input' => ['Input value is required.'],
            ]);
        }

        $isUrl = preg_match('/^https?:\/\//i', $trimmedValue) === 1;
        $metadata = $this->fetchLinkMetadata($trimmedValue);
        $userContent = [
            [
                'type' => 'text',
                'text' => "Analyze this content and identify all real-world locations directly supported by the evidence.\n\nUser Input:\n{$trimmedValue}\n\nInput Type:\n".($isUrl ? 'URL' : 'Text/Search Query')."\n\nPlatform:\n{$metadata['platform']}\n\nTitle:\n{$metadata['title']}\n\nDescription:\n{$metadata['description']}\n\nPage Text (extracted):\n{$metadata['pageText']}\n\nTask:\nExtract every location that has direct evidence in the title, description, image, captions, hashtags, or page text. Return only places directly supported by the evidence — not alternatives, not parent cities of an already-identified venue, not invented locations. If no real location can be identified, return an empty places array.",
            ],
        ];

        if ($this->canSendImageToOpenAi((string) $metadata['image'])) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $this->decodeHtmlEntities((string) $metadata['image']),
                ],
            ];
        }

        $response = $this->openAiRequest()->post('chat/completions', [
            'model' => (string) config('services.openai.model', 'gpt-4o'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional AI location analyst. Your job is to identify real-world locations from social media content, URLs, images, text, captions, hashtags, and metadata.

When an image is provided, analyze it thoroughly for location evidence:
- Architecture style (traditional, modern, colonial, vernacular)
- Geographic features (mountains, beaches, deserts, forests, rivers)
- Visible road signs, shop signs, banners, or text in the image
- Language of any visible text or signage
- Vegetation and climate indicators
- Iconic landmarks or recognizable structures
- Cultural symbols, clothing styles, or vehicle types

Return only valid JSON in this exact structure:

{
  "query": "string",
  "places": [
    {
      "place": "string",
      "category": "string",
      "city": "string",
      "country": "string",
      "confidence": "string",
      "lat": 0,
      "lng": 0,
      "reason": "string"
    }
  ]
}

Core rule:
The places array must contain ONLY the real locations directly supported by the input.
Do not force a fixed number of results.
Return 0, 1, or multiple places depending on the evidence.

When to return 1 place:
- The input points to one exact venue, landmark, attraction, hotel, restaurant, mall, beach, park, city, or country.
- The input contains one specific location plus its parent city/town/country — in this case return only the most specific place.

Example:
Input: "The Land of Legends Antalya"
Correct:
[{"place": "The Land of Legends Theme Park", "category": "Theme Park", "city": "Antalya", "country": "Turkey", "confidence": "90%", "lat": 0, "lng": 0, "reason": "Text explicitly mentions The Land of Legends Antalya, a known theme park."}]
WRONG: also returning Antalya, Belek, or Turkey as separate entries.

When to return multiple places:
Return multiple places when any of the following is true:
- Several venue names appear in the caption, title, or hashtags.
- A travel itinerary with different stops is described.
- Multiple landmarks are visible in the image at different locations.
- Text like "Istanbul, Cappadocia and Antalya trip" mentions more than one destination.
- The title explicitly states a count of places (e.g. "3 Most Beautiful Places", "Top 5 Destinations", "7 Hidden Gems").

Example:
Input: "Turkey trip: Istanbul, Cappadocia, Antalya"
Correct: return 3 places — Istanbul, Cappadocia, Antalya.

Example:
Input: "Visited Eiffel Tower and Louvre Museum today"
Correct: return 2 places — Eiffel Tower, Louvre Museum.

Special rule — count-based titles:
When the title or caption explicitly states a number of places (e.g. "3 Most Beautiful Places in China", "Top 5 Places to Visit in Japan") AND the content is about a specific country or region, do the following:
1. First identify every place directly named in the title, hashtags, captions, or image.
2. If the number of identified places is less than the stated count, fill the remaining slots with the most famous, well-known real places that fit the established country/region/theme of the content.
3. Mark directly identified places with confidence 75%–95%.
4. Mark contextually inferred places with confidence 30%–60% and clearly state in the reason that they are inferred from the video theme and geography, not directly named.
5. Never exceed the count stated in the title.

Example:
Title: "3 Most Beautiful Places 🥰 #heavengatechina #china"
- Heaven's Gate is directly confirmed by hashtag → confidence 85%
- 2 more places must be identified → infer top beautiful places in China → e.g. Zhangjiajie National Forest, Li River (Guilin) → confidence 45%

What NOT to do:
- Do not add the parent city or country if a specific venue is already found.
- Do not return social media platform headquarters (Facebook, Instagram, YouTube, TikTok, X/Twitter, Meta, Google).
- Do not return "Unknown".
- Do not exceed the count stated in a count-based title.
- Do not invent fictional places — only suggest real, well-known locations.

Confidence rules:
- Exact venue or landmark directly mentioned: 80% to 95%.
- City or country directly mentioned only: 60% to 85%.
- Weak visual or hashtag-only clue: 20% to 50%.
- If confidence would be below 20% for all candidates, return an empty places array.

Reason rules:
Each reason must cite specific evidence from the input: title text, caption words, hashtags, visible signage, landmark features, metadata, or image details. Never use vague statements.

Coordinates: always set lat and lng to 0.

Final decision process:
1. Extract all location evidence from title, description, captions, hashtags, image, and page text.
2. Check if the title explicitly states a count of places (e.g. "3 Most Beautiful", "Top 5"). If yes, apply the count-based title rule above.
3. If one exact place is identified (no count in title), return only that place.
4. If multiple genuinely separate places are identified, return each one.
4. Remove any generic parent place (city, country) when it only describes an already-returned specific venue.
5. Return an empty places array if no real location is supported by the evidence.',
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            throw $this->openAiException($response, 'OpenAI request failed.');
        }

        $aiContent = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($aiContent) || trim($aiContent) === '') {
            throw new PublicApiException('Empty OpenAI response', 502);
        }

        try {
            /** @var array<string, mixed> $parsedResult */
            $parsedResult = json_decode($aiContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PublicApiException('OpenAI returned invalid JSON', 502, previous: $exception);
        }

        return [
            'query' => (string) ($parsedResult['query'] ?? $trimmedValue),
            'places' => $this->enrichPlacesWithGoogleDetails(
                $this->filterParentPlaces(
                    $this->normalizePlaces($parsedResult['places'] ?? []),
                ),
            ),
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fetchLinkMetadata(string $input): array
    {
        $isUrl = preg_match('/^https?:\/\//i', $input) === 1;
        $platform = $isUrl ? $this->detectPlatform($input) : 'manual_text';
        $metadata = [
            'platform' => $platform,
            'title' => $isUrl ? '' : $input,
            'description' => '',
            'image' => '',
            'pageText' => '',
        ];

        if (! $isUrl) {
            return $metadata;
        }

        // Use oEmbed for platforms that support it — more reliable than HTML scraping
        if (in_array($platform, ['youtube', 'tiktok'], true)) {
            $oembed = $this->fetchOembedData($input, $platform);

            if ($oembed !== []) {
                $metadata['title'] = (string) ($oembed['title'] ?? '');
                $authorName = (string) ($oembed['author_name'] ?? '');

                if ($authorName !== '') {
                    $metadata['description'] = 'By '.$authorName;
                }
            }
        }

        // YouTube: always use direct thumbnail (reliable, no auth required)
        if ($platform === 'youtube') {
            $videoId = $this->getYouTubeVideoId($input);

            if ($videoId !== null) {
                $metadata['image'] = "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
            }
        }

        try {
            $this->guardPublicUrl($input);

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
                ])
                ->get($input);

            if (! $response->successful()) {
                throw new PublicApiException(self::METADATA_FALLBACK, 422);
            }

            $html = (string) $response->body();

            // Only override oEmbed data with HTML data if oEmbed gave us nothing
            if ($metadata['title'] === '') {
                $metadata['title'] = $this->getMetaContent($html, 'og:title')
                    ?: $this->getMetaContent($html, 'twitter:title')
                    ?: $this->getTitleFromHtml($html);
            }

            if ($metadata['description'] === '') {
                $metadata['description'] = $this->getMetaContent($html, 'og:description')
                    ?: $this->getMetaContent($html, 'twitter:description')
                    ?: $this->getMetaContent($html, 'description');
            }

            $metadata['image'] = $metadata['image']
                ?: $this->getMetaContent($html, 'og:image')
                ?: $this->getMetaContent($html, 'twitter:image');

            $metadata['pageText'] = $this->decodeHtmlEntities((string) Str::of($html)
                ->replaceMatches('/<script[\s\S]*?<\/script>/i', ' ')
                ->replaceMatches('/<style[\s\S]*?<\/style>/i', ' ')
                ->replaceMatches('/<[^>]+>/', ' ')
                ->replaceMatches('/\s+/u', ' ')
                ->trim()
                ->limit(2500, '')
                ->value());
        } catch (\Throwable) {
            if ($metadata['pageText'] === '') {
                $metadata['pageText'] = self::METADATA_FALLBACK;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchOembedData(string $url, string $platform): array
    {
        $oembedUrl = match ($platform) {
            'youtube' => 'https://www.youtube.com/oembed?url='.urlencode($url).'&format=json',
            'tiktok' => 'https://www.tiktok.com/oembed?url='.urlencode($url),
            default => null,
        };

        if ($oembedUrl === null) {
            return [];
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get($oembedUrl);

            if ($response->successful()) {
                return (array) ($response->json() ?? []);
            }
        } catch (\Throwable) {
        }

        return [];
    }

    protected function detectPlatform(string $url): string
    {
        $lower = Str::lower($url);

        return match (true) {
            str_contains($lower, 'youtube.com'),
            str_contains($lower, 'youtu.be') => 'youtube',
            str_contains($lower, 'instagram.com') => 'instagram',
            str_contains($lower, 'tiktok.com') => 'tiktok',
            str_contains($lower, 'facebook.com'),
            str_contains($lower, 'fb.watch') => 'facebook',
            str_contains($lower, 'x.com'),
            str_contains($lower, 'twitter.com') => 'twitter',
            default => 'website',
        };
    }

    protected function getYouTubeVideoId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/shorts\/([^?&\/]+)/',
            '/youtube\.com\/watch\?v=([^?&\/]+)/',
            '/youtu\.be\/([^?&\/]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1 && isset($matches[1])) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function getMetaContent(string $html, string $property): string
    {
        $quotedProperty = preg_quote($property, '/');
        $regex = '/<meta[^>]+(?:property|name)=["\']'.$quotedProperty.'["\'][^>]+content=["\']([^"\']+)["\']/i';

        if (preg_match($regex, $html, $matches) === 1) {
            return $this->decodeHtmlEntities($matches[1] ?? '');
        }

        return '';
    }

    protected function getTitleFromHtml(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1) {
            return $this->decodeHtmlEntities(trim(preg_replace('/\s+/u', ' ', $matches[1] ?? '') ?? ''));
        }

        return '';
    }

    protected function canSendImageToOpenAi(string $imageUrl): bool
    {
        if ($imageUrl === '') {
            return false;
        }

        $cleanUrl = Str::lower($this->decodeHtmlEntities($imageUrl));

        if (str_contains($cleanUrl, 'cdninstagram.com')) {
            return false;
        }

        if (str_contains($cleanUrl, 'fbcdn.net')) {
            return false;
        }

        if (str_contains($cleanUrl, 'tiktokcdn')) {
            return false;
        }

        return str_starts_with($cleanUrl, 'http://') || str_starts_with($cleanUrl, 'https://');
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
     * Example: ["The Land of Legends Theme Park" (city=Antalya), "Antalya" (category=City)]
     * → removes "Antalya" because it is only the parent of the theme park.
     *
     * Genuine multi-city itineraries are kept because no entry is a child of another.
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
                strtolower(trim($p['category'] ?? '')),
                $specificCategories,
                true,
            ),
        );

        if (! $hasSpecificPlace) {
            return $places;
        }

        return array_values(array_filter($places, function (array $place) use ($places, $genericCategories, $specificCategories): bool {
            $category = strtolower(trim($place['category'] ?? ''));

            if (! in_array($category, $genericCategories, true)) {
                return true;
            }

            $placeName = strtolower(trim($place['place'] ?? ''));

            foreach ($places as $other) {
                if ($other === $place) {
                    continue;
                }

                $otherCategory = strtolower(trim($other['category'] ?? ''));

                if (! in_array($otherCategory, $specificCategories, true)) {
                    continue;
                }

                $otherCity = strtolower(trim($other['city'] ?? ''));
                $otherCountry = strtolower(trim($other['country'] ?? ''));

                if ($placeName !== '' && ($otherCity === $placeName || $otherCountry === $placeName)) {
                    return false;
                }
            }

            return true;
        }));
    }

    protected function decodeHtmlEntities(string $text = ''): string
    {
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function resolveRegionCode(mixed $country): ?string
    {
        if (! is_string($country)) {
            return null;
        }

        $country = strtoupper(trim($country));

        return strlen($country) === 2 ? $country : null;
    }

    protected function openAiRequest(): PendingRequest
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new PublicApiException('OPENAI_API_KEY missing in server configuration', 500);
        }

        return Http::baseUrl((string) config('services.openai.base_url'))
            ->acceptJson()
            ->timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$apiKey,
            ]);
    }

    protected function openAiException(Response $response, string $fallbackMessage): PublicApiException
    {
        $message = (string) data_get($response->json(), 'error.message', $fallbackMessage);
        $status = $response->serverError() ? 502 : 422;

        return new PublicApiException($message, $status);
    }

    protected function guardPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new PublicApiException('Only http and https URLs are supported.', 422);
        }

        if ($host === '' || in_array($host, ['localhost', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            throw new PublicApiException(self::METADATA_FALLBACK, 422);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($publicIp === false) {
                throw new PublicApiException(self::METADATA_FALLBACK, 422);
            }
        }
    }
}
