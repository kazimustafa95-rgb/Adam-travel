<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Map\MapPinsRequest;
use App\Http\Resources\Api\V1\MapPinResource;
use App\Services\SavedPlaces\SavedPlaceService;
use Illuminate\Http\JsonResponse;

class MapPinsController extends BaseApiController
{
    public function __construct(protected SavedPlaceService $savedPlaceService)
    {
    }

    public function __invoke(MapPinsRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $pins = $this->savedPlaceService->pinsForUser($user, $request->validated());

        return $this->success(
            data: MapPinResource::collection($pins)->resolve(),
            message: 'Map pins loaded successfully.',
            meta: [
                'count' => $pins->count(),
            ],
        );
    }
}
