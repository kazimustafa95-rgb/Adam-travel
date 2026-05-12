<?php

namespace App\Services\Auth;

use App\Enums\AuthOtpPurpose;
use App\Models\AuthOtpCode;
use App\Models\User;
use App\Notifications\Auth\PasswordResetOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetOtpService
{
    /**
     * @return array<string, mixed>
     */
    public function requestCode(string $email): array
    {
        $normalizedEmail = strtolower($email);
        $challengeId = (string) Str::uuid();
        $expiresInMinutes = $this->expiresInMinutes();

        AuthOtpCode::query()
            ->where('purpose', AuthOtpPurpose::PasswordReset->value)
            ->where('email', $normalizedEmail)
            ->whereNull('consumed_at')
            ->delete();

        $user = User::query()->where('email', $normalizedEmail)->first();

        if ($user !== null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            AuthOtpCode::query()->create([
                'user_id' => $user->id,
                'purpose' => AuthOtpPurpose::PasswordReset,
                'challenge_id' => $challengeId,
                'email' => $normalizedEmail,
                'code_hash' => Hash::make($code),
                'attempt_count' => 0,
                'max_attempts' => $this->maxAttempts(),
                'expires_at' => now()->addMinutes($expiresInMinutes),
                'last_sent_at' => now(),
                'ip_address' => request()->ip(),
                'metadata' => [
                    'channel' => 'email',
                ],
            ]);

            $user->notify(new PasswordResetOtpNotification($code, $expiresInMinutes));
        }

        return [
            'challenge_id' => $challengeId,
            'email' => $normalizedEmail,
            'masked_email' => $this->maskEmail($normalizedEmail),
            'purpose' => AuthOtpPurpose::PasswordReset->value,
            'expires_in_seconds' => $expiresInMinutes * 60,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyCode(string $email, string $challengeId, string $code): array
    {
        $normalizedEmail = strtolower($email);

        /** @var \App\Models\AuthOtpCode|null $otp */
        $otp = AuthOtpCode::query()
            ->where('purpose', AuthOtpPurpose::PasswordReset->value)
            ->where('email', $normalizedEmail)
            ->where('challenge_id', $challengeId)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null) {
            $this->throwInvalidCode('The verification code is invalid or no longer available.');
        }

        if ($otp->expires_at->isPast()) {
            $otp->forceFill([
                'consumed_at' => now(),
            ])->save();

            $this->throwInvalidCode('The verification code has expired. Request a new code and try again.');
        }

        if ($otp->attempt_count >= $otp->max_attempts) {
            $otp->forceFill([
                'consumed_at' => now(),
            ])->save();

            $this->throwInvalidCode('The verification code has reached its maximum retry limit.');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $attemptCount = $otp->attempt_count + 1;

            $otp->forceFill([
                'attempt_count' => $attemptCount,
                'consumed_at' => $attemptCount >= $otp->max_attempts ? now() : null,
            ])->save();

            $this->throwInvalidCode('The verification code is incorrect.');
        }

        $user = $otp->user ?? User::query()->where('email', $normalizedEmail)->first();

        if ($user === null) {
            $otp->forceFill([
                'consumed_at' => now(),
            ])->save();

            $this->throwInvalidCode('The verification code is invalid or no longer available.');
        }

        $resetToken = Password::broker('users')->createToken($user);

        $otp->forceFill([
            'verified_at' => now(),
            'consumed_at' => now(),
        ])->save();

        return [
            'challenge_id' => $otp->challenge_id,
            'email' => $normalizedEmail,
            'masked_email' => $this->maskEmail($normalizedEmail),
            'purpose' => AuthOtpPurpose::PasswordReset->value,
            'reset_token' => $resetToken,
            'reset_token_expires_in_minutes' => (int) config('auth.passwords.users.expire', 60),
        ];
    }

    protected function expiresInMinutes(): int
    {
        return max(1, (int) config('auth.otp.expires', 10));
    }

    protected function maxAttempts(): int
    {
        return max(1, (int) config('auth.otp.max_attempts', 5));
    }

    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        $masked = str_repeat('*', max(2, mb_strlen($local) - mb_strlen($visible)));

        return $visible.$masked.'@'.$domain;
    }

    protected function throwInvalidCode(string $message): never
    {
        throw ValidationException::withMessages([
            'code' => [$message],
        ]);
    }
}
