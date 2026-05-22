<?php

namespace App\Services\PublicApi\Evidence;

use App\Exceptions\PublicApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebEvidenceExtractor
{
    public const METADATA_FALLBACK = 'Metadata could not be fetched. Link may be private, blocked, login-protected, or anti-bot protected.';

    public function __construct(protected UrlContentClassifier $urlContentClassifier) {}

    public function extract(string $input): LocationEvidence
    {
        $trimmedInput = trim($input);
        $classification = $this->urlContentClassifier->classify($trimmedInput);

        if ($classification['mediaType'] === 'text') {
            return LocationEvidence::fromManualText($trimmedInput);
        }

        $metadata = [
            'platform' => $classification['platform'],
            'mediaType' => $classification['mediaType'],
            'title' => '',
            'description' => '',
            'image' => '',
            'pageText' => '',
        ];

        $analysisImages = [];
        $oembedUsed = false;
        $metadataFetchSucceeded = false;

        if (in_array($classification['platform'], ['youtube', 'tiktok'], true)) {
            $oembed = $this->fetchOembedData($trimmedInput, $classification['platform']);

            if ($oembed !== []) {
                $oembedUsed = true;
                $metadata['title'] = (string) ($oembed['title'] ?? '');
                $authorName = (string) ($oembed['author_name'] ?? '');

                if ($authorName !== '') {
                    $metadata['description'] = 'By '.$authorName;
                }

                $thumbnailUrl = (string) ($oembed['thumbnail_url'] ?? '');

                if ($thumbnailUrl !== '') {
                    $metadata['image'] = $thumbnailUrl;

                    if ($classification['platform'] === 'tiktok') {
                        $this->appendAnalysisImage($analysisImages, $this->downloadImageAsDataUrl($thumbnailUrl));
                    } else {
                        $this->appendAnalysisImage($analysisImages, $thumbnailUrl);
                    }
                }
            }
        }

        if ($classification['platform'] === 'youtube' && $metadata['image'] === '') {
            $videoId = $this->getYouTubeVideoId($trimmedInput);

            if ($videoId !== null) {
                $metadata['image'] = "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
                $this->appendAnalysisImage($analysisImages, $metadata['image']);
            }
        }

        if ($classification['mediaType'] === 'image') {
            $metadata['image'] = $metadata['image'] !== '' ? $metadata['image'] : $trimmedInput;
            $this->appendAnalysisImage($analysisImages, $trimmedInput);
        }

        try {
            $this->guardPublicUrl($trimmedInput);

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
                ])
                ->get($trimmedInput);

            if (! $response->successful()) {
                throw new PublicApiException(self::METADATA_FALLBACK, 422);
            }

            $metadataFetchSucceeded = true;

            $contentType = strtolower(explode(';', (string) $response->header('Content-Type'))[0]);

            if (str_starts_with($contentType, 'image/')) {
                if ($metadata['image'] === '') {
                    $metadata['image'] = $trimmedInput;
                }

                return (new LocationEvidence(
                    platform: $metadata['platform'],
                    mediaType: $metadata['mediaType'],
                    title: $metadata['title'],
                    description: $metadata['description'],
                    pageText: $metadata['pageText'],
                    metadataImage: $metadata['image'],
                    analysisImages: $analysisImages,
                    rawMetadata: $metadata,
                ))->withAnalysisDebug($this->buildDebugPayload(
                    classification: $classification,
                    oembedUsed: $oembedUsed,
                    metadataFetchSucceeded: $metadataFetchSucceeded,
                    analysisImages: $analysisImages,
                ));
            }

            $html = (string) $response->body();

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

            $htmlImageCandidates = array_values(array_filter([
                $this->getMetaContent($html, 'og:image'),
                $this->getMetaContent($html, 'twitter:image'),
            ]));

            if ($classification['platform'] === 'website') {
                $htmlImageCandidates = [
                    ...$htmlImageCandidates,
                    ...$this->extractHtmlImageCandidates($html, $trimmedInput),
                ];
            }

            if ($metadata['image'] === '' && isset($htmlImageCandidates[0])) {
                $metadata['image'] = $htmlImageCandidates[0];
            }

            foreach (array_slice(array_values(array_unique($htmlImageCandidates)), 0, 6) as $candidate) {
                $this->appendAnalysisImage($analysisImages, $candidate);
            }

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

        return (new LocationEvidence(
            platform: $metadata['platform'],
            mediaType: $metadata['mediaType'],
            title: $metadata['title'],
            description: $metadata['description'],
            pageText: $metadata['pageText'],
            metadataImage: $metadata['image'],
            analysisImages: $analysisImages,
            rawMetadata: $metadata,
        ))->withAnalysisDebug($this->buildDebugPayload(
            classification: $classification,
            oembedUsed: $oembedUsed,
            metadataFetchSucceeded: $metadataFetchSucceeded,
            analysisImages: $analysisImages,
        ));
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

    /**
     * @return list<string>
     */
    protected function extractHtmlImageCandidates(string $html, string $pageUrl): array
    {
        $matches = [];
        $candidates = [];

        preg_match_all('/<img[^>]+(?:src|data-src|data-lazy-src)=["\']([^"\']+)["\']/i', $html, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            $resolved = $this->resolveUrl((string) $candidate, $pageUrl);

            if ($resolved !== null) {
                $candidates[] = $resolved;
            }
        }

        preg_match_all('/<img[^>]+srcset=["\']([^"\']+)["\']/i', $html, $matches);

        foreach ($matches[1] ?? [] as $srcset) {
            $firstCandidate = trim(explode(',', (string) $srcset)[0] ?? '');
            $firstUrl = trim(explode(' ', $firstCandidate)[0] ?? '');
            $resolved = $this->resolveUrl($firstUrl, $pageUrl);

            if ($resolved !== null) {
                $candidates[] = $resolved;
            }
        }

        return array_values(array_unique(array_slice($candidates, 0, 8)));
    }

    protected function decodeHtmlEntities(string $text = ''): string
    {
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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

    protected function downloadImageAsDataUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
                    'Referer' => 'https://www.tiktok.com/',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower(explode(';', (string) $response->header('Content-Type'))[0]);

            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            return 'data:'.$contentType.';base64,'.base64_encode($response->body());
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveUrl(string $candidate, string $pageUrl): ?string
    {
        $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($candidate === '' || str_starts_with($candidate, 'data:') || str_starts_with($candidate, 'javascript:')) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $candidate) === 1) {
            return $candidate;
        }

        $parts = parse_url($pageUrl);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');

        if ($host === '') {
            return null;
        }

        if (str_starts_with($candidate, '//')) {
            return $scheme.':'.$candidate;
        }

        $basePath = (string) ($parts['path'] ?? '/');
        $baseDirectory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';
        $resolvedPath = str_starts_with($candidate, '/')
            ? $candidate
            : $baseDirectory.$candidate;

        return $scheme.'://'.$host.'/'.ltrim($resolvedPath, '/');
    }

    /**
     * @param  list<string>  $images
     */
    protected function appendAnalysisImage(array &$images, ?string $candidate): void
    {
        $value = trim((string) $candidate);

        if ($value === '' || ! $this->canSendImageToOpenAi($value) || in_array($value, $images, true)) {
            return;
        }

        $images[] = $this->decodeHtmlEntities($value);
    }

    protected function canSendImageToOpenAi(string $imageUrl): bool
    {
        if ($imageUrl === '') {
            return false;
        }

        $cleanUrl = Str::lower($this->decodeHtmlEntities($imageUrl));

        if (str_starts_with($cleanUrl, 'data:image/')) {
            return true;
        }

        if ($this->isTrackingOrNonRenderableUrl($cleanUrl)) {
            return false;
        }

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

    protected function isTrackingOrNonRenderableUrl(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
        $query = Str::lower((string) parse_url($url, PHP_URL_QUERY));

        $trackingFragments = [
            '/collect',
            '/monitor/',
            '/analytics',
            '/tracking',
            '/track',
            '/pixel',
            '/event',
            '/log',
        ];

        foreach ($trackingFragments as $fragment) {
            if (($path !== '' && str_contains($path, $fragment)) || ($query !== '' && str_contains($query, trim($fragment, '/')))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{platform: string, mediaType: string}  $classification
     * @param  list<string>  $analysisImages
     * @return array<string, mixed>
     */
    protected function buildDebugPayload(
        array $classification,
        bool $oembedUsed,
        bool $metadataFetchSucceeded,
        array $analysisImages,
    ): array {
        return [
            'platform' => $classification['platform'],
            'media_type' => $classification['mediaType'],
            'oembed_used' => $oembedUsed,
            'metadata_fetch_succeeded' => $metadataFetchSucceeded,
            'analysis_images_collected' => count($analysisImages),
            'metadata_image_available' => $analysisImages !== [],
        ];
    }
}
