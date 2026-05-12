<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\Auth\SocialTokenVerifier;
use App\Enums\AccountStatus;
use App\Enums\SocialAuthProvider;
use App\Models\User;
use App\Notifications\Auth\PasswordResetOtpNotification;
use App\Services\Auth\SocialIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token_with_default_preferences(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jamie Traveler',
            'email' => 'jamie@example.com',
            'password' => 'securePass123!',
            'password_confirmation' => 'securePass123!',
            'device_name' => 'Jamie iPhone',
            'device_platform' => 'ios',
            'device_identifier' => 'device-001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'jamie@example.com')
            ->assertJsonPath('data.user.preference.distance_unit', 'km')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'uuid',
                        'name',
                        'email',
                        'status',
                        'preference',
                    ],
                ],
                'meta',
                'errors',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jamie@example.com',
            'status' => AccountStatus::Active->value,
        ]);

        $user = User::query()->where('email', 'jamie@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'distance_unit' => 'km',
        ]);
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_name' => 'Jamie iPhone',
        ]);
    }

    public function test_active_user_can_login_and_fetch_profile_settings_and_onboarding(): void
    {
        $user = User::factory()->create([
            'email' => 'traveler@example.com',
            'password' => 'securePass123!',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'traveler@example.com',
            'password' => 'securePass123!',
            'device_name' => 'Android Pixel',
            'device_platform' => 'android',
            'device_identifier' => 'device-android-01',
        ]);

        $token = $loginResponse->json('data.token');

        $loginResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'traveler@example.com');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        $this->getJson('/api/v1/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.email', 'traveler@example.com')
            ->assertJsonPath('data.preference.distance_unit', 'km');

        $this->patchJson('/api/v1/me', [
            'name' => 'Traveler Updated',
            'email' => 'traveler.updated@example.com',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Traveler Updated')
            ->assertJsonPath('data.email', 'traveler.updated@example.com');

        $this->getJson('/api/v1/settings', $headers)
            ->assertOk()
            ->assertJsonPath('data.distance_unit', 'km');

        $this->patchJson('/api/v1/settings', [
            'distance_unit' => 'mi',
            'default_radius_meters' => 5000,
            'notifications_enabled' => false,
            'offline_auto_sync' => true,
            'theme' => 'dark',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.distance_unit', 'mi')
            ->assertJsonPath('data.default_radius_meters', 5000)
            ->assertJsonPath('data.notifications_enabled', false)
            ->assertJsonPath('data.theme', 'dark');

        $this->getJson('/api/v1/onboarding', $headers)
            ->assertOk()
            ->assertJsonPath('data.completed', false);

        $this->putJson('/api/v1/onboarding', [
            'completed' => true,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.completed', true);
    }

    public function test_user_can_sign_in_with_google_and_receive_a_token(): void
    {
        $this->mock(SocialTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with(SocialAuthProvider::Google, 'firebase-google-id-token')
                ->andReturn(new SocialIdentity(
                    provider: SocialAuthProvider::Google,
                    providerUserId: 'google-sub-001',
                    email: 'social@example.com',
                    emailVerified: true,
                    name: 'Google Traveler',
                    avatarUrl: 'https://example.com/avatar.jpg',
                    claims: ['sub' => 'google-sub-001'],
                ));
        });

        $this->postJson('/api/v1/auth/social/google', [
            'firebase_id_token' => 'firebase-google-id-token',
            'device_name' => 'Jamie iPhone',
            'device_platform' => 'ios',
            'device_identifier' => 'device-social-001',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Signed in with Google successfully.')
            ->assertJsonPath('meta.provider', 'google')
            ->assertJsonPath('meta.is_new_user', true)
            ->assertJsonPath('data.user.email', 'social@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
        ]);
        $this->assertDatabaseHas('user_social_accounts', [
            'provider' => SocialAuthProvider::Google->value,
            'provider_user_id' => 'google-sub-001',
            'provider_email' => 'social@example.com',
        ]);
    }

    public function test_existing_user_can_link_apple_sign_in_without_creating_a_duplicate_account(): void
    {
        $user = User::factory()->create([
            'email' => 'appletraveler@example.com',
        ]);

        $this->mock(SocialTokenVerifier::class, function ($mock) use ($user): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with(SocialAuthProvider::Apple, 'firebase-apple-id-token')
                ->andReturn(new SocialIdentity(
                    provider: SocialAuthProvider::Apple,
                    providerUserId: 'apple-sub-001',
                    email: $user->email,
                    emailVerified: true,
                    name: null,
                    avatarUrl: null,
                    claims: ['sub' => 'apple-sub-001'],
                ));
        });

        $this->postJson('/api/v1/auth/social/apple', [
            'firebase_id_token' => 'firebase-apple-id-token',
            'name' => 'Apple Traveler',
            'email' => $user->email,
            'device_name' => 'Jamie iPhone',
            'device_platform' => 'ios',
            'device_identifier' => 'device-social-apple-001',
        ])
            ->assertOk()
            ->assertJsonPath('meta.provider', 'apple')
            ->assertJsonPath('meta.is_new_user', false)
            ->assertJsonPath('meta.social_account_linked', true)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->id,
            'provider' => SocialAuthProvider::Apple->value,
            'provider_user_id' => 'apple-sub-001',
        ]);
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => 'securePass123!',
            'status' => AccountStatus::Suspended,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'securePass123!',
            'device_name' => 'Blocked Device',
            'device_platform' => 'ios',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['email'],
            ]);
    }

    public function test_authenticated_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Device');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_reset_flow_returns_successful_responses(): void
    {
        $user = User::factory()->create([
            'email' => 'recover@example.com',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'recover@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $rawToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'recover@example.com',
            'token' => $rawToken,
            'password' => 'newSecurePass123!',
            'password_confirmation' => 'newSecurePass123!',
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_password_reset_otp_flow_can_verify_email_before_resetting_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'otp@example.com',
        ]);

        $challengeId = $this->postJson('/api/v1/auth/password-otp/request', [
            'email' => 'otp@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.challenge_id');

        $this->assertNotNull($challengeId);

        $verificationCode = null;

        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function (PasswordResetOtpNotification $notification) use (&$verificationCode): bool {
            $verificationCode = $notification->code;

            return $notification->expiresInMinutes > 0;
        });

        $this->assertNotNull($verificationCode);

        $resetToken = $this->postJson('/api/v1/auth/password-otp/verify', [
            'email' => 'otp@example.com',
            'challenge_id' => $challengeId,
            'code' => $verificationCode,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.reset_token');

        $this->assertNotNull($resetToken);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'otp@example.com',
            'token' => $resetToken,
            'password' => 'otpResetPass123!',
            'password_confirmation' => 'otpResetPass123!',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'otp@example.com',
            'password' => 'otpResetPass123!',
            'device_name' => 'OTP Test Device',
            'device_platform' => 'ios',
            'device_identifier' => 'otp-device-001',
        ])->assertOk()
            ->assertJsonPath('success', true);
    }
}
