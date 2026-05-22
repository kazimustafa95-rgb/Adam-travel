<?php

namespace App\Services\PublicApi;

use App\Exceptions\PublicApiException;
use App\Jobs\PublicApi\AnalyzeLocationSuggestionsJob;
use App\Support\PublicApi\LocationSuggestionRealtime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LocationSuggestionAsyncService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, mixed>
     */
    public function create(string $input, array $analysisDebug = []): array
    {
        if (! (bool) config('location_suggestions.async.enabled', true)) {
            throw new PublicApiException('Async location suggestions are disabled.', 422);
        }

        $token = (string) Str::uuid();
        $payload = [
            'token' => $token,
            'status' => self::STATUS_PENDING,
            'input' => $input,
            'result' => null,
            'error' => null,
            'realtime' => LocationSuggestionRealtime::subscriptionPayload($token),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        if ((bool) config('location_suggestions.debug.enabled', false) && $analysisDebug !== []) {
            $payload['analysis_debug'] = $analysisDebug;
        }

        $this->put($token, $payload);

        AnalyzeLocationSuggestionsJob::dispatch($token, $input);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $token): array
    {
        $payload = Cache::get($this->cacheKey($token));

        if (! is_array($payload)) {
            throw new PublicApiException('Location suggestion job not found.', 404);
        }

        return $payload;
    }

    public function markProcessing(string $token): void
    {
        $payload = $this->get($token);
        $payload['status'] = self::STATUS_PROCESSING;
        $payload['updated_at'] = now()->toIso8601String();

        $this->put($token, $payload);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(string $token, array $result): void
    {
        $payload = $this->get($token);
        $payload['status'] = self::STATUS_COMPLETED;
        $payload['result'] = $result;
        $payload['error'] = null;
        $payload['updated_at'] = now()->toIso8601String();

        $this->put($token, $payload);
    }

    public function markFailed(string $token, string $message): void
    {
        $payload = $this->get($token);
        $payload['status'] = self::STATUS_FAILED;
        $payload['error'] = $message;
        $payload['updated_at'] = now()->toIso8601String();

        $this->put($token, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function put(string $token, array $payload): void
    {
        Cache::put(
            $this->cacheKey($token),
            $payload,
            now()->addMinutes((int) config('location_suggestions.async.ttl_minutes', 1440)),
        );
    }

    protected function cacheKey(string $token): string
    {
        return 'location-suggestions:async:'.$token;
    }
}
