<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SavedPlaces\StoreSavedPlaceCollectionRequest;
use App\Http\Resources\Api\V1\SavedPlaceCollectionResource;
use App\Models\SavedPlace;
use App\Models\User;
use App\Services\SavedPlaces\SavedPlaceCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedPlaceCollectionController extends BaseApiController
{
    public function __construct(protected SavedPlaceCollectionService $savedPlaceCollectionService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $savedPlace = null;

        if ($request->filled('saved_place_id')) {
            $savedPlace = SavedPlace::query()->findOrFail((int) $request->input('saved_place_id'));
            $this->authorize('view', $savedPlace);
        }

        $collections = $this->savedPlaceCollectionService->listForUser($user);

        return $this->success(
            data: [
                'selected_saved_place_collection_id' => $savedPlace?->saved_place_collection_id,
                'collections' => SavedPlaceCollectionResource::collection($collections)->resolve(),
            ],
            message: 'Saved place collections loaded successfully.',
        );
    }

    public function store(StoreSavedPlaceCollectionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $collection = $this->savedPlaceCollectionService->create($user, $request->validated());

        return $this->success(
            data: (new SavedPlaceCollectionResource($collection))->resolve(),
            message: 'Saved place collection created successfully.',
            status: 201,
        );
    }
}
