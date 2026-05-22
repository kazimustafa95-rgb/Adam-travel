<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicApiException;
use App\Services\PublicApi\LocationSuggestionAsyncService;
use Illuminate\Http\JsonResponse;

class PublicAsyncLocationSuggestionsStatusController extends BaseApiController
{
    public function __construct(protected LocationSuggestionAsyncService $locationSuggestionAsyncService) {}

    public function __invoke(string $token): JsonResponse
    {
        try {
            return $this->success(
                data: $this->locationSuggestionAsyncService->get($token),
                message: 'Location suggestion status loaded successfully.',
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
