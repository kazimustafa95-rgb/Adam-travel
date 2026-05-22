<?php

namespace App\Jobs\PublicApi;

use App\Events\PublicApi\LocationSuggestionStatusUpdated;
use App\Exceptions\PublicApiException;
use App\Services\PublicApi\LocationSuggestionAsyncService;
use App\Services\PublicApi\LocationSuggestionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeLocationSuggestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $input,
    ) {
        $this->onQueue('ai-generation');
    }

    public function handle(
        LocationSuggestionsService $locationSuggestionsService,
        LocationSuggestionAsyncService $asyncService,
    ): void {
        $asyncService->markProcessing($this->token);
        event(new LocationSuggestionStatusUpdated(
            token: $this->token,
            status: LocationSuggestionAsyncService::STATUS_PROCESSING,
        ));

        try {
            $result = $locationSuggestionsService->getLocations($this->input, ['mode' => 'async']);

            $asyncService->markCompleted($this->token, $result);
            event(new LocationSuggestionStatusUpdated(
                token: $this->token,
                status: LocationSuggestionAsyncService::STATUS_COMPLETED,
            ));
        } catch (PublicApiException $exception) {
            $asyncService->markFailed($this->token, $exception->getMessage());
            event(new LocationSuggestionStatusUpdated(
                token: $this->token,
                status: LocationSuggestionAsyncService::STATUS_FAILED,
                error: $exception->getMessage(),
            ));
        } catch (\Throwable) {
            $asyncService->markFailed($this->token, 'Location suggestion analysis failed.');
            event(new LocationSuggestionStatusUpdated(
                token: $this->token,
                status: LocationSuggestionAsyncService::STATUS_FAILED,
                error: 'Location suggestion analysis failed.',
            ));
        }
    }
}
