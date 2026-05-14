<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicApiException;
use App\Http\Requests\Api\V1\Public\LocationSuggestionsRequest;
use App\Services\PublicApi\LocationSuggestionsService;
use Illuminate\Http\JsonResponse;

class PublicLocationSuggestionsController extends BaseApiController
{
    public function __construct(protected LocationSuggestionsService $locationSuggestionsService) {}

    public function __invoke(LocationSuggestionsRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            return $this->success(
                data: $this->locationSuggestionsService->getLocations((string) $payload['input']),
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
