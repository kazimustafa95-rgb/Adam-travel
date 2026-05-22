<?php

namespace App\Services\PublicApi;

use App\Services\PublicApi\Evidence\UrlContentClassifier;
use Illuminate\Support\Facades\Process;

class LocationSuggestionRoutingService
{
    public function __construct(
        protected UrlContentClassifier $urlContentClassifier,
        protected LocationSuggestionAsyncService $locationSuggestionAsyncService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function routeToAsyncIfNeeded(string $input, bool $preferAsync = false): ?array
    {
        if (! (bool) config('location_suggestions.async.enabled', true)) {
            return null;
        }

        $classification = $this->urlContentClassifier->classify($input);

        if ($preferAsync) {
            return $this->buildQueuedPayload($input, $classification['platform'], routingReason: 'forced_async');
        }

        if ($classification['mediaType'] !== 'video') {
            return null;
        }

        if (! (bool) config('location_suggestions.async.auto_route_long_videos', true)) {
            return null;
        }

        $durationSeconds = $this->estimateVideoDurationSeconds($input, $classification['platform']);
        $threshold = max(1, (int) config('location_suggestions.async.auto_route_video_seconds', 45));

        if ($durationSeconds === null || $durationSeconds <= $threshold) {
            return null;
        }

        return $this->buildQueuedPayload($input, $classification['platform'], $durationSeconds, 'auto_long_video');
    }

    protected function estimateVideoDurationSeconds(string $input, string $platform): ?int
    {
        $ytDlpPath = trim((string) config('location_suggestions.video_processing.yt_dlp_path', ''));

        if ($ytDlpPath === '' || ! $this->urlContentClassifier->isVideoUrl($input, $platform)) {
            return null;
        }

        $result = Process::timeout(60)->run([
            $ytDlpPath,
            '--dump-single-json',
            '--no-download',
            '--no-playlist',
            $input,
        ]);

        if ($result->failed()) {
            return null;
        }

        $decoded = json_decode($result->output(), true);
        $duration = is_array($decoded) ? ($decoded['duration'] ?? null) : null;

        if (! is_int($duration) && ! is_float($duration) && ! is_string($duration)) {
            return null;
        }

        return (int) round((float) $duration);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildQueuedPayload(
        string $input,
        string $platform,
        ?int $estimatedDurationSeconds = null,
        string $routingReason = 'direct_async',
    ): array
    {
        $job = $this->locationSuggestionAsyncService->create($input, [
            'mode' => 'queued',
            'used_async' => true,
            'routing_reason' => $routingReason,
            'estimated_duration_seconds' => $estimatedDurationSeconds,
            'platform' => $platform,
        ]);

        return [
            'query' => $input,
            'places' => [],
            'metadata' => [
                'platform' => $platform,
                'title' => '',
                'description' => '',
                'image' => '',
                'pageText' => '',
            ],
            'async_job' => array_filter([
                'token' => $job['token'] ?? null,
                'status' => $job['status'] ?? null,
                'estimated_duration_seconds' => $estimatedDurationSeconds,
                'poll_url' => isset($job['token']) ? route('api.v1.public.location-suggestions.async.status', ['token' => $job['token']]) : null,
                'realtime' => $job['realtime'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
            'analysis_debug' => array_filter([
                'mode' => 'queued',
                'used_async' => true,
                'routing_reason' => $routingReason,
                'estimated_duration_seconds' => $estimatedDurationSeconds,
                'platform' => $platform,
            ], fn (mixed $value): bool => $value !== null),
        ];
    }
}
