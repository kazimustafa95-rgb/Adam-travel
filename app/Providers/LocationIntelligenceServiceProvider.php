<?php

namespace App\Providers;

use App\Contracts\LocationIntelligence\InputTypeDetectorContract;
use App\Services\LocationIntelligence\InputTypeDetectorService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LocationIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/location_intelligence.php',
            'location_intelligence',
        );

        $this->app->bind(InputTypeDetectorContract::class, InputTypeDetectorService::class);
    }

    public function boot(): void
    {
        $this->registerRateLimiter();
        $this->registerRoutes();
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('location-intelligence', function (Request $request): Limit {
            $key         = $request->ip();
            $maxAttempts = (int) config('location_intelligence.rate_limiting.max_attempts', 20);
            $perMinutes  = (int) config('location_intelligence.rate_limiting.per_minutes', 1);

            return Limit::perMinutes($perMinutes, $maxAttempts)->by($key);
        });
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/v2')
            ->group(base_path('routes/api_v2.php'));
    }
}
