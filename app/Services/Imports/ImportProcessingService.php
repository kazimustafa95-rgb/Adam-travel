<?php

namespace App\Services\Imports;

use App\Enums\ImportStatus;
use App\Models\AiRequest;
use App\Models\Import;
use App\Models\ImportCandidate;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ImportProcessingService
{
    public function process(Import $import): Import
    {
        $import->update([
            'status' => ImportStatus::Processing,
            'error_code' => null,
            'error_message' => null,
        ]);

        $import->candidates()->delete();

        $normalizedText = null;
        $candidateData = null;
        $aiRequest = $this->startAiRequest($import);

        try {
            $sourceText = $this->resolveSourceText($import);
            $normalizedText = $this->normalizeText($sourceText);

            if ($normalizedText === '') {
                throw new RuntimeException('The submitted import did not contain readable content.');
            }

            $candidateData = $this->extractCandidateData($normalizedText, $import);

            if (! $candidateData) {
                $this->completeAiRequest($aiRequest, 'failed', null, 'No location candidate could be extracted.');

                return $this->markFailure(
                    $import,
                    normalizedText: $normalizedText,
                    errorCode: 'no_location_detected',
                    errorMessage: 'No recognizable travel location could be extracted from the provided content.',
                );
            }

            $candidate = $import->candidates()->create(array_merge($candidateData, [
                'candidate_rank' => 1,
                'metadata' => [
                    'processor' => 'local_heuristic',
                    'source_host' => $import->source_host,
                ],
            ]));

            $hasCoordinates = $candidate->latitude !== null && $candidate->longitude !== null;

            $import->update([
                'normalized_text' => $normalizedText,
                'status' => $hasCoordinates ? ImportStatus::AwaitingConfirmation : ImportStatus::ManualReview,
                'confidence_score' => $candidate->confidence_score,
                'processed_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $this->completeAiRequest($aiRequest, 'completed', $candidate->summary, null);

            return $import->fresh(['candidates', 'savedPlaces']);
        } catch (\Throwable $exception) {
            $this->completeAiRequest($aiRequest, 'failed', null, $exception->getMessage());

            return $this->markFailure(
                $import,
                normalizedText: $normalizedText,
                errorCode: 'processing_failed',
                errorMessage: $exception->getMessage(),
            );
        }
    }

    protected function resolveSourceText(Import $import): string
    {
        if ($import->raw_text) {
            return $import->raw_text;
        }

        if (! $import->source_url) {
            return '';
        }

        $this->guardImportUrl($import->source_url);

        $response = Http::timeout(10)
            ->retry(1, 200)
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8',
                'User-Agent' => 'AdamTravelImportBot/1.0',
            ])
            ->get($import->source_url);

        if (! $response->successful()) {
            throw new RuntimeException('The remote link could not be fetched successfully.');
        }

        $body = $response->body();
        $title = $this->extractHtmlTitle($body);

        return trim($title.' '.strip_tags($body));
    }

    protected function normalizeText(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '';

        return trim(html_entity_decode($collapsed, ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractCandidateData(string $text, Import $import): ?array
    {
        $coordinates = $this->extractCoordinates($text, $import->source_url);
        $placeName = $this->extractLabeledValue($text, ['place', 'location', 'name'])
            ?? $this->extractPlaceFromTitle($text)
            ?? $this->extractCapitalizedPlace($text);

        if (! $placeName) {
            return null;
        }

        $city = $this->extractLabeledValue($text, ['city']);
        $region = $this->extractLabeledValue($text, ['region', 'state']);
        $country = $this->extractLabeledValue($text, ['country', 'country code', 'country_code']);
        $category = $this->normalizeCategory(
            $this->extractLabeledValue($text, ['category', 'type']) ?? $this->inferCategory($placeName.' '.$text),
        );

        $existingLocation = $this->matchExistingLocation($placeName, $city, $country);

        if ($existingLocation && ($coordinates['latitude'] === null || $coordinates['longitude'] === null)) {
            $coordinates['latitude'] = $existingLocation->latitude !== null ? (float) $existingLocation->latitude : null;
            $coordinates['longitude'] = $existingLocation->longitude !== null ? (float) $existingLocation->longitude : null;
        }

        if ($existingLocation) {
            $city ??= $existingLocation->city;
            $region ??= $existingLocation->region;
            $country ??= $existingLocation->country_code;
        }

        $confidence = 0.45;
        $confidence += $coordinates['latitude'] !== null && $coordinates['longitude'] !== null ? 0.35 : 0.0;
        $confidence += $city ? 0.10 : 0.0;
        $confidence += $country ? 0.05 : 0.0;
        $confidence += $category ? 0.05 : 0.0;

        return [
            'place_name' => $placeName,
            'category' => $category,
            'city' => $city,
            'region' => $region,
            'country' => $country,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'provider_place_id' => $existingLocation?->provider_place_id,
            'summary' => $this->buildSummary($placeName, $city, $text),
            'confidence_score' => min(0.99, round($confidence, 2)),
        ];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function extractCoordinates(string $text, ?string $sourceUrl = null): array
    {
        $patterns = [
            '/(?:coordinates?|coord|lat(?:itude)?)\s*[:=]?\s*(-?\d{1,2}\.\d+)\s*[, ]+\s*(?:lng|lon|longitude)?\s*[:=]?\s*(-?\d{1,3}\.\d+)/i',
            '/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return [
                    'latitude' => (float) $matches[1],
                    'longitude' => (float) $matches[2],
                ];
            }
        }

        if ($sourceUrl && preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', $sourceUrl, $matches)) {
            return [
                'latitude' => (float) $matches[1],
                'longitude' => (float) $matches[2],
            ];
        }

        return [
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /**
     * @param  list<string>  $labels
     */
    protected function extractLabeledValue(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            $pattern = '/\b'.preg_quote($label, '/').'\b\s*[:=]\s*([^\n\.;\|]+)/iu';

            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B\"'");
            }
        }

        return null;
    }

    protected function extractPlaceFromTitle(string $text): ?string
    {
        $segments = preg_split('/\s[-|]\s/', $text);
        $candidate = trim((string) ($segments[0] ?? ''));

        if ($candidate !== '' && preg_match('/[A-Z][A-Za-z\'&\-\s]{3,}/', $candidate) === 1) {
            return $candidate;
        }

        return null;
    }

    protected function extractCapitalizedPlace(string $text): ?string
    {
        preg_match_all('/\b([A-Z][\pL\'&.-]+(?:\s+[A-Z][\pL\'&.-]+){0,5})\b/u', $text, $matches);
        $blacklist = ['Place', 'Location', 'Name', 'City', 'Country', 'Category', 'Coordinates', 'Latitude', 'Longitude', 'Visit', 'Save'];

        foreach ($matches[1] ?? [] as $match) {
            $trimmed = trim($match);

            if (! in_array($trimmed, $blacklist, true) && Str::length($trimmed) >= 4) {
                return $trimmed;
            }
        }

        return null;
    }

    protected function inferCategory(string $text): string
    {
        $haystack = Str::lower($text);

        return match (true) {
            str_contains($haystack, 'hotel'),
            str_contains($haystack, 'resort'),
            str_contains($haystack, 'inn') => 'hotel',

            str_contains($haystack, 'restaurant'),
            str_contains($haystack, 'cafe'),
            str_contains($haystack, 'bar'),
            str_contains($haystack, 'sushi'),
            str_contains($haystack, 'coffee') => 'restaurant',

            str_contains($haystack, 'transport'),
            str_contains($haystack, 'airport'),
            str_contains($haystack, 'station') => 'transport',

            str_contains($haystack, 'shop'),
            str_contains($haystack, 'market') => 'shopping',

            default => 'activity',
        };
    }

    protected function normalizeCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $normalized = Str::of($category)->trim()->lower()->replace(' ', '_')->value();

        return match ($normalized) {
            'hotel', 'restaurant', 'activity', 'viewpoint', 'transport', 'shopping', 'other' => $normalized,
            default => $this->inferCategory($normalized),
        };
    }

    protected function buildSummary(string $placeName, ?string $city, string $text): string
    {
        $excerpt = Str::of($text)
            ->replaceMatches('/\b(?:place|location|name|city|country|category|coordinates?)\b\s*:\s*/iu', '')
            ->trim()
            ->limit(180, '...')
            ->value();

        return trim($placeName.($city ? ' in '.$city : '').' imported successfully. '.$excerpt);
    }

    protected function matchExistingLocation(string $placeName, ?string $city, ?string $country): ?Location
    {
        return Location::query()
            ->where('is_moderated_hidden', false)
            ->where(function ($query) use ($placeName): void {
                $query->whereRaw('LOWER(name) = ?', [Str::lower($placeName)])
                    ->orWhereRaw('LOWER(slug) = ?', [Str::slug($placeName)]);
            })
            ->when($city, fn ($query) => $query->whereRaw('LOWER(city) = ?', [Str::lower((string) $city)]))
            ->when($country && Str::length((string) $country) <= 3, fn ($query) => $query->whereRaw('LOWER(country_code) = ?', [Str::lower((string) $country)]))
            ->first();
    }

    protected function extractHtmlTitle(string $body): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return '';
    }

    protected function guardImportUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https import URLs are supported.');
        }

        if ($host === '' || in_array($host, ['localhost', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            throw new RuntimeException('Local or private import URLs are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($publicIp === false) {
                throw new RuntimeException('Private network import URLs are not allowed.');
            }
        }
    }

    protected function startAiRequest(Import $import): AiRequest
    {
        return AiRequest::query()->create([
            'user_id' => $import->user_id,
            'context_type' => 'import',
            'context_id' => $import->id,
            'provider' => 'local_heuristic',
            'model' => 'rule-based-extractor',
            'status' => 'pending',
            'request_hash' => sha1(($import->raw_text ?? '').'|'.($import->source_url ?? '').'|'.$import->updated_at?->timestamp),
        ]);
    }

    protected function completeAiRequest(AiRequest $aiRequest, string $status, ?string $responseExcerpt, ?string $errorMessage): void
    {
        $aiRequest->update([
            'status' => $status,
            'response_excerpt' => $responseExcerpt,
            'error_message' => $errorMessage,
        ]);
    }

    protected function markFailure(Import $import, ?string $normalizedText, string $errorCode, string $errorMessage): Import
    {
        $import->update([
            'normalized_text' => $normalizedText,
            'status' => ImportStatus::Failed,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);

        return $import->fresh(['candidates', 'savedPlaces']);
    }
}
