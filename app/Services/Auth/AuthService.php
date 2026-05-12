<?php

namespace App\Services\Auth;

use App\Contracts\Auth\SocialTokenVerifier;
use App\Enums\AccountStatus;
use App\Enums\SocialAuthProvider;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSocialAccount;
use App\Services\Users\ProfileService;
use App\Services\Users\UserPreferenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected UserPreferenceService $preferenceService,
        protected ProfileService $profileService,
        protected SocialTokenVerifier $socialTokenVerifier,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, user: \App\Models\User}
     *
     * @throws ValidationException
     */
    public function register(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => strtolower((string) $payload['email']),
                'password' => $payload['password'],
                'status' => AccountStatus::Active,
                'last_seen_at' => now(),
            ]);

            $this->preferenceService->ensureDefaults($user);
            $this->registerDevice($user, $payload);

            return [
                'token' => $user->createToken($payload['device_name'])->plainTextToken,
                'user' => $user->fresh()->load('preference'),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, user: \App\Models\User}
     *
     * @throws ValidationException
     */
    public function login(array $payload): array
    {
        $user = User::query()->where('email', strtolower((string) $payload['email']))->first();

        if (! $user || ! Hash::check((string) $payload['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== AccountStatus::Active) {
            throw ValidationException::withMessages([
                'email' => ['This account is currently unavailable.'],
            ]);
        }

        return DB::transaction(function () use ($user, $payload): array {
            $this->preferenceService->ensureDefaults($user);
            $this->profileService->touchLastSeen($user);
            $this->registerDevice($user, $payload);

            return [
                'token' => $user->createToken($payload['device_name'])->plainTextToken,
                'user' => $user->fresh()->load('preference'),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, user: \App\Models\User, is_new_user: bool, social_account_linked: bool}
     */
    public function socialLogin(SocialAuthProvider $provider, array $payload): array
    {
        $identity = $this->socialTokenVerifier->verify(
            provider: $provider,
            firebaseIdToken: (string) $payload['firebase_id_token'],
        );

        return DB::transaction(function () use ($provider, $payload, $identity): array {
            $socialAccount = UserSocialAccount::query()
                ->with('user')
                ->where('provider', $provider->value)
                ->where('provider_user_id', $identity->providerUserId)
                ->first();

            $user = $socialAccount?->user;
            $isNewUser = false;
            $socialAccountLinked = false;
            $resolvedEmail = $this->resolveSocialEmail($identity, $payload);

            if ($user === null && $resolvedEmail !== null) {
                $user = User::query()->where('email', $resolvedEmail)->first();
            }

            if ($user === null) {
                if ($resolvedEmail === null) {
                    throw ValidationException::withMessages([
                        'email' => ['A verified email address is required to complete this social sign-in.'],
                    ]);
                }

                $isNewUser = true;
                $user = User::query()->create([
                    'name' => $this->resolveSocialName($identity, $payload, $resolvedEmail),
                    'email' => $resolvedEmail,
                    'email_verified_at' => $identity->emailVerified ? now() : null,
                    'password' => Str::random(40),
                    'status' => AccountStatus::Active,
                    'last_seen_at' => now(),
                ]);
            }

            if ($user->status !== AccountStatus::Active) {
                throw ValidationException::withMessages([
                    'email' => ['This account is currently unavailable.'],
                ]);
            }

            if ($identity->emailVerified && $user->email_verified_at === null) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            $this->preferenceService->ensureDefaults($user);
            $this->profileService->touchLastSeen($user);
            $this->registerDevice($user, $payload);

            if ($socialAccount === null) {
                $socialAccount = new UserSocialAccount([
                    'provider' => $provider,
                    'provider_user_id' => $identity->providerUserId,
                ]);
                $socialAccount->user()->associate($user);
                $socialAccountLinked = true;
            }

            $socialAccount->forceFill([
                'provider_email' => $resolvedEmail,
                'provider_email_verified_at' => $identity->emailVerified ? now() : null,
                'avatar_url' => $identity->avatarUrl,
                'provider_payload' => $identity->claims,
                'last_used_at' => now(),
            ])->save();

            return [
                'token' => $user->createToken($payload['device_name'])->plainTextToken,
                'user' => $user->fresh()->load('preference'),
                'is_new_user' => $isNewUser,
                'social_account_linked' => $socialAccountLinked,
            ];
        });
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function registerDevice(User $user, array $payload): void
    {
        $deviceName = (string) $payload['device_name'];
        $devicePlatform = $payload['device_platform'] ?? 'unknown';
        $deviceIdentifier = $payload['device_identifier'] ?? null;
        $deviceHash = hash('sha256', (string) ($deviceIdentifier ?: $deviceName.'|'.$devicePlatform));

        UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_identifier_hash' => $deviceHash,
            ],
            [
                'device_name' => $deviceName,
                'device_platform' => (string) $devicePlatform,
                'last_ip' => request()->ip(),
                'last_synced_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveSocialEmail(SocialIdentity $identity, array $payload): ?string
    {
        $email = $identity->email ?? ($payload['email'] ?? null);

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveSocialName(SocialIdentity $identity, array $payload, string $email): string
    {
        $candidates = [
            $payload['name'] ?? null,
            $identity->name,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return Str::title((string) str($email)->before('@')->replace(['.', '_', '-'], ' '));
    }
}
