<?php

namespace App\Services\PublicApi\Evidence;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class VideoEvidenceExtractor
{
    public function enrich(string $url, LocationEvidence $evidence): LocationEvidence
    {
        if (! $evidence->isVideo() || ! $this->isVideoProcessingEnabled()) {
            return $evidence;
        }

        $ytDlpPath = trim((string) config('location_suggestions.video_processing.yt_dlp_path', ''));
        $ffmpegPath = trim((string) config('location_suggestions.video_processing.ffmpeg_path', ''));
        $expectedPlaceCount = $this->extractExpectedPlaceCount($evidence);
        $binaryDebug = $this->binaryDebugPayload($ytDlpPath, $ffmpegPath);

        if ($ytDlpPath === '' || $ffmpegPath === '') {
            return $evidence->withAnalysisDebug([
                'video_processing_attempted' => false,
                'video_processing_enabled' => true,
                'video_processing_skipped_reason' => 'missing_binary_configuration',
                'expected_place_count' => $expectedPlaceCount,
                ...$binaryDebug,
            ]);
        }

        if (! $binaryDebug['yt_dlp_exists'] || ! $binaryDebug['ffmpeg_exists']) {
            return $evidence->withAnalysisDebug([
                'video_processing_attempted' => false,
                'video_processing_enabled' => true,
                'video_processing_skipped_reason' => 'binary_path_not_found',
                'expected_place_count' => $expectedPlaceCount,
                ...$binaryDebug,
            ]);
        }

        $workDir = storage_path('app/private/location-suggestions/'.Str::uuid());

        if (! is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        try {
            $videoDurationSeconds = $this->fetchVideoDurationSeconds($url, $ytDlpPath);
            $videoPath = $this->downloadVideo($url, $workDir, $ytDlpPath);

            if ($videoPath === null) {
                return $evidence->withAnalysisDebug([
                    'video_processing_attempted' => true,
                    'video_download_succeeded' => false,
                    'video_duration_seconds' => $videoDurationSeconds,
                    'expected_place_count' => $expectedPlaceCount,
                    ...$binaryDebug,
                ]);
            }

            $frameSamplingPlan = $this->buildFrameSamplingPlan($videoDurationSeconds, $expectedPlaceCount);
            $frameDataUrls = $this->extractFrameDataUrls($videoPath, $workDir, $ffmpegPath, $frameSamplingPlan);
            $transcript = $this->transcribeVideoAudio($videoPath, $workDir, $ffmpegPath, $videoDurationSeconds);

            $updatedEvidence = $evidence
                ->withAdditionalAnalysisImages($frameDataUrls)
                ->withAnalysisDebug([
                    'video_processing_attempted' => true,
                    'video_download_succeeded' => true,
                    'video_duration_seconds' => $videoDurationSeconds,
                    'frames_extracted' => count($frameDataUrls),
                    'frame_target_count' => $frameSamplingPlan['target_frame_count'],
                    'sampling_window_seconds' => $frameSamplingPlan['sampling_window_seconds'],
                    'frame_divisor_seconds' => $frameSamplingPlan['frame_divisor_seconds'],
                    'expected_place_count' => $expectedPlaceCount,
                    'ranked_video_detected' => $expectedPlaceCount !== null,
                    ...$binaryDebug,
                ]);

            if ($transcript !== '') {
                $updatedEvidence = $updatedEvidence->withTranscript($transcript);
            }

            $updatedEvidence = $updatedEvidence->withAnalysisDebug([
                'transcript_used' => $transcript !== '',
            ]);

            return $updatedEvidence;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    protected function isVideoProcessingEnabled(): bool
    {
        return (bool) config('location_suggestions.video_processing.enabled', true);
    }

    protected function downloadVideo(string $url, string $workDir, string $ytDlpPath): ?string
    {
        $outputTemplate = $workDir.DIRECTORY_SEPARATOR.'source.%(ext)s';

        $result = Process::path($workDir)
            ->timeout(180)
            ->run([
                $ytDlpPath,
                '--no-playlist',
                '--no-progress',
                '--newline',
                '--output',
                $outputTemplate,
                '--format',
                'mp4/best[ext=mp4]/best',
                $url,
            ]);

        if ($result->failed()) {
            return null;
        }

        $files = array_values(array_filter(
            glob($workDir.DIRECTORY_SEPARATOR.'source.*') ?: [],
            fn (string $path): bool => ! str_ends_with(strtolower($path), '.part')
        ));

        return $files[0] ?? null;
    }

    /**
     * @return list<string>
     */
    protected function extractFrameDataUrls(
        string $videoPath,
        string $workDir,
        string $ffmpegPath,
        array $frameSamplingPlan,
    ): array
    {
        $framesDir = $workDir.DIRECTORY_SEPARATOR.'frames';

        if (! is_dir($framesDir)) {
            mkdir($framesDir, 0755, true);
        }

        $targetFrameCount = max(1, (int) ($frameSamplingPlan['target_frame_count'] ?? 1));
        $samplingWindowSeconds = max(1, (int) ($frameSamplingPlan['sampling_window_seconds'] ?? 1));
        $fallbackInterval = max(1, (int) config('location_suggestions.video_processing.frame_interval_seconds', 3));
        $fpsFilter = $this->buildFpsFilter($targetFrameCount, $samplingWindowSeconds, $fallbackInterval);
        $framePattern = $framesDir.DIRECTORY_SEPARATOR.'frame-%03d.jpg';

        $result = Process::path($workDir)
            ->timeout(180)
            ->run([
                $ffmpegPath,
                '-y',
                '-i',
                $videoPath,
                '-t',
                (string) $samplingWindowSeconds,
                '-vf',
                $fpsFilter,
                '-frames:v',
                (string) $targetFrameCount,
                $framePattern,
            ]);

        if ($result->failed()) {
            return [];
        }

        $framePaths = glob($framesDir.DIRECTORY_SEPARATOR.'frame-*.jpg') ?: [];
        sort($framePaths);

        $frames = [];

        foreach ($framePaths as $framePath) {
            $data = @file_get_contents($framePath);

            if ($data === false || $data === '') {
                continue;
            }

            $frames[] = 'data:image/jpeg;base64,'.base64_encode($data);
        }

        return $frames;
    }

    /**
     * @param  array{sampling_window_seconds:int,target_frame_count:int,frame_divisor_seconds:int}  $frameSamplingPlan
     */
    protected function buildFrameSamplingPlan(?int $videoDurationSeconds, ?int $expectedPlaceCount = null): array
    {
        $frameDivisorSeconds = max(1, (int) config('location_suggestions.video_processing.frame_divisor_seconds', 2));
        $baseMaxFrames = max(1, (int) config('location_suggestions.video_processing.max_frames', 8));
        $maxVideoSeconds = max(1, (int) config('location_suggestions.video_processing.max_video_seconds', 45));

        if ($videoDurationSeconds !== null && $videoDurationSeconds > 0) {
            $samplingWindowSeconds = max(1, $videoDurationSeconds);
            $targetFrameCount = max(1, (int) ceil($samplingWindowSeconds / $frameDivisorSeconds));

            return [
                'sampling_window_seconds' => $samplingWindowSeconds,
                'target_frame_count' => $targetFrameCount,
                'frame_divisor_seconds' => $frameDivisorSeconds,
            ];
        }

        $targetFrameCount = $expectedPlaceCount !== null
            ? max($baseMaxFrames, $expectedPlaceCount * 2)
            : $baseMaxFrames;
        $samplingWindowSeconds = max(1, min(
            $maxVideoSeconds,
            $expectedPlaceCount !== null ? max($expectedPlaceCount * 3, 20) : $maxVideoSeconds
        ));

        return [
            'sampling_window_seconds' => $samplingWindowSeconds,
            'target_frame_count' => $targetFrameCount,
            'frame_divisor_seconds' => $frameDivisorSeconds,
        ];
    }

    protected function buildFpsFilter(int $maxFrames, int $samplingWindowSeconds, int $fallbackInterval): string
    {
        if ($samplingWindowSeconds <= 0 || $maxFrames <= 0) {
            return 'fps=1/'.max(1, $fallbackInterval);
        }

        $sampleRate = $maxFrames / $samplingWindowSeconds;

        if ($sampleRate >= 1) {
            return 'fps=1';
        }

        return 'fps='.$maxFrames.'/'.$samplingWindowSeconds;
    }

    protected function fetchVideoDurationSeconds(string $url, string $ytDlpPath): ?int
    {
        $result = Process::timeout(60)->run([
            $ytDlpPath,
            '--dump-single-json',
            '--no-download',
            '--no-playlist',
            $url,
        ]);

        if ($result->failed()) {
            return null;
        }

        $decoded = json_decode($result->output(), true);
        $duration = is_array($decoded) ? ($decoded['duration'] ?? null) : null;

        if (! is_int($duration) && ! is_float($duration) && ! is_string($duration)) {
            return null;
        }

        return max(1, (int) ceil((float) $duration));
    }

    protected function extractExpectedPlaceCount(LocationEvidence $evidence): ?int
    {
        $haystacks = array_filter([
            $evidence->title,
            $evidence->description,
            $evidence->pageText,
            $evidence->transcript,
        ], fn (string $value): bool => trim($value) !== '');

        foreach ($haystacks as $text) {
            if (preg_match('/\btop\s+(\d{1,2})\b/i', $text, $matches) === 1 && isset($matches[1])) {
                return (int) $matches[1];
            }

            if (preg_match('/\b(\d{1,2})\s+(?:things?|places?|spots?|destinations?|landmarks?)\b/i', $text, $matches) === 1 && isset($matches[1])) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function binaryDebugPayload(string $ytDlpPath, string $ffmpegPath): array
    {
        return [
            'yt_dlp_configured' => $ytDlpPath !== '',
            'ffmpeg_configured' => $ffmpegPath !== '',
            'yt_dlp_exists' => $this->binaryPathIsResolvable($ytDlpPath),
            'ffmpeg_exists' => $this->binaryPathIsResolvable($ffmpegPath),
            'missing_binary_keys' => array_values(array_filter([
                $ytDlpPath === '' ? 'YTDLP_PATH' : null,
                $ffmpegPath === '' ? 'FFMPEG_PATH' : null,
            ])),
        ];
    }

    protected function binaryPathIsResolvable(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $looksLikeExplicitPath = str_contains($path, '\\')
            || str_contains($path, '/')
            || preg_match('/^[a-z]:/i', $path) === 1;

        if (! $looksLikeExplicitPath) {
            return true;
        }

        return File::exists($path);
    }

    protected function transcribeVideoAudio(string $videoPath, string $workDir, string $ffmpegPath, ?int $videoDurationSeconds = null): string
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            return '';
        }

        $audioPath = $workDir.DIRECTORY_SEPARATOR.'audio.mp3';
        $maxVideoSeconds = max(1, (int) config('location_suggestions.video_processing.max_video_seconds', 45));
        $transcriptionWindowSeconds = $videoDurationSeconds !== null && $videoDurationSeconds > 0
            ? $videoDurationSeconds
            : $maxVideoSeconds;

        $result = Process::path($workDir)
            ->timeout(180)
            ->run([
                $ffmpegPath,
                '-y',
                '-i',
                $videoPath,
                '-t',
                (string) $transcriptionWindowSeconds,
                '-vn',
                '-ac',
                '1',
                '-ar',
                '16000',
                $audioPath,
            ]);

        if ($result->failed() || ! is_file($audioPath)) {
            return '';
        }

        $handle = fopen($audioPath, 'r');

        if ($handle === false) {
            return '';
        }

        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->acceptJson()
                ->withToken($apiKey)
                ->attach('file', $handle, basename($audioPath))
                ->post('audio/transcriptions', [
                    'model' => (string) config('services.openai.transcribe_model', 'gpt-4o-transcribe'),
                    'response_format' => 'json',
                ]);

            if (! $response->successful()) {
                return '';
            }

            return trim((string) data_get($response->json(), 'text', ''));
        } finally {
            fclose($handle);
        }
    }
}
