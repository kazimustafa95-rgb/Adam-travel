<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicApiException;
use App\Http\Requests\Api\V1\Public\LocationSuggestionsRequest;
use App\Services\PublicApi\LocationSuggestionAsyncService;
use Illuminate\Http\JsonResponse;

class PublicAsyncLocationSuggestionsController extends BaseApiController
{
    public function __construct(protected LocationSuggestionAsyncService $locationSuggestionAsyncService) {}

    public function __invoke(LocationSuggestionsRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            return $this->success(
                data: $this->locationSuggestionAsyncService->create((string) $payload['input'], [
                    'mode' => 'queued',
                    'used_async' => true,
                    'routing_reason' => 'direct_async',
                ]),
                message: 'Location suggestion analysis queued successfully.',
                status: 202,
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
