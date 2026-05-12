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

        RateLimiter::for('proximity-check', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(30)->by((string) $key);
        });
    }
}
