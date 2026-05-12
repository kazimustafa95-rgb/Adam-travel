<?php

namespace App\Services\Auth;

use App\Contracts\Auth\SocialTokenVerifier;
use App\Enums\SocialAuthProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use JsonException;

class JwtSocialTokenVerifier implements SocialTokenVerifier
{
    private const CERTIFICATES_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function verify(SocialAuthProvider $provider, string $firebaseIdToken): SocialIdentity
    {
        [$header, $claims, $signature, $signingInput] = $this->parseJwt($firebaseIdToken);

        if (($header['alg'] ?? null) !== 'RS256') {
            $this->throwInvalidToken('Unsupported Firebase authentication token algorithm.');
        }

        $projectIds = $this->configuredProjectIds();
        $audience = $this->verifyStandardClaims($claims, $projectIds);
        $this->verifyFirebaseProviderClaim($provider, $claims);
        $this->verifySignature($header['kid'] ?? null, $signingInput, $signature);

        $providerUserId = $this->resolveProviderUserId($provider, $claims);
        $email = isset($claims['email']) ? strtolower((string) $claims['email']) : null;
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        $name = $claims['name'] ?? null;
        $avatarUrl = $claims['picture'] ?? null;

        return new SocialIdentity(
            provider: $provider,
            providerUserId: $providerUserId,
            email: is_string($email) && $email !== '' ? $email : null,
            emailVerified: $emailVerified,
            name: is_string($name) && trim($name) !== '' ? trim($name) : null,
            avatarUrl: is_string($avatarUrl) && trim($avatarUrl) !== '' ? trim($avatarUrl) : null,
            claims: array_merge($claims, [
                'firebase_project_id' => $audience,
            ]),
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string}
     */
    protected function parseJwt(string $firebaseIdToken): array
    {
        $parts = explode('.', $firebaseIdToken);

        if (count($parts) !== 3) {
            $this->throwInvalidToken('The Firebase authentication token format is invalid.');
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;

        try {
            /** @var array<string, mixed> $header */
            $header = json_decode($this->base64UrlDecode($encodedHeader), true, 512, JSON_THROW_ON_ERROR);
            /** @var array<string, mixed> $claims */
            $claims = json_decode($this->base64UrlDecode($encodedClaims), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->throwInvalidToken('The Firebase authentication token payload could not be decoded.');
        }

        return [
            $header,
            $claims,
            $this->base64UrlDecode($encodedSignature),
            $encodedHeader.'.'.$encodedClaims,
        ];
    }

    /**
     * @return list<string>
     */
    protected function configuredProjectIds(): array
    {
        /** @var list<string> $projectIds */
        $projectIds = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) config('services.firebase.project_ids', []),
        )));

        if ($projectIds === []) {
            $singleProjectId = trim((string) config('services.firebase.project_id', ''));

            if ($singleProjectId !== '') {
                $projectIds[] = $singleProjectId;
            }
        }

        if ($projectIds === []) {
            $this->throwInvalidToken('Firebase authentication is not configured for this environment.');
        }

        return $projectIds;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $projectIds
     */
    protected function verifyStandardClaims(array $claims, array $projectIds): string
    {
        $audience = trim((string) ($claims['aud'] ?? ''));

        if ($audience === '' || ! in_array($audience, $projectIds, true)) {
            $this->throwInvalidToken('The Firebase authentication token audience is invalid.');
        }

        $issuer = trim((string) ($claims['iss'] ?? ''));
        $expectedIssuer = 'https://securetoken.google.com/'.$audience;

        if ($issuer !== $expectedIssuer) {
            $this->throwInvalidToken('The Firebase authentication token issuer is invalid.');
        }

        $subject = trim((string) ($claims['sub'] ?? ''));

        if ($subject === '' || strlen($subject) > 128) {
            $this->throwInvalidToken('The Firebase authentication token subject is invalid.');
        }

        $now = now()->getTimestamp();
        $clockSkew = 60;
        $expiresAt = isset($claims['exp']) ? (int) $claims['exp'] : 0;
        $issuedAt = isset($claims['iat']) ? (int) $claims['iat'] : 0;
        $authTime = isset($claims['auth_time']) ? (int) $claims['auth_time'] : 0;

        if ($expiresAt <= ($now - $clockSkew)) {
            $this->throwInvalidToken('The Firebase authentication token has expired.');
        }

        if ($issuedAt > ($now + $clockSkew)) {
            $this->throwInvalidToken('The Firebase authentication token issue time is invalid.');
        }

        if ($authTime > ($now + $clockSkew)) {
            $this->throwInvalidToken('The Firebase authentication token authentication time is invalid.');
        }

        return $audience;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function verifyFirebaseProviderClaim(SocialAuthProvider $provider, array $claims): void
    {
        $firebase = $claims['firebase'] ?? null;
        $signInProvider = is_array($firebase) ? ($firebase['sign_in_provider'] ?? null) : null;
        $expectedProvider = match ($provider) {
            SocialAuthProvider::Google => 'google.com',
            SocialAuthProvider::Apple => 'apple.com',
        };

        if (! is_string($signInProvider) || $signInProvider !== $expectedProvider) {
            $this->throwInvalidToken('The Firebase authentication token does not belong to the requested provider.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function resolveProviderUserId(SocialAuthProvider $provider, array $claims): string
    {
        $firebase = $claims['firebase'] ?? null;
        $identities = is_array($firebase) ? ($firebase['identities'] ?? null) : null;
        $providerKey = match ($provider) {
            SocialAuthProvider::Google => 'google.com',
            SocialAuthProvider::Apple => 'apple.com',
        };

        if (is_array($identities)) {
            $candidate = $identities[$providerKey] ?? null;

            if (is_array($candidate) && isset($candidate[0]) && is_string($candidate[0]) && trim($candidate[0]) !== '') {
                return trim($candidate[0]);
            }
        }

        $subject = trim((string) ($claims['sub'] ?? ''));

        if ($subject === '') {
            $this->throwInvalidToken('The Firebase authentication token is missing a subject identifier.');
        }

        return $subject;
    }

    protected function verifySignature(?string $keyId, string $signingInput, string $signature): void
    {
        if (! is_string($keyId) || trim($keyId) === '') {
            $this->throwInvalidToken('The Firebase authentication token is missing a key identifier.');
        }

        $certificates = $this->certificates();
        $certificate = $certificates[$keyId] ?? null;

        if (! is_string($certificate) || trim($certificate) === '') {
            $this->throwInvalidToken('The matching Firebase signing certificate was not found.');
        }

        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            $this->throwInvalidToken('The Firebase signing certificate could not be parsed.');
        }

        $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            $this->throwInvalidToken('The Firebase authentication token signature is invalid.');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function certificates(): array
    {
        $cacheKey = 'firebase-auth:certificates';
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            /** @var array<string, string> $cached */
            return $cached;
        }

        $response = Http::acceptJson()->timeout(10)->get(self::CERTIFICATES_URL);

        if (! $response->successful()) {
            $this->throwInvalidToken('The Firebase signing certificates could not be loaded.');
        }

        $certificates = $response->json();

        if (! is_array($certificates) || $certificates === []) {
            $this->throwInvalidToken('The Firebase signing certificates response was empty.');
        }

        $ttlSeconds = $this->resolveCertificateCacheTtl($response->header('Cache-Control'));

        Cache::put($cacheKey, $certificates, now()->addSeconds($ttlSeconds));

        /** @var array<string, string> $certificates */
        return $certificates;
    }

    protected function resolveCertificateCacheTtl(?string $cacheControl): int
    {
        if (is_string($cacheControl) && preg_match('/max-age=(\d+)/', $cacheControl, $matches) === 1) {
            return max(60, (int) $matches[1]);
        }

        return 3600;
    }

    protected function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            $this->throwInvalidToken('The Firebase authentication token encoding is invalid.');
        }

        return $decoded;
    }

    protected function throwInvalidToken(string $message): never
    {
        throw ValidationException::withMessages([
            'firebase_id_token' => [$message],
        ]);
    }
}
