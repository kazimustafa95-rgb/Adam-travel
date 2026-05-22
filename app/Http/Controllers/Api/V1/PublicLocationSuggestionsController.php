<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicApiException;
use App\Http\Requests\Api\V1\Public\LocationSuggestionsRequest;
use App\Services\PublicApi\LocationSuggestionRoutingService;
use App\Services\PublicApi\LocationSuggestionsService;
use Illuminate\Http\JsonResponse;

class PublicLocationSuggestionsController extends BaseApiController
{
    public function __construct(
        protected LocationSuggestionsService $locationSuggestionsService,
        protected LocationSuggestionRoutingService $locationSuggestionRoutingService,
    ) {}

    public function __invoke(LocationSuggestionsRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $input = (string) $payload['input'];
        $preferAsync = (bool) ($payload['prefer_async'] ?? false);

        try {
            $queuedPayload = $this->locationSuggestionRoutingService->routeToAsyncIfNeeded($input, $preferAsync);

            if ($queuedPayload !== null) {
                return $this->success(
                    data: $queuedPayload,
                    message: 'Location suggestion analysis queued successfully.',
                    status: 202,
                );
            }

            return $this->success(
                data: $this->locationSuggestionsService->getLocations($input, ['mode' => 'sync']),
                message: 'Location suggestions loaded successfully.',
            );
        } catch (PublicApiException $exception) {
            return $this->error(
                message: $exception->getMessage(),
                errors: $exception->errors(),
                status: $exception->status(),
            );
        }
    }
}
