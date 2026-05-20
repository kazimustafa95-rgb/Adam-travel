<?php

namespace App\Services\LocationIntelligence;

use App\Exceptions\LocationIntelligence\LocationIntelligenceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleServiceAccountTokenService
{
    private const SCOPE      = 'https://www.googleapis.com/auth/cloud-platform';
    private const CACHE_KEY  = 'location_intelligence.gcp_access_token';
    private const TTL        = 3300; // Cache for 55 min (token expires in 60 min)

    /**
     * Return a valid OAuth2 access token, fetching a new one only when the cached one expires.
     */
    public function getAccessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, fn (): string => $this->fetchNewToken());
    }

    // -------------------------------------------------------------------------
    // Token fetching
    // -------------------------------------------------------------------------

    private function fetchNewToken(): string
    {
        $credentials = $this->loadCredentials();
        $jwt         = $this->buildJwt($credentials);
        $tokenUri    = (string) config('location_intelligence.google.token_uri', 'https://oauth2.googleapis.com/token');

        $response = Http::timeout(15)
            ->asForm()
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

        if (! $response->successful()) {
            $detail = (string) data_get($response->json(), 'error_description', $response->body());

            throw new LocationIntelligenceException(
                'Failed to obtain Google Cloud access token: '.$detail,
                500,
            );
        }

        $token = data_get($response->json(), 'access_token');

        if (! is_string($token) || $token === '') {
            throw new LocationIntelligenceException(
                'Google Cloud returned an empty access token.',
                500,
            );
        }

        return $token;
    }

    // -------------------------------------------------------------------------
    // JWT construction (RS256)
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $credentials
     */
    private function buildJwt(array $credentials): string
    {
        $now     = time();
        $tokenUri = (string) config('location_intelligence.google.token_uri', 'https://oauth2.googleapis.com/token');

        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64url(json_encode([
            'iss'   => $credentials['client_email'],
            'sub'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $tokenUri,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$payload;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new LocationIntelligenceException(
                'Invalid private key in the service account JSON. Check GOOGLE_CLOUD_SERVICE_ACCOUNT_JSON.',
                500,
            );
        }

        $signature = '';

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new LocationIntelligenceException(
                'Failed to sign the service account JWT.',
                500,
            );
        }

        return $signingInput.'.'.$this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // -------------------------------------------------------------------------
    // Credential loading
    // -------------------------------------------------------------------------

    /**
     * Load and parse the service account JSON.
     * Supports three formats:
     *  - Absolute file path:  /var/secrets/sa.json
     *  - Raw JSON string:     {"type":"service_account",...}
     *  - Base64-encoded JSON: eyJ0eXBl...
     *
     * @return array<string, string>
     */
    private function loadCredentials(): array
    {
        $raw = trim((string) config('location_intelligence.google.service_account_json', ''));

        if ($raw === '') {
            throw new LocationIntelligenceException(
                'GOOGLE_CLOUD_SERVICE_ACCOUNT_JSON is not set. '
                .'The Video Intelligence API requires a Service Account — API keys are not supported.',
                500,
            );
        }

        // File path
        if (file_exists($raw)) {
            $json = file_get_contents($raw);

            if ($json === false) {
                throw new LocationIntelligenceException(
                    'Could not read service account file at: '.$raw,
                    500,
                );
            }
        } else {
            // Try base64 decode first, fall back to treating as raw JSON
            $decoded = base64_decode($raw, true);
            $json    = ($decoded !== false && str_starts_with(ltrim($decoded), '{'))
                ? $decoded
                : $raw;
        }

        /** @var array<string, string>|null $credentials */
        $credentials = json_decode($json, true);

        if (
            ! is_array($credentials) ||
            ! isset($credentials['client_email'], $credentials['private_key']) ||
            ($credentials['type'] ?? '') !== 'service_account'
        ) {
            throw new LocationIntelligenceException(
                'Invalid service account JSON. '
                .'It must contain type=service_account, client_email, and private_key.',
                500,
            );
        }

        return $credentials;
    }
}
