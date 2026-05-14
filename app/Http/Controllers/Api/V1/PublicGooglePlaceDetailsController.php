<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicApiException;
use App\Http\Requests\Api\V1\Public\GooglePlaceDetailsRequest;
use App\Services\PublicApi\GooglePlaceDetailsService;
use Illuminate\Http\JsonResponse;

class PublicGooglePlaceDetailsController extends BaseApiController
{
    public function __construct(protected GooglePlaceDetailsService $googlePlaceDetailsService) {}

    public function __invoke(GooglePlaceDetailsRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            return $this->success(
                data: $this->googlePlaceDetailsService->getLocationDetail(
                    placeQuery: (string) $payload['place_query'],
                    regionCode: (string) ($payload['region_code'] ?? 'PK'),
                ),
                message: 'Google place details loaded successfully.',
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
