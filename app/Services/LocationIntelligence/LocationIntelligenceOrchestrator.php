<?php

namespace App\Services\LocationIntelligence;

use App\Contracts\LocationIntelligence\InputTypeDetectorContract;
use App\Enums\LocationIntelligence\LocationInputType;
use App\Exceptions\LocationIntelligence\LocationIntelligenceException;

class LocationIntelligenceOrchestrator
{
    public function __construct(
        private readonly InputTypeDetectorContract $typeDetector,
        private readonly TextLocationResolverService $textResolver,
        private readonly ImageLocationResolverService $imageResolver,
        private readonly VideoLocationResolverService $videoResolver,
        private readonly SocialUrlLocationResolverService $socialResolver,
    ) {}

    /**
     * Detect the input type and route to the appropriate resolver.
     *
     * @return array<string, mixed>
     *
     * @throws LocationIntelligenceException
     */
    public function resolve(string $input): array
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw new LocationIntelligenceException(
                'Input must not be empty.',
                422,
                ['input' => ['The input field is required.']],
            );
        }

        $inputType = $this->typeDetector->detect($trimmed);

        // All URL-based inputs must pass the public-URL safety check
        if ($inputType !== LocationInputType::Text) {
            $this->guardPublicUrl($trimmed);
        }

        return match ($inputType) {
            LocationInputType::Text      => $this->textResolver->resolve($trimmed),
            LocationInputType::ImageUrl  => $this->imageResolver->resolve($trimmed),
            LocationInputType::VideoUrl  => $this->videoResolver->resolve($trimmed),
            LocationInputType::SocialUrl => $this->socialResolver->resolve($trimmed),
        };
    }

    // -------------------------------------------------------------------------
    // URL safety guard — blocks localhost and private IPs
    // -------------------------------------------------------------------------

    private function guardPublicUrl(string $url): void
    {
        $parts  = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new LocationIntelligenceException(
                'Only http and https URLs are supported.',
                422,
                ['input' => ['The URL must use http or https scheme.']],
            );
        }

        if ($host === '') {
            throw new LocationIntelligenceException(
                'Could not determine host from the provided URL.',
                422,
                ['input' => ['The URL does not contain a valid host.']],
            );
        }

        // Block common localhost aliases
        if (in_array($host, ['localhost', '0.0.0.0', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            throw new LocationIntelligenceException(
                'Access to local or private addresses is not allowed.',
                422,
                ['input' => ['The URL must point to a publicly accessible resource.']],
            );
        }

        // Block private / reserved IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($publicIp === false) {
                throw new LocationIntelligenceException(
                    'Access to private or reserved IP addresses is not allowed.',
                    422,
                    ['input' => ['The URL must not point to a private or reserved IP address.']],
                );
            }
        }
    }
}
