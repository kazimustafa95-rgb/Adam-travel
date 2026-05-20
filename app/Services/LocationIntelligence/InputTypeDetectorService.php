<?php

namespace App\Services\LocationIntelligence;

use App\Contracts\LocationIntelligence\InputTypeDetectorContract;
use App\Enums\LocationIntelligence\LocationInputType;
use Illuminate\Support\Str;

class InputTypeDetectorService implements InputTypeDetectorContract
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'avif', 'svg'];

    /** @var list<string> */
    private const VIDEO_EXTENSIONS = ['mp4', 'avi', 'mov', 'mkv', 'webm', 'flv', 'wmv', 'm4v', '3gp', 'ogv', 'ts'];

    /** @var list<string> */
    private const SOCIAL_DOMAINS = [
        'instagram.com', 'facebook.com', 'fb.watch',
        'tiktok.com', 'twitter.com', 'x.com',
        'linkedin.com', 'pinterest.com', 'reddit.com',
        'tumblr.com', 'medium.com', 'blogger.com', 'wordpress.com',
    ];

    /** @var list<string> */
    private const VIDEO_DOMAINS = ['youtube.com', 'youtu.be', 'vimeo.com', 'dailymotion.com', 'twitch.tv'];

    public function detect(string $input): LocationInputType
    {
        $trimmed = trim($input);

        if (! preg_match('/^https?:\/\//i', $trimmed)) {
            return LocationInputType::Text;
        }

        $host      = Str::lower((string) (parse_url($trimmed, PHP_URL_HOST) ?? ''));
        $path      = Str::lower((string) (parse_url($trimmed, PHP_URL_PATH) ?? ''));
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Explicit video file extension takes priority
        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return LocationInputType::VideoUrl;
        }

        // Explicit image file extension
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return LocationInputType::ImageUrl;
        }

        // Known video platforms (YouTube, Vimeo, etc.)
        if ($this->matchesDomain($host, self::VIDEO_DOMAINS)) {
            return LocationInputType::VideoUrl;
        }

        // Known social / blog platforms
        if ($this->matchesDomain($host, self::SOCIAL_DOMAINS)) {
            return LocationInputType::SocialUrl;
        }

        // Heuristic: URL path hints at an image resource
        if ($this->looksLikeImageUrl($trimmed, $path)) {
            return LocationInputType::ImageUrl;
        }

        // Default: treat any other URL as a social/blog/public-post URL
        return LocationInputType::SocialUrl;
    }

    /**
     * @param  list<string>  $domains
     */
    private function matchesDomain(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeImageUrl(string $url, string $path): bool
    {
        // Extension hidden behind query string (e.g. /img.jpg?v=1)
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif)(\?|$)/i', $url) === 1) {
            return true;
        }

        // Common CDN / image service path fragments
        return str_contains($path, '/image') ||
               str_contains($path, '/photo') ||
               str_contains($path, '/img/') ||
               str_contains($path, '/media/');
    }
}
