<?php

namespace App\Services\LocationIntelligence;

use App\Contracts\LocationIntelligence\LocationResolverContract;
use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VideoLocationResolverService implements LocationResolverContract
{
    public function __construct(
        private readonly GoogleVisionAnalyzerService $visionAnalyzer,
        private readonly GooglePlacesResolverService $placesResolver,
    ) {}

    /**
     * Resolve location from any video URL.
     *
     * All video types use the same API-key-based flow (no Service Account needed):
     *   1. Resolve a thumbnail / preview image URL
     *   2. Run Google Vision API on that image (landmark + OCR + label)
     *   3. Fall back to page metadata (og:title, og:description)
     *   4. Fall back to filename extraction from the URL path
     *   5. Resolve best candidate via Google Places Text Search
     *
     * @return array<string, mixed>
     */
    public function resolve(string $input): array
    {
        $host = Str::lower((string) (parse_url($input, PHP_URL_HOST) ?? ''));

        $thumbnailUrl   = $this->resolveThumbnailUrl($input, $host);
        $pageMeta       = $this->fetchPageMetadata($input);
        $signals        = ['landmarks' => [], 'labels' => [], 'ocr_text' => []];
        $visionCandidate = null;

        // --- Step 1: Vision API on thumbnail ---
        if ($thumbnailUrl !== null) {
            try {
                $visionSignals = $this->visionAnalyzer->analyzeImageUrl($thumbnailUrl);
                $signals = [
                    'landmarks' => $visionSignals['landmarks'],
                    'labels'    => $visionSignals['labels'],
                    'ocr_text'  => $visionSignals['ocr_text'],
                ];
                $visionCandidate = $visionSignals['best_candidate'];
            } catch (LocationIntelligenceException) {
                // Vision failed — continue to next fallback.
            }
        }

        // --- Step 2: Page title fallback ---
        $queryCandidate = $visionCandidate;

        if (($queryCandidate === null || $queryCandidate === '') && $pageMeta['title'] !== '') {
            $queryCandidate = $pageMeta['title'];
        }

        // --- Step 3: Filename extraction fallback ---
        if ($queryCandidate === null || $queryCandidate === '') {
            $queryCandidate = $this->extractQueryFromFilename($input);
        }

        // --- Step 4: Resolve with Google Places ---
        $resolvedPlace = null;

        if ($queryCandidate !== null && $queryCandidate !== '') {
            try {
                $confidence    = $visionCandidate !== null ? 72 : 55;
                $resolvedPlace = $this->placesResolver->resolveByText($queryCandidate, $confidence);
            } catch (LocationIntelligenceException) {
                // Could not resolve — return partial result.
            }
        }

        return [
            'type'           => 'video',
            'resolved_place' => $resolvedPlace,
            'signals'        => $signals,
            'metadata'       => [
                'title'       => $pageMeta['title'],
                'description' => $pageMeta['description'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Thumbnail resolution
    // -------------------------------------------------------------------------

    private function resolveThumbnailUrl(string $url, string $host): ?string
    {
        // YouTube
        if (str_contains($host, 'youtu')) {
            $id = $this->extractYouTubeId($url);

            return $id !== null ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : null;
        }

        // Vimeo — oEmbed API returns thumbnail_url
        if (str_contains($host, 'vimeo.com')) {
            return $this->resolveVimeoThumbnail($url);
        }

        // Dailymotion
        if (str_contains($host, 'dailymotion.com')) {
            return $this->resolveDailymotionThumbnail($url);
        }

        // Direct image file alongside the video (e.g. video.mp4 → video.jpg)
        $imageSibling = $this->resolveImageSiblingUrl($url);

        if ($imageSibling !== null) {
            return $imageSibling;
        }

        return null;
    }

    private function extractYouTubeId(string $url): ?string
    {
        foreach ([
            '/youtube\.com\/shorts\/([^?&\/]+)/',
            '/youtube\.com\/watch\?v=([^?&\/]+)/',
            '/youtu\.be\/([^?&\/]+)/',
        ] as $pattern) {
            if (preg_match($pattern, $url, $m) === 1 && isset($m[1])) {
                return $m[1];
            }
        }

        return null;
    }

    private function resolveVimeoThumbnail(string $url): ?string
    {
        try {
            $response = Http::timeout(8)->get('https://vimeo.com/api/oembed.json', ['url' => $url]);

            if ($response->successful()) {
                $thumb = data_get($response->json(), 'thumbnail_url');

                return is_string($thumb) && $thumb !== '' ? $thumb : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function resolveDailymotionThumbnail(string $url): ?string
    {
        if (preg_match('/dailymotion\.com\/video\/([a-zA-Z0-9]+)/', $url, $m) === 1) {
            return "https://www.dailymotion.com/thumbnail/video/{$m[1]}";
        }

        return null;
    }

    /**
     * For direct video URLs like /videos/lucky-one-mall.mp4,
     * check if a sibling .jpg/.png exists at the same path.
     */
    private function resolveImageSiblingUrl(string $url): ?string
    {
        $path      = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
            return null;
        }

        $base = preg_replace('/\.[^.]+$/', '', $url);

        foreach (['.jpg', '.jpeg', '.png', '.webp'] as $ext) {
            $candidate = $base.$ext;

            try {
                $response = Http::timeout(5)->head($candidate);

                if ($response->successful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Page metadata
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function fetchPageMetadata(string $url): array
    {
        $empty = ['title' => '', 'description' => ''];

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; LocationIntelligenceBot/1.0)'])
                ->get($url);

            if (! $response->successful()) {
                return $empty;
            }

            $contentType = $response->header('Content-Type') ?? '';

            // Binary video stream — skip HTML parsing
            if (str_contains($contentType, 'video/') || str_contains($contentType, 'application/octet-stream')) {
                return $empty;
            }

            $html = $response->body();

            return [
                'title'       => $this->metaContent($html, 'og:title')
                    ?: $this->metaContent($html, 'twitter:title')
                    ?: $this->htmlTitle($html),
                'description' => $this->metaContent($html, 'og:description')
                    ?: $this->metaContent($html, 'twitter:description'),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    // -------------------------------------------------------------------------
    // Filename extraction
    // -------------------------------------------------------------------------

    /**
     * Extract a human-readable place candidate from the video URL filename.
     * e.g. "lucky-one-mall-karachi.mp4" → "lucky one mall karachi"
     */
    private function extractQueryFromFilename(string $url): ?string
    {
        $path      = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $filename  = pathinfo($path, PATHINFO_FILENAME);

        if ($filename === '' || strlen($filename) < 3) {
            return null;
        }

        // Replace separators with spaces and clean up
        $query = preg_replace('/[-_]+/', ' ', $filename) ?? $filename;
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;
        $query = trim($query);

        // Skip generic/useless filenames
        $genericNames = ['video', 'movie', 'clip', 'media', 'file', 'upload', 'output', 'untitled', 'recording'];

        if (in_array(strtolower($query), $genericNames, true) || strlen($query) < 4) {
            return null;
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // HTML helpers
    // -------------------------------------------------------------------------

    private function metaContent(string $html, string $property): string
    {
        $quoted = preg_quote($property, '/');
        $regex  = '/<meta[^>]+(?:property|name)=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']+)["\']/i';

        if (preg_match($regex, $html, $m) === 1) {
            return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    private function htmlTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) === 1) {
            return html_entity_decode(
                trim((string) preg_replace('/\s+/', ' ', $m[1] ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
        }

        return '';
    }
}
