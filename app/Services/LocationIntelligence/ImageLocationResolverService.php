<?php

namespace App\Services\LocationIntelligence;

use App\Contracts\LocationIntelligence\LocationResolverContract;
use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImageLocationResolverService implements LocationResolverContract
{
    /**
     * Labels that describe scenes/objects but are NOT place names.
     * Using these as a Places query returns irrelevant results.
     *
     * @var list<string>
     */
    private const GENERIC_LABELS = [
        // Nature / water
        'water', 'body of water', 'water resources', 'watercourse', 'liquid', 'fluid',
        'lake', 'ocean', 'sea', 'river', 'stream', 'pond', 'reservoir', 'waterfall', 'spring',
        'wave', 'tide', 'shore', 'shoreline',
        // Terrain
        'forest', 'tree', 'plant', 'vegetation', 'nature', 'biome', 'jungle', 'woodland', 'grass',
        'landscape', 'sky', 'cloud', 'atmosphere', 'weather', 'sunlight', 'horizon', 'sunset', 'sunrise',
        'mountain', 'hill', 'rock', 'soil', 'land', 'terrain', 'valley', 'canyon', 'plain', 'field',
        // Coastal
        'coast', 'coastal and oceanic landforms', 'channel', 'bay', 'inlet', 'headland', 'cape', 'cliff',
        // Infrastructure (generic)
        'road', 'path', 'trail', 'asphalt', 'pavement', 'infrastructure',
        'architecture', 'building', 'structure', 'facade', 'wall', 'roof',
        // People
        'people', 'person', 'man', 'woman', 'child', 'crowd', 'human', 'face', 'portrait',
        // Objects
        'furniture', 'wood', 'material', 'texture', 'pattern', 'color', 'light',
        'vehicle', 'car', 'bus', 'truck', 'transport', 'automobile', 'boat', 'ship',
        'animal', 'dog', 'cat', 'bird', 'wildlife', 'fish',
        'food', 'dish', 'cuisine', 'meal', 'drink', 'beverage',
        'flower', 'leaf', 'branch', 'garden',
    ];

    /**
     * Labels that strongly suggest a named place type.
     * Only these are eligible to be used as a Places query.
     *
     * @var list<string>
     */
    private const PLACE_LABELS = [
        'tourist attraction', 'landmark', 'monument', 'memorial', 'statue', 'sculpture',
        'temple', 'church', 'mosque', 'cathedral', 'shrine', 'pagoda', 'synagogue', 'chapel',
        'palace', 'castle', 'fort', 'fortress', 'citadel', 'ruins',
        'tower', 'lighthouse', 'minaret',
        'bridge', 'dam', 'aqueduct',
        'stadium', 'arena', 'sports venue', 'olympic venue',
        'museum', 'art museum', 'gallery', 'exhibition',
        'park', 'national park', 'nature reserve', 'botanical garden',
        'mall', 'shopping mall', 'market', 'bazaar', 'plaza',
        'airport', 'train station', 'harbour', 'port', 'marina',
        'beach', 'island', 'peninsula',
        'university', 'school', 'library',
        'hospital', 'clinic',
        'hotel', 'resort', 'inn',
        'zoo', 'aquarium', 'amusement park', 'theme park',
        'waterfall', 'geyser',
        'square', 'piazza', 'promenade', 'boulevard',
    ];

    public function __construct(
        private readonly GoogleVisionAnalyzerService $visionAnalyzer,
        private readonly GooglePlacesResolverService $placesResolver,
    ) {}

    /**
     * Analyze an image URL with Google Vision, then resolve the best place candidate.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $input): array
    {
        // Resolve the actual direct image URL before sending to Vision
        $imageUrl = $this->resolveDirectImageUrl($input);

        $signals       = $this->visionAnalyzer->analyzeImageUrl($imageUrl);
        $resolvedPlace = null;

        [$query, $confidence] = $this->buildQuery($signals);

        if ($query !== '') {
            try {
                $resolvedPlace = $this->placesResolver->resolveByText($query, $confidence);
            } catch (LocationIntelligenceException) {
                // Signals found but Places couldn't resolve — return partial result.
            }
        }

        return [
            'type'           => 'image',
            'resolved_place' => $resolvedPlace,
            'signals'        => [
                'landmarks' => $signals['landmarks'],
                'ocr_text'  => $signals['ocr_text'],
                'labels'    => $signals['labels'],
            ],
        ];
    }

    /**
     * Build the best possible Places query from Vision signals.
     *
     * Priority:
     *   1. Landmark name   — Vision's named geographic entity, most reliable
     *   2. OCR / text      — sign boards, storefront names, building labels
     *   3. null            — generic scene labels alone are never specific enough
     *                        to identify a real place (e.g. "Water", "Tourist attraction",
     *                        "Coast" could match thousands of locations worldwide)
     *
     * @param  array<string, mixed>  $signals
     * @return array{string, int}   [query, confidence]
     */
    private function buildQuery(array $signals): array
    {
        // --- 1. Landmark: Vision explicitly identified a named place ---
        if (! empty($signals['landmarks'])) {
            $name       = (string) ($signals['landmarks'][0]['name'] ?? '');
            $confidence = (int) ($signals['landmarks'][0]['confidence'] ?? 85);

            if ($name !== '') {
                return [$name, $confidence];
            }
        }

        // --- 2. OCR text: visible text on signs, boards, storefronts ---
        foreach ((array) $signals['ocr_text'] as $line) {
            $line = trim((string) $line);

            if (strlen($line) >= 4) {
                return [$line, 75];
            }
        }

        // --- 3. Labels alone are scene descriptors, not location identifiers.
        //        "Tourist attraction" + "Coast" + "Water" tells us NOTHING about
        //        which specific beach or lake this is. Returning null is correct. ---
        return ['', 0];
    }

    // -------------------------------------------------------------------------
    // Direct image URL resolver
    // -------------------------------------------------------------------------

    /**
     * Convert any image-referencing URL into a direct image URL
     * that Google Vision can actually read as image bytes.
     *
     * Handles:
     *  - Wikipedia media viewer  en.wikipedia.org/wiki/X#/media/File:Y.jpg
     *  - Wikipedia file page     en.wikipedia.org/wiki/File:Y.jpg
     *  - Wikimedia Commons page  commons.wikimedia.org/wiki/File:Y.jpg
     *  - Google redirected images, CDN pages, etc.
     *  - Already-direct image URLs (passthrough)
     */
    private function resolveDirectImageUrl(string $url): string
    {
        // --- Wikipedia / Wikimedia: #/media/File:Name.jpg fragment ---
        if ($this->isWikipediaHost($url)) {
            $direct = $this->resolveWikipediaImageUrl($url);

            if ($direct !== null) {
                return $direct;
            }
        }

        // --- Generic: follow redirects and check Content-Type ---
        $resolved = $this->followToDirectImage($url);

        return $resolved ?? $url;
    }

    private function isWikipediaHost(string $url): bool
    {
        $host = Str::lower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return str_contains($host, 'wikipedia.org') || str_contains($host, 'wikimedia.org');
    }

    /**
     * Extract the filename from a Wikipedia/Wikimedia URL and build
     * a direct image URL via the Special:FilePath redirect endpoint.
     *
     * Supports:
     *  - https://en.wikipedia.org/wiki/Landmark#/media/File:Hodges_cape-good-hope.jpg
     *  - https://en.wikipedia.org/wiki/File:Hodges_cape-good-hope.jpg
     *  - https://commons.wikimedia.org/wiki/File:Hodges_cape-good-hope.jpg
     */
    private function resolveWikipediaImageUrl(string $url): ?string
    {
        $filename = null;

        // Fragment pattern: #/media/File:Filename.ext
        $fragment = (string) (parse_url($url, PHP_URL_FRAGMENT) ?? '');

        if (preg_match('/File:(.+)$/i', $fragment, $m) === 1) {
            $filename = urldecode($m[1]);
        }

        // Path pattern: /wiki/File:Filename.ext
        if ($filename === null) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

            if (preg_match('/\/wiki\/File:(.+)$/i', $path, $m) === 1) {
                $filename = urldecode($m[1]);
            }
        }

        if ($filename === null || $filename === '') {
            return null;
        }

        // Use Wikimedia's Special:FilePath — it redirects straight to the image CDN URL
        $apiUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/'.urlencode($filename);

        try {
            // Follow the redirect to get the actual CDN image URL
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; LocationIntelligenceBot/1.0)'])
                ->get($apiUrl);

            if ($response->successful()) {
                // If we landed on an image content-type, this is the direct URL
                $contentType = $response->header('Content-Type') ?? '';

                if (str_starts_with($contentType, 'image/')) {
                    return $response->effectiveUri()?->__toString() ?? $apiUrl;
                }
            }

            // Fallback: use Wikipedia image info API
            return $this->resolveViaWikimediaApi($filename);
        } catch (\Throwable) {
            return $this->resolveViaWikimediaApi($filename);
        }
    }

    /**
     * Use Wikimedia's imageinfo API to get the direct URL for a file.
     */
    private function resolveViaWikimediaApi(string $filename): ?string
    {
        try {
            $response = Http::timeout(10)->get('https://en.wikipedia.org/w/api.php', [
                'action'    => 'query',
                'titles'    => 'File:'.$filename,
                'prop'      => 'imageinfo',
                'iiprop'    => 'url',
                'format'    => 'json',
                'redirects' => 1,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $pages = (array) data_get($response->json(), 'query.pages', []);

            foreach ($pages as $page) {
                $imageUrl = data_get($page, 'imageinfo.0.url');

                if (is_string($imageUrl) && $imageUrl !== '') {
                    return $imageUrl;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * For non-Wikipedia URLs, follow HTTP redirects and check if the
     * final destination is a direct image (based on Content-Type header).
     * Returns null if the URL already is a direct image or cannot be resolved.
     */
    private function followToDirectImage(string $url): ?string
    {
        // If the URL path already has a clear image extension — trust it directly
        $path      = Str::lower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
            return null; // Already direct — no need to follow
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; LocationIntelligenceBot/1.0)'])
                ->head($url);

            $contentType = Str::lower($response->header('Content-Type') ?? '');

            if (str_starts_with($contentType, 'image/')) {
                // The URL is already serving image bytes — use as-is
                return $url;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Label filtering
    // -------------------------------------------------------------------------

    /**
     * Find the first label that looks like a real place type.
     * Rejects all generic scene/object/nature labels.
     *
     * @param  array<int, array<string, mixed>>  $labels
     */
    private function extractPlaceLabel(array $labels): string
    {
        foreach ($labels as $label) {
            $name       = strtolower(trim((string) ($label['name'] ?? '')));
            $confidence = (int) ($label['confidence'] ?? 0);

            // Skip low-confidence labels
            if ($confidence < 75) {
                continue;
            }

            // Skip known generic labels
            if (in_array($name, self::GENERIC_LABELS, true)) {
                continue;
            }

            // Accept if it matches a known place type
            if (in_array($name, self::PLACE_LABELS, true)) {
                return (string) ($label['name'] ?? '');
            }
        }

        return '';
    }
}
