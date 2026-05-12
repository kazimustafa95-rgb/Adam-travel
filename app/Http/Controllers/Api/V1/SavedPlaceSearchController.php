<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SavedPlaces\SavedPlaceSearchRequest;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Services\SavedPlaces\SavedPlaceService;
use Illuminate\Http\JsonResponse;

class SavedPlaceSearchController extends BaseApiController
{
    public function __construct(protected SavedPlaceService $savedPlaceService)
    {
    }

    public function __invoke(SavedPlaceSearchRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $results = $this->savedPlaceService->searchForUser($user, $request->validated());

        return $this->success(
            data: SavedPlaceResource::collection($results)->resolve(),
            message: 'Saved place search completed successfully.',
            meta: [
                'count' => $results->count(),
            ],
        );
    }
}
