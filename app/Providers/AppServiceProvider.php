<?php

namespace App\Providers;

use App\Contracts\Auth\SocialTokenVerifier;
use App\Services\Auth\JwtSocialTokenVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SocialTokenVerifier::class, JwtSocialTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email', 'guest'));

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset-request', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email', 'guest'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset-verify', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email', 'guest'));

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('imports-submission', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(20)->by((string) $key);
        });

        RateLimiter::for('ai-generation', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perHour(20)->by((string) $key);
        });

        RateLimiter::for('public-location-suggestions-submit', function (Request $request): array {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $submitPerMinute = max(1, (int) config('location_suggestions.rate_limits.submit_per_minute', 20));
            $submitPerHour = max($submitPerMinute, (int) config('location_suggestions.rate_limits.submit_per_hour', 100));

            return [
                Limit::perMinute($submitPerMinute)->by((string) $key),
                Limit::perHour($submitPerHour)->by((string) $key),
            ];
        });

        RateLimiter::for('ai-status-poll', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $token = (string) $request->route('token', 'status');
            $statusPollsPerMinute = max(1, (int) config('location_suggestions.rate_limits.status_polls_per_minute', 120));

            return Limit::perMinute($statusPollsPerMinute)->by((string) $key.'|'.$token);
        });

        RateLimiter::for('proximity-check', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(30)->by((string) $key);
        });
    }
}
