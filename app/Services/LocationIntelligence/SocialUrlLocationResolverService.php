<?php

namespace App\Services\LocationIntelligence;

use App\Contracts\LocationIntelligence\LocationResolverContract;
use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialUrlLocationResolverService implements LocationResolverContract
{
    public function __construct(
        private readonly GoogleVisionAnalyzerService $visionAnalyzer,
        private readonly GooglePlacesResolverService $placesResolver,
    ) {}

    /**
     * Scrape Open Graph / Twitter Card metadata from a social or blog URL,
     * optionally run Vision API on the og:image, then resolve with Google Places.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $input): array
    {
        $metadata      = $this->scrapeMetadata($input);
        $signals       = ['landmarks' => [], 'ocr_text' => [], 'labels' => []];
        $visionCandidate = null;

        // Run Vision analysis on og:image when available
        if ($metadata['image'] !== '') {
            try {
                $visionSignals = $this->visionAnalyzer->analyzeImageUrl($metadata['image']);
                $signals       = [
                    'landmarks' => $visionSignals['landmarks'],
                    'ocr_text'  => $visionSignals['ocr_text'],
                    'labels'    => $visionSignals['labels'],
                ];
                $visionCandidate = $visionSignals['best_candidate'];
            } catch (LocationIntelligenceException) {
                // Vision failed — fall through to text-metadata candidate.
            }
        }

        $query = $visionCandidate ?? $this->extractCandidateFromMetadata($metadata) ?? '';

        $resolvedPlace = null;

        if ($query !== '') {
            try {
                $confidence    = $visionCandidate !== null ? 75 : 65;
                $resolvedPlace = $this->placesResolver->resolveByText($query, $confidence);
            } catch (LocationIntelligenceException) {
                // Partial result — signals present but Places could not resolve.
            }
        }

        return [
            'type'           => 'social',
            'resolved_place' => $resolvedPlace,
            'signals'        => $signals,
            'metadata'       => [
                'platform'    => $metadata['platform'],
                'title'       => $metadata['title'],
                'description' => $metadata['description'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Metadata scraping
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function scrapeMetadata(string $url): array
    {
        $platform = $this->detectPlatform($url);
        $empty    = [
            'platform'    => $platform,
            'title'       => '',
            'description' => '',
            'image'       => '',
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
                ])
                ->get($url);

            if (! $response->successful()) {
                return $empty;
            }

            $html = $response->body();

            return [
                'platform'    => $platform,
                'title'       => $this->metaContent($html, 'og:title')
                    ?: $this->metaContent($html, 'twitter:title')
                    ?: $this->htmlTitle($html),
                'description' => $this->metaContent($html, 'og:description')
                    ?: $this->metaContent($html, 'twitter:description')
                    ?: $this->metaContent($html, 'description'),
                'image'       => $this->metaContent($html, 'og:image')
                    ?: $this->metaContent($html, 'twitter:image'),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    // -------------------------------------------------------------------------
    // Candidate extraction from text metadata
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $metadata
     */
    private function extractCandidateFromMetadata(array $metadata): ?string
    {
        if ($metadata['title'] !== '') {
            return $metadata['title'];
        }

        if ($metadata['description'] !== '') {
            $firstSentence = trim(explode('.', $metadata['description'])[0] ?? '');

            if (strlen($firstSentence) >= 4) {
                return $firstSentence;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function detectPlatform(string $url): string
    {
        $lower = Str::lower($url);

        return match (true) {
            str_contains($lower, 'instagram.com')  => 'instagram',
            str_contains($lower, 'facebook.com'),
            str_contains($lower, 'fb.watch')        => 'facebook',
            str_contains($lower, 'tiktok.com')      => 'tiktok',
            str_contains($lower, 'twitter.com'),
            str_contains($lower, 'x.com')           => 'twitter',
            str_contains($lower, 'linkedin.com')    => 'linkedin',
            str_contains($lower, 'pinterest.com')   => 'pinterest',
            str_contains($lower, 'reddit.com')      => 'reddit',
            str_contains($lower, 'medium.com')      => 'medium',
            default                                 => 'website',
        };
    }

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
