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
                'text' => "Find multiple possible/similar real-world places from this input.\n\nUser Input:\n{$trimmedValue}\n\nInput Type:\n".($isUrl ? 'URL' : 'Text/Search Query')."\n\nPlatform:\n{$metadata['platform']}\n\nTitle:\n{$metadata['title']}\n\nDescription:\n{$metadata['description']}\n\nPage Text:\n{$metadata['pageText']}\n\nTask:\nReturn 5 possible place recommendations.\n\nExample:\nIf input is \"lucky one mall\", return places like:\n- LuckyOne Mall, Karachi\n- Dolmen Mall Clifton, Karachi\n- Ocean Mall, Karachi\n- Millennium Mall, Karachi\n- Atrium Mall, Karachi\n\nRules:\n- First item should be the most likely exact match.\n- If title or description contains a clear hotel, resort, mall, restaurant, landmark, or destination name, use it as the first result.\n- Other items should be similar nearby or same-category places.\n- Prefer exact POI names: mall, restaurant, beach, park, hotel, resort, landmark.\n- Do not return social media headquarters.\n- Do not return \"Unknown\".\n- Do not invent coordinates. Use lat/lng as 0 until Google Places is enabled.\n- Confidence must be a percentage string like \"95%\", \"80%\", or \"45%\".\n- Keep confidence realistic.",
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
                    'content' => 'You are an AI location recommendation assistant.

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
      "lat": number,
      "lng": number,
      "reason": "string"
    }
  ]
}

Important:
1. Return 3 to 5 places.
2. First place must be the most likely exact match from the input, title, or description.
3. Other places should be similar recommendations.
4. Prefer nearby/same-city/same-category places when possible.
5. Confidence must always be a percentage string, for example "95%".
6. If coordinates are not verified from Google Places, set lat and lng to 0.
7. Never return Facebook, Instagram, YouTube, TikTok, Meta, Google, Twitter/X, or any platform headquarters.
8. Never return Unknown.
9. Do not make fake exact coordinates.',
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'temperature' => 0.4,
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
                $this->normalizePlaces($parsedResult['places'] ?? []),
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
            $metadata['title'] = $this->getMetaContent($html, 'og:title')
                ?: $this->getMetaContent($html, 'twitter:title')
                ?: $this->getTitleFromHtml($html);
            $metadata['description'] = $this->getMetaContent($html, 'og:description')
                ?: $this->getMetaContent($html, 'twitter:description')
                ?: $this->getMetaContent($html, 'description');
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
            $metadata['pageText'] = self::METADATA_FALLBACK;
        }

        return $metadata;
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
