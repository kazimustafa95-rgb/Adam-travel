<?php

namespace App\Services\PublicApi;

use App\Exceptions\PublicApiException;
use App\Services\PublicApi\Evidence\LocationEvidence;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use JsonException;

class OpenAiLocationAnalyzer
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(LocationEvidence $evidence, string $originalInput): array
    {
        if ($this->shouldChunkVideoAnalysis($evidence)) {
            return $this->analyzeVideoInChunks($evidence, $originalInput);
        }

        $imageDetail = $this->resolveImageDetail($evidence, chunked: false);
        $parsedResult = $this->sendAnalysisRequest(
            evidence: $evidence,
            originalInput: $originalInput,
            imagesToSend: $evidence->analysisImages,
            imageDetail: $imageDetail,
            includeTranscript: true,
        );

        return [
            'result' => $parsedResult,
            'debug' => [
                'openai_images_sent' => count($evidence->analysisImages),
                'openai_image_detail' => $imageDetail,
                'openai_request_count' => 1,
                'openai_chunk_image_counts' => $evidence->analysisImages === [] ? [] : [count($evidence->analysisImages)],
                'openai_chunked' => false,
                'transcript_in_prompt' => $evidence->transcript !== '',
                'transcript_length' => strlen($evidence->transcript),
                'openai_transcript_strategy' => $evidence->transcript !== '' ? 'all_requests' : 'none',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function analyzeVideoInChunks(LocationEvidence $evidence, string $originalInput): array
    {
        $chunkSize = max(1, (int) config('location_suggestions.openai.video_chunk_size', 8));
        $imageChunks = $this->chunkImagesForAnalysis($evidence->analysisImages, $chunkSize);
        $imageDetail = $this->resolveImageDetail($evidence, chunked: true);
        $mergedQuery = '';
        $mergedPlaces = [];
        $chunkImageCounts = [];

        foreach ($imageChunks as $index => $imageChunk) {
            $chunkNumber = $index + 1;
            $totalChunks = count($imageChunks);
            $includeTranscript = $chunkNumber === 1;

            $chunkResult = $this->sendAnalysisRequest(
                evidence: $evidence,
                originalInput: $originalInput,
                imagesToSend: $imageChunk,
                imageDetail: $imageDetail,
                includeTranscript: $includeTranscript,
                batchContext: $totalChunks > 1
                    ? "Video frame batch {$chunkNumber} of {$totalChunks}. Return locations supported by this batch plus the shared metadata."
                    : null,
            );

            $chunkImageCounts[] = count($imageChunk);

            $chunkQuery = trim((string) ($chunkResult['query'] ?? ''));

            if ($mergedQuery === '' && $chunkQuery !== '') {
                $mergedQuery = $chunkQuery;
            }

            $chunkPlaces = is_array($chunkResult['places'] ?? null) ? $chunkResult['places'] : [];
            $mergedPlaces = $this->mergePlaces($mergedPlaces, $chunkPlaces);
        }

        return [
            'result' => [
                'query' => $mergedQuery !== '' ? $mergedQuery : ($evidence->title !== '' ? $evidence->title : $originalInput),
                'places' => array_values($mergedPlaces),
            ],
            'debug' => [
                'openai_images_sent' => count($evidence->analysisImages),
                'openai_image_detail' => $imageDetail,
                'openai_request_count' => count($imageChunks),
                'openai_chunk_image_counts' => $chunkImageCounts,
                'openai_chunked' => true,
                'transcript_in_prompt' => $evidence->transcript !== '',
                'transcript_length' => strlen($evidence->transcript),
                'openai_transcript_strategy' => $evidence->transcript !== '' ? 'first_chunk_only' : 'none',
            ],
        ];
    }

    protected function shouldChunkVideoAnalysis(LocationEvidence $evidence): bool
    {
        if (! $evidence->isVideo()) {
            return false;
        }

        $chunkSize = max(1, (int) config('location_suggestions.openai.video_chunk_size', 8));

        return count($evidence->analysisImages) > $chunkSize;
    }

    /**
     * @param  list<string>  $images
     * @return list<list<string>>
     */
    protected function chunkImagesForAnalysis(array $images, int $chunkSize): array
    {
        $totalImages = count($images);

        if ($totalImages === 0) {
            return [];
        }

        if ($totalImages <= $chunkSize) {
            return [$images];
        }

        $chunkCount = (int) ceil($totalImages / $chunkSize);
        $baseChunkSize = intdiv($totalImages, $chunkCount);
        $remainder = $totalImages % $chunkCount;
        $offset = 0;
        $chunks = [];

        for ($index = 0; $index < $chunkCount; $index++) {
            $currentChunkSize = $baseChunkSize + ($index < $remainder ? 1 : 0);
            $chunks[] = array_slice($images, $offset, $currentChunkSize);
            $offset += $currentChunkSize;
        }

        return $chunks;
    }

    protected function resolveImageDetail(LocationEvidence $evidence, bool $chunked): string
    {
        if (! $evidence->isVideo()) {
            return 'auto';
        }

        $configKey = $chunked
            ? 'location_suggestions.openai.chunked_video_image_detail'
            : 'location_suggestions.openai.single_video_image_detail';
        $detail = strtolower(trim((string) config($configKey, $chunked ? 'low' : 'high')));

        return in_array($detail, ['low', 'high', 'auto'], true)
            ? $detail
            : ($chunked ? 'low' : 'high');
    }

    /**
     * @param  list<string>  $imagesToSend
     * @return array<string, mixed>
     */
    protected function sendAnalysisRequest(
        LocationEvidence $evidence,
        string $originalInput,
        array $imagesToSend,
        string $imageDetail,
        bool $includeTranscript,
        ?string $batchContext = null,
    ): array {
        $userContent = [
            [
                'type' => 'text',
                'text' => $this->buildEvidenceSummary($evidence, $originalInput, $includeTranscript, $batchContext),
            ],
        ];

        foreach ($imagesToSend as $image) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image,
                    'detail' => $imageDetail,
                ],
            ];
        }

        $response = $this->openAiRequest()->post('chat/completions', [
            'model' => (string) config('services.openai.model', 'gpt-4o'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'location_suggestions_result',
                    'strict' => true,
                    'schema' => $this->responseSchema(),
                ],
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

        return $parsedResult;
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional AI location analyst. Your job is to identify real-world locations from URLs, videos, images, social posts, captions, hashtags, transcripts, page text, and metadata.

Return only valid JSON in the provided schema.

Core rules:
- Return only locations supported by direct evidence from the provided title, description, hashtags, transcript, visible text, landmarks, or frames.
- Return 0, 1, or multiple places depending on the evidence.
- If several frames in a video show different clearly identifiable locations, return each distinct location once.
- For ranked slideshow-style videos such as "Top 10 things to see in Berlin", read the on-screen overlay text in each frame carefully. Text like "10 TV TOWER", "9 GENDARMENMARKT", and "8 REICHSTAG BUILDING" counts as direct evidence.
- When multiple video frames are attached, treat them as chronological samples from the same video and combine evidence across them.
- If a video title says "Top 3" or "3 most beautiful places" but only one or two places are actually supported by the evidence, return only the supported places.
- Do not invent unnamed famous places just to satisfy a count in the title.
- Do not add parent cities or countries as separate entries when a more specific venue is already identified.
- Do not return social media company headquarters, platform names, or "Unknown".

When analyzing images or video frames, look for:
- visible text, captions, or signage
- landmarks and architectural style
- mountains, rivers, coastlines, deserts, forests, or other geographic features
- language, vehicle clues, cultural symbols, and climate cues

Confidence rules:
- 80% to 95% for exact places directly named or unmistakably shown
- 50% to 79% for strong but indirect visual/textual evidence
- 20% to 49% for weak evidence
- If all candidates would be below 20%, return an empty places array

Reason rules:
- Every reason must cite the actual evidence used
- Reasons must be concrete, not vague

Coordinates:
- Always set lat and lng to 0
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query', 'places'],
            'properties' => [
                'query' => ['type' => 'string'],
                'places' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['place', 'category', 'city', 'country', 'confidence', 'lat', 'lng', 'reason'],
                        'properties' => [
                            'place' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                            'country' => ['type' => 'string'],
                            'confidence' => ['type' => 'string'],
                            'lat' => ['type' => 'number'],
                            'lng' => ['type' => 'number'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function buildEvidenceSummary(
        LocationEvidence $evidence,
        string $originalInput,
        bool $includeTranscript = true,
        ?string $batchContext = null,
    ): string
    {
        $summary = [
            'Analyze this content and identify all real-world locations directly supported by the evidence.',
            '',
            'User Input:',
            $originalInput,
            '',
            'Platform:',
            $evidence->platform,
            '',
            'Media Type:',
            $evidence->mediaType,
            '',
            'Title:',
            $evidence->title !== '' ? $evidence->title : '(none)',
            '',
            'Description:',
            $evidence->description !== '' ? $evidence->description : '(none)',
            '',
            'Page Text:',
            $evidence->pageText !== '' ? $evidence->pageText : '(none)',
        ];

        if ($batchContext !== null) {
            $summary[] = '';
            $summary[] = 'Batch Context:';
            $summary[] = $batchContext;
        }

        if ($includeTranscript && $evidence->transcript !== '') {
            $summary[] = '';
            $summary[] = 'Transcript:';
            $summary[] = Str::limit($evidence->transcript, 4000, '');
        }

        if ($evidence->isVideo() && $evidence->analysisImages !== []) {
            $summary[] = '';
            $summary[] = 'Video Frames:';
            $summary[] = 'The attached images are chronological frame samples from the same video. Read any large overlay text, rank numbers, subtitles, and landmark names carefully.';
        }

        $summary[] = '';
        $summary[] = 'Task:';
        $summary[] = 'Return every distinct real-world location directly supported by the evidence. If no location is supported, return an empty places array.';

        return implode("\n", $summary);
    }

    /**
     * @param  array<string, array<string, mixed>>  $existingPlaces
     * @param  array<int, mixed>  $incomingPlaces
     * @return array<string, array<string, mixed>>
     */
    protected function mergePlaces(array $existingPlaces, array $incomingPlaces): array
    {
        foreach ($incomingPlaces as $incomingPlace) {
            if (! is_array($incomingPlace)) {
                continue;
            }

            $key = $this->placeKey($incomingPlace);

            if ($key === '') {
                continue;
            }

            if (! isset($existingPlaces[$key])) {
                $existingPlaces[$key] = $incomingPlace;

                continue;
            }

            $currentConfidence = $this->confidenceScore($existingPlaces[$key]['confidence'] ?? null);
            $incomingConfidence = $this->confidenceScore($incomingPlace['confidence'] ?? null);

            if ($incomingConfidence > $currentConfidence) {
                $existingPlaces[$key] = $incomingPlace;

                continue;
            }

            $existingReason = trim((string) ($existingPlaces[$key]['reason'] ?? ''));
            $incomingReason = trim((string) ($incomingPlace['reason'] ?? ''));

            if ($existingReason === '' && $incomingReason !== '') {
                $existingPlaces[$key]['reason'] = $incomingReason;
            }
        }

        return $existingPlaces;
    }

    /**
     * @param  array<string, mixed>  $place
     */
    protected function placeKey(array $place): string
    {
        $segments = [
            strtolower(trim((string) ($place['place'] ?? ''))),
            strtolower(trim((string) ($place['category'] ?? ''))),
            strtolower(trim((string) ($place['city'] ?? ''))),
            strtolower(trim((string) ($place['country'] ?? ''))),
        ];

        return trim(implode('|', $segments), '|');
    }

    protected function confidenceScore(mixed $confidence): int
    {
        if (! is_string($confidence) && ! is_numeric($confidence)) {
            return 0;
        }

        if (preg_match('/(\d{1,3})/', (string) $confidence, $matches) !== 1 || ! isset($matches[1])) {
            return 0;
        }

        return (int) $matches[1];
    }

    protected function openAiRequest(): PendingRequest
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new PublicApiException('OPENAI_API_KEY missing in server configuration', 500);
        }

        return \Illuminate\Support\Facades\Http::baseUrl((string) config('services.openai.base_url'))
            ->acceptJson()
            ->timeout(60)
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
}
