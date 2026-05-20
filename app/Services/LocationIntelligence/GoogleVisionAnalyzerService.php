<?php

namespace App\Services\LocationIntelligence;

use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Support\Facades\Http;

class GoogleVisionAnalyzerService
{
    private const VISION_ENDPOINT = 'https://vision.googleapis.com/v1/images:annotate';

    /**
     * Analyze a publicly accessible image URL using Google Vision API.
     * Runs landmark detection, OCR/text detection, and label detection.
     *
     * @return array<string, mixed>
     */
    public function analyzeImageUrl(string $imageUrl): array
    {
        $apiKey = $this->resolveApiKey();

        $response = Http::acceptJson()
            ->timeout(25)
            ->post(self::VISION_ENDPOINT.'?key='.$apiKey, [
                'requests' => [
                    [
                        'image'    => ['source' => ['imageUri' => $imageUrl]],
                        'features' => [
                            ['type' => 'LANDMARK_DETECTION', 'maxResults' => 5],
                            ['type' => 'TEXT_DETECTION', 'maxResults' => 10],
                            ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new LocationIntelligenceException(
                (string) data_get($response->json(), 'error.message', 'Google Vision analysis failed.'),
                $response->serverError() ? 502 : 422,
            );
        }

        /** @var array<string, mixed> $result */
        $result = (array) data_get($response->json(), 'responses.0', []);

        return $this->extractSignals($result);
    }

    /**
     * @param  array<string, mixed>  $visionResponse
     * @return array<string, mixed>
     */
    private function extractSignals(array $visionResponse): array
    {
        $landmarks     = [];
        $ocrLines      = [];
        $labels        = [];
        $bestCandidate = null;
        $bestScore     = 0.0;

        // --- Landmark annotations (highest-value signal) ---
        foreach ((array) data_get($visionResponse, 'landmarkAnnotations', []) as $lm) {
            $name  = (string) ($lm['description'] ?? '');
            $score = (float) ($lm['score'] ?? 0);

            if ($name === '') {
                continue;
            }

            $landmarks[] = ['name' => $name, 'confidence' => (int) round($score * 100)];

            if ($score > $bestScore) {
                $bestScore     = $score;
                $bestCandidate = $name;
            }
        }

        // --- Full-text / OCR annotation ---
        $fullText = (string) data_get($visionResponse, 'fullTextAnnotation.text', '');

        if ($fullText !== '') {
            $ocrLines = array_values(array_filter(
                array_map('trim', explode("\n", $fullText)),
                static fn (string $line): bool => strlen($line) >= 3,
            ));
        }

        // --- Label annotations (scene context) ---
        foreach ((array) data_get($visionResponse, 'labelAnnotations', []) as $label) {
            $desc = (string) ($label['description'] ?? '');

            if ($desc === '') {
                continue;
            }

            $labels[] = [
                'name'       => $desc,
                'confidence' => (int) round((float) ($label['score'] ?? 0) * 100),
            ];
        }

        return [
            'best_candidate' => $bestCandidate,
            'confidence'     => (int) round($bestScore * 100),
            'landmarks'      => $landmarks,
            'ocr_text'       => $ocrLines,
            'labels'         => $labels,
        ];
    }

    private function resolveApiKey(): string
    {
        $key = trim((string) config('location_intelligence.google.vision_api_key'));

        if ($key === '') {
            throw new LocationIntelligenceException('GOOGLE_VISION_API_KEY is not configured.', 500);
        }

        return $key;
    }
}
