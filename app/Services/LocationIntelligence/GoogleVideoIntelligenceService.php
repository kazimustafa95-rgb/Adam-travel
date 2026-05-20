<?php

namespace App\Services\LocationIntelligence;

use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Support\Facades\Http;

class GoogleVideoIntelligenceService
{
    private const ANNOTATE_URL  = 'https://videointelligence.googleapis.com/v1/videos:annotate';
    private const OPERATIONS_URL = 'https://videointelligence.googleapis.com/v1/operations/';

    public function __construct(
        private readonly GoogleServiceAccountTokenService $tokenService,
    ) {}

    /**
     * Download a public video URL and analyze it with Google Video Intelligence API.
     *
     * @return array<string, mixed>
     */
    public function analyzeVideoUrl(string $videoUrl): array
    {
        $base64Content = $this->downloadAndEncode($videoUrl);

        return $this->submitAndPoll($base64Content);
    }

    // -------------------------------------------------------------------------
    // Submit + poll long-running operation
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function submitAndPoll(string $base64Content): array
    {
        $token = $this->tokenService->getAccessToken();

        $response = Http::acceptJson()
            ->timeout(30)
            ->withToken($token)
            ->post(self::ANNOTATE_URL, [
                'inputContent' => $base64Content,
                'features'     => ['LABEL_DETECTION', 'TEXT_DETECTION', 'SHOT_CHANGE_DETECTION'],
                'videoContext' => [
                    'labelDetectionConfig' => [
                        'labelDetectionMode'       => 'SHOT_AND_FRAME_MODE',
                        'frameConfidenceThreshold' => 0.4,
                        'videoConfidenceThreshold' => 0.4,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new LocationIntelligenceException(
                (string) data_get($response->json(), 'error.message', 'Video Intelligence API request failed.'),
                $response->serverError() ? 502 : 422,
            );
        }

        $operationName = (string) data_get($response->json(), 'name', '');

        if ($operationName === '') {
            throw new LocationIntelligenceException(
                'Video Intelligence API did not return an operation name.',
                502,
            );
        }

        return $this->pollOperation($operationName, $token);
    }

    /**
     * Poll until the operation completes or the timeout is reached.
     *
     * @return array<string, mixed>
     */
    private function pollOperation(string $operationName, string $token): array
    {
        $pollTimeout  = (int) config('location_intelligence.video.poll_timeout', 60);
        $pollInterval = (int) config('location_intelligence.video.poll_interval', 5);
        $elapsed      = 0;

        while ($elapsed < $pollTimeout) {
            sleep($pollInterval);
            $elapsed += $pollInterval;

            $response = Http::acceptJson()
                ->timeout(15)
                ->withToken($token)
                ->get(self::OPERATIONS_URL.rawurlencode($operationName));

            if (! $response->successful()) {
                continue;
            }

            /** @var array<string, mixed> $data */
            $data = (array) $response->json();

            if (! data_get($data, 'done', false)) {
                continue;
            }

            if (! empty($data['error'])) {
                throw new LocationIntelligenceException(
                    (string) data_get($data, 'error.message', 'Video analysis operation failed.'),
                    502,
                );
            }

            return $this->extractVideoSignals($data);
        }

        throw new LocationIntelligenceException(
            'Video analysis timed out. Try a shorter video clip.',
            504,
        );
    }

    // -------------------------------------------------------------------------
    // Signal extraction
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $operationData
     * @return array<string, mixed>
     */
    private function extractVideoSignals(array $operationData): array
    {
        /** @var array<string, mixed> $results */
        $results = (array) data_get($operationData, 'response.annotationResults.0', []);

        $labels    = [];
        $ocrText   = [];
        $bestLabel = null;
        $bestScore = 0.0;

        // Segment-level label annotations
        foreach ((array) data_get($results, 'segmentLabelAnnotations', []) as $annotation) {
            $entity     = (string) data_get($annotation, 'entity.description', '');
            $confidence = (float) data_get($annotation, 'segments.0.confidence', 0);

            if ($entity === '') {
                continue;
            }

            $labels[] = ['name' => $entity, 'confidence' => (int) round($confidence * 100)];

            if ($confidence > $bestScore) {
                $bestScore = $confidence;
                $bestLabel = $entity;
            }
        }

        // Text / OCR annotations
        foreach ((array) data_get($results, 'textAnnotations', []) as $textAnnotation) {
            $text = trim((string) data_get($textAnnotation, 'text', ''));

            if (strlen($text) >= 3) {
                $ocrText[] = $text;
            }
        }

        return [
            'best_candidate' => $bestLabel,
            'confidence'     => (int) round($bestScore * 100),
            'labels'         => $labels,
            'ocr_text'       => array_values(array_unique($ocrText)),
        ];
    }

    // -------------------------------------------------------------------------
    // Video download
    // -------------------------------------------------------------------------

    private function downloadAndEncode(string $url): string
    {
        $maxBytes = (int) config('location_intelligence.video.max_download_bytes', 52428800);
        $timeout  = (int) config('location_intelligence.video.download_timeout', 60);

        $response = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; LocationIntelligenceBot/1.0)'])
            ->get($url);

        if (! $response->successful()) {
            throw new LocationIntelligenceException(
                'Failed to download video from the provided URL.',
                422,
                ['input' => ['The video URL could not be fetched. Ensure it is publicly accessible.']],
            );
        }

        $body = $response->body();

        if ($body === '') {
            throw new LocationIntelligenceException('Downloaded video file is empty.', 422);
        }

        if (strlen($body) > $maxBytes) {
            throw new LocationIntelligenceException(
                'Video file exceeds the maximum allowed size of '.round($maxBytes / 1_048_576).' MB.',
                422,
                ['input' => ['Please provide a shorter or smaller video file.']],
            );
        }

        return base64_encode($body);
    }
}
