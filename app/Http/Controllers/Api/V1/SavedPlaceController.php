<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SavedPlaces\SavedPlaceIndexRequest;
use App\Http\Requests\Api\V1\SavedPlaces\StoreSavedPlaceRequest;
use App\Http\Requests\Api\V1\SavedPlaces\UpdateSavedPlaceRequest;
use App\Http\Resources\Api\V1\SavedPlaceDetailResource;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Models\SavedPlace;
use App\Models\User;
use App\Services\SavedPlaces\SavedPlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedPlaceController extends BaseApiController
{
    public function __construct(protected SavedPlaceService $savedPlaceService) {}

    public function index(SavedPlaceIndexRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $paginator = $this->savedPlaceService->paginateForUser($user, $request->validated());

        return $this->success(
            data: SavedPlaceResource::collection($paginator->items())->resolve(),
            message: 'Saved places loaded successfully.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreSavedPlaceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $savedPlace = $this->savedPlaceService->create($user, $request->validated());

        return $this->success(
            data: (new SavedPlaceResource($savedPlace))->resolve(),
            message: 'Saved place created successfully.',
            status: 201,
        );
    }

    public function show(Request $request, SavedPlace $savedPlace): JsonResponse
    {
        $this->authorize('view', $savedPlace);
        $savedPlace = $this->savedPlaceService->detailForUser($savedPlace);

        return $this->success(
            data: (new SavedPlaceDetailResource($savedPlace))->resolve(),
            message: 'Saved place loaded successfully.',
        );
    }

    public function update(UpdateSavedPlaceRequest $request, SavedPlace $savedPlace): JsonResponse
    {
        $this->authorize('update', $savedPlace);
        $updatedSavedPlace = $this->savedPlaceService->update($savedPlace, $request->validated());

        return $this->success(
            data: (new SavedPlaceResource($updatedSavedPlace))->resolve(),
            message: 'Saved place updated successfully.',
        );
    }

    public function destroy(Request $request, SavedPlace $savedPlace): JsonResponse
    {
        $this->authorize('delete', $savedPlace);
        $this->savedPlaceService->delete($savedPlace);

        return $this->success(
            message: 'Saved place deleted successfully.',
        );
    }
}
