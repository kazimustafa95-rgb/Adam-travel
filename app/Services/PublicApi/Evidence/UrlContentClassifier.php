<?php

namespace App\Services\PublicApi\Evidence;

use Illuminate\Support\Str;

class UrlContentClassifier
{
    /**
     * @return array{platform: string, mediaType: string}
     */
    public function classify(string $input): array
    {
        $isUrl = preg_match('/^https?:\/\//i', $input) === 1;

        if (! $isUrl) {
            return [
                'platform' => 'manual_text',
                'mediaType' => 'text',
            ];
        }

        $platform = $this->detectPlatform($input);
        $mediaType = match (true) {
            $this->isDirectImageUrl($input) => 'image',
            $this->isVideoUrl($input, $platform) => 'video',
            default => 'webpage',
        };

        return [
            'platform' => $platform,
            'mediaType' => $mediaType,
        ];
    }

    public function detectPlatform(string $url): string
    {
        $lower = Str::lower($url);

        return match (true) {
            str_contains($lower, 'youtube.com'),
            str_contains($lower, 'youtu.be') => 'youtube',
            str_contains($lower, 'instagram.com') => 'instagram',
            str_contains($lower, 'tiktok.com') => 'tiktok',
            str_contains($lower, 'facebook.com'),
            str_contains($lower, 'fb.watch') => 'facebook',
            str_contains($lower, 'x.com'),
            str_contains($lower, 'twitter.com') => 'twitter',
            default => 'website',
        };
    }

    public function isDirectImageUrl(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        return preg_match('/\.(jpg|jpeg|png|webp|gif)$/', $path) === 1;
    }

    public function isVideoUrl(string $url, ?string $platform = null): bool
    {
        $platform ??= $this->detectPlatform($url);
        $lowerUrl = Str::lower($url);
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if (preg_match('/\.(mp4|mov|m4v|webm|avi|mkv)$/', $path) === 1) {
            return true;
        }

        if (in_array($platform, ['youtube', 'tiktok'], true)) {
            return true;
        }

        return str_contains($path, '/reel/')
            || str_contains($path, '/reels/')
            || str_contains($path, '/video/')
            || str_contains($path, '/shorts/')
            || ($platform === 'instagram' && str_contains($lowerUrl, '/reel'))
            || ($platform === 'facebook' && str_contains($lowerUrl, '/watch'));
    }
}
