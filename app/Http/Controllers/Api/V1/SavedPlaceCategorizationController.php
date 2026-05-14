<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SavedPlaces\AssignSavedPlaceCollectionRequest;
use App\Http\Resources\Api\V1\SavedPlaceResource;
use App\Models\SavedPlace;
use App\Models\SavedPlaceCollection;
use App\Services\SavedPlaces\SavedPlaceCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SavedPlaceCategorizationController extends BaseApiController
{
    public function __construct(protected SavedPlaceCollectionService $savedPlaceCollectionService) {}

    public function store(AssignSavedPlaceCollectionRequest $request, SavedPlace $savedPlace): JsonResponse
    {
        $this->authorize('update', $savedPlace);

        $collection = null;
        $collectionId = $request->validated('saved_place_collection_id');

        if ($collectionId !== null) {
            $collection = SavedPlaceCollection::query()
                ->where('user_id', $request->user()?->id)
                ->find($collectionId);

            if (! $collection) {
                throw ValidationException::withMessages([
                    'saved_place_collection_id' => ['The selected collection is not available.'],
                ]);
            }
        }

        $savedPlace = $this->savedPlaceCollectionService->assign($savedPlace, $collection);

        return $this->success(
            data: (new SavedPlaceResource($savedPlace))->resolve(),
            message: 'Saved place category updated successfully.',
        );
    }
}
